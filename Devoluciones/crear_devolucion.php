<?php
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Mexico_City');

/* === MISMA SESIÓN DEL PORTAL === */
ini_set('session.cookie_httponly', '1');
session_name('GA');
session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/db.php';              // $pdo (portal)
require_once __DIR__ . '/../app/WhatsBusiness.php';   // funciones WhatsApp

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

/* === Tomar el usuario de la sesión (NO del cliente) === */
$uid = $_SESSION['user_id'] ?? null;
if (!$uid) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'msg'=>'No autenticado']); exit;
}

/* ===== Datos del form ===== */
$nombreProducto = trim($_POST['nombre_producto'] ?? '');
$noSerie        = trim($_POST['no_serie'] ?? '');
$codigoFactura  = trim($_POST['codigo_factura'] ?? '');
$noFactura      = trim($_POST['no_factura'] ?? '');
$marca          = trim($_POST['marca'] ?? '');
$motivo         = trim($_POST['motivo'] ?? '');
$contactoNombre = trim($_POST['contacto_nombre'] ?? '');
$contactoTel    = trim($_POST['contacto_tel'] ?? '');
$contactoEmail  = trim($_POST['contacto_email'] ?? '');

/* ===== sucursal y vendedor ===== */
$sucursal = strtolower(trim($_POST['sucursal'] ?? ''));
$vendedor_id = isset($_POST['vendedor']) && $_POST['vendedor'] !== '' ? (int)$_POST['vendedor'] : null;

$sucursalesPermitidas = [
  'deasa','tapatia','dimegsa','iluminacion','segsa','fesa','codi','vallarta','queretaro','gabsa','aiesa'
];
if (!in_array($sucursal, $sucursalesPermitidas, true)) {
  http_response_code(422);
  echo json_encode(['ok'=>false,'msg'=>'Sucursal inválida']); exit;
}

/* ===== Validaciones ===== */
if ($nombreProducto === '' || $motivo === '' || $contactoNombre === '') {
  http_response_code(422);
  echo json_encode(['ok'=>false,'msg'=>'Faltan campos obligatorios (producto, motivo, contacto).']); exit;
}
if ($contactoEmail !== '' && !filter_var($contactoEmail, FILTER_VALIDATE_EMAIL)) {
  http_response_code(422);
  echo json_encode(['ok'=>false,'msg'=>'Correo de contacto inválido.']); exit;
}

try {
  $pdo->beginTransaction();

  $estatus = 0; // 0 = En revisión
  $folio_placeholder = 'PEND';

  $sqlIns = "
    INSERT INTO devoluciones
      (folio, user_id, nombre_producto, no_serie, codigo_factura, no_factura, marca,
       motivo, contacto_nombre, contacto_tel, contacto_email, estatus, sucursal, vendedor_id,
       created_at, updated_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())
  ";

  $st = $pdo->prepare($sqlIns);
  $st->execute([
    $folio_placeholder, (int)$uid, $nombreProducto, $noSerie, $codigoFactura, $noFactura, $marca,
    $motivo, $contactoNombre, $contactoTel, $contactoEmail, (int)$estatus, $sucursal, $vendedor_id
  ]);

  $idDev = (int)$pdo->lastInsertId();
  if ($idDev <= 0) throw new RuntimeException('No se obtuvo el ID de la devolución.');

  $folio = 'DEV' . str_pad((string)$idDev, 5, '0', STR_PAD_LEFT);
  $stFol = $pdo->prepare("UPDATE devoluciones SET folio = ? WHERE id = ?");
  $stFol->execute([$folio, $idDev]);

  // ===== Adjuntos
  if (!empty($_FILES['adjuntos']['name'][0])) {
    $baseDir = __DIR__ . '/uploads_devoluciones';
    if (!is_dir($baseDir)) mkdir($baseDir, 0775, true);
    $uploadDir = $baseDir . '/' . $idDev;
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

    $stAdj = $pdo->prepare("
      INSERT INTO devolucion_adjuntos (devolucion_id, ruta, tipo, original, created_at)
      VALUES (?,?,?,?,NOW())
    ");

    foreach ($_FILES['adjuntos']['name'] as $i => $orig) {
      $err = $_FILES['adjuntos']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
      if ($err !== UPLOAD_ERR_OK) continue;
      $tmp   = $_FILES['adjuntos']['tmp_name'][$i];
      $mime  = $_FILES['adjuntos']['type'][$i] ?? '';
      $ext   = pathinfo($orig, PATHINFO_EXTENSION);
      $safe  = preg_replace('/[^a-zA-Z0-9_\.-]/','_', $orig);
      $tipo  = (strpos($mime, 'image/') === 0) ? 'foto' : ((strpos($mime, 'video/') === 0) ? 'video' : 'otro');
      $fname = uniqid('adj_', true) . ($ext ? '.'.$ext : '');
      $dest  = $uploadDir . '/' . $fname;
      if (!is_uploaded_file($tmp) || !move_uploaded_file($tmp, $dest)) {
        throw new RuntimeException('No se pudo mover un adjunto.');
      }
      $rel = 'uploads_devoluciones/'.$idDev.'/'.$fname;
      $stAdj->execute([$idDev, $rel, $tipo, $safe]);
    }
  }

  $pdo->commit();

  echo json_encode(['ok'=>true, 'folio'=>$folio, 'id'=>$idDev, 'sucursal'=>$sucursal, 'vendedor_id'=>$vendedor_id]);

  // ====== WhatsApp ======
  // Normaliza sucursal a mayúsculas “bonitas” para mensaje/consulta
  $map = [
    'deasa'=>'DEASA','tapatia'=>'TAPATIA','dimegsa'=>'DIMEGSA','iluminacion'=>'ILUMINACION',
    'segsa'=>'SEGSA','fesa'=>'FESA','codi'=>'CODI','vallarta'=>'VALLARTA',
    'queretaro'=>'QUERETARO','gabsa'=>'GABSA','aiesa'=>'AIESA'
  ];
  $sucUpper = $map[$sucursal] ?? strtoupper($sucursal);

  try {
    $destinos = [];

    // 1) Si eligieron vendedor: avisar SOLO a esa persona (si tiene teléfono)
    if ($vendedor_id) {
      $q = $pdo->prepare("
        SELECT COALESCE(NULLIF(nombre_factura,''), NULLIF(nombre_completo,'')) AS nombre,
               telefono AS tel
        FROM usuarios_cache
        WHERE id_usuario = :id
          AND telefono IS NOT NULL AND telefono <> ''
        LIMIT 1
      ");
      $q->execute([':id'=>$vendedor_id]);
      $row = $q->fetch();
      if ($row) $destinos[] = $row;
    }

    // 2) Si no hay elegido o no tiene teléfono -> avisar a todos los de la sucursal
    if (!$destinos) {
      $q = $pdo->prepare("
        SELECT COALESCE(NULLIF(nombre_factura,''), NULLIF(nombre_completo,'')) AS nombre,
               telefono AS tel
        FROM usuarios_cache
        WHERE TRIM(UPPER(sucursal)) = TRIM(UPPER(:suc))
          AND telefono IS NOT NULL AND telefono <> ''
      ");
      $q->execute([':suc'=>$sucUpper]);
      $destinos = $q->fetchAll(PDO::FETCH_ASSOC);
    }

    if (!$destinos) {
      file_put_contents(__DIR__.'/wa_new_dev.log', date('c')." $folio: sin teléfonos en $sucUpper\n", FILE_APPEND);
    } else {
      $plantilla = 'portal_nuevo_ga';
      $idioma    = 'en_US';

      foreach ($destinos as $usr) {
        $nombre = $usr['nombre'] ?: 'Vendedor';
        $telRaw = (string)($usr['tel'] ?? '');
        $digits = preg_replace('/\D+/', '', $telRaw);
        if ($digits === '' || strlen($digits) < 10) continue;

        // Normaliza a E.164 (México)
        $e164 = (strlen($digits) === 10) ? '+52'.$digits : '+'.$digits;

        $componentes = [[
          'type' => 'body',
          'parameters' => [
            ['type'=>'text','text'=> $nombre],
            ['type'=>'text','text'=> $folio],
            ['type'=>'text','text'=> $sucUpper],
          ]
        ]];

        wa_send_template($e164, $plantilla, $componentes, $idioma);
        file_put_contents(__DIR__.'/wa_new_dev.log', date('c')." $folio->$e164 [$nombre]\n", FILE_APPEND);
      }
    }
  } catch (Throwable $e) {
    file_put_contents(__DIR__.'/wa_new_dev.log', date('c')." ERROR $folio: ".$e->getMessage()."\n", FILE_APPEND);
  }

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'Error al guardar','err'=>$e->getMessage()]);
}

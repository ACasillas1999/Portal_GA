<?php
// ===== Salida JSON limpia =====
while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors','0'); error_reporting(E_ALL);

// Handlers
set_error_handler(function($lvl,$msg,$file,$line){
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'PHP','msg'=>"$msg ($file:$line)"]); exit;
});
set_exception_handler(function($e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'EXCEPTION','msg'=>$e->getMessage()]); exit;
});

// ===== Dependencias =====
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/db.php';         // Debe exponer $pdo (PDO)
require_once __DIR__ . '/../app/WhatsBusiness.php'; // funciones WhatsApp
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// ===== Sesión =====
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_secure', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? '1' : '0');
session_name('GA');
session_start();

// ===== Utils =====
function resp($ok,$arr=[]) { echo json_encode(['ok'=>$ok]+$arr); exit; }
function digits($s){ return preg_replace('/\D+/','', (string)$s); }

// ===== Leer sesión (varios nombres posibles) =====
$uid = $_SESSION['user_id'] ?? $_SESSION['ID'] ?? $_SESSION['idUsuario'] ?? $_SESSION['usuario_id'] ?? null;
if ($uid !== null) $uid = (int)$uid;

// ===== POST =====
$nombre        = trim($_POST['nombre_contacto'] ?? '');
$empresa       = trim($_POST['empresa'] ?? '');
$rfc           = strtoupper(trim($_POST['rfc'] ?? ''));
$telefono      = digits($_POST['telefono'] ?? '');
$email         = strtolower(trim($_POST['email'] ?? ''));
$sucursal      = trim($_POST['sucursal'] ?? '');
$desc          = trim($_POST['descripcion'] ?? '');
$no_serie      = trim($_POST['no_serie'] ?? '');
$no_factura    = trim($_POST['no_factura'] ?? '');
$fecha_factura = trim($_POST['fecha_factura'] ?? '');

// Fallback por email si no hay id en sesión
if (!$uid && $email !== '') {
  $q = $pdo->prepare('SELECT ID FROM usuarios WHERE email = ? LIMIT 1');
  $q->execute([$email]);
  $uid = $q->fetchColumn();
  $uid = $uid !== false ? (int)$uid : null;
}

// ===== Validaciones =====
$err = [];
if ($nombre === '' || mb_strlen($nombre) < 3) $err[] = 'Nombre de contacto requerido';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err[] = 'Correo inválido';
if ($empresa === '' || mb_strlen($empresa) < 2) $err[] = 'Empresa / Razón social requerida';
if (!preg_match('/^\d{10}$/', $telefono)) $err[] = 'Teléfono inválido (10 dígitos)';
if ($desc === '' || mb_strlen($desc) < 5) $err[] = 'Describe el caso con más detalle';
if ($fecha_factura !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_factura)) $err[] = 'Fecha de factura inválida (YYYY-MM-DD)';
if ($err) resp(false, ['msg'=>implode('. ', $err)]);

// ===== Guardar (una sola transacción) =====
try {
  $pdo->beginTransaction();

  // 1) Insert base con folio TEMP
  $st = $pdo->prepare("
    INSERT INTO protocolo_solicitudes
      (folio, id_usuario, nombre_contacto, empresa, rfc, telefono, email, sucursal,
       descripcion, no_serie, no_factura, fecha_factura,
       estado, fecha_envio, url_entrega, created_at, updated_at)
    VALUES
      ('TEMP', :uid, :nom, :emp, :rfc, :tel, :email, :suc,
       :des, :serie, :nfact, :ffact,
       0, NULL, NULL, NOW(), NOW())
  ");
  $st->execute([
    ':uid'   => $uid,
    ':nom'   => $nombre,
    ':emp'   => $empresa !== '' ? $empresa : null,
    ':rfc'   => $rfc !== '' ? $rfc : null,
    ':tel'   => $telefono,
    ':email' => $email,
    ':suc'   => $sucursal !== '' ? $sucursal : null,
    ':des'   => $desc,
    ':serie' => $no_serie !== '' ? $no_serie : null,
    ':nfact' => $no_factura !== '' ? $no_factura : null,
    ':ffact' => $fecha_factura !== '' ? $fecha_factura : null,
  ]);
  $id = (int)$pdo->lastInsertId();

  // 2) Folio definitivo
  $folio = sprintf('PRC-%s-%06d', date('Y'), $id);
  $pdo->prepare("UPDATE protocolo_solicitudes SET folio=:f, updated_at=NOW() WHERE id=:id")
      ->execute([':f'=>$folio, ':id'=>$id]);

  // 3) Adjuntos
  $year = date('Y');
  $month = date('m');
  $baseDir = __DIR__ . '/uploads/' . $year . '/' . $month . '/' . $id;

  if (!is_dir($baseDir)) {
    if (!@mkdir($baseDir, 0755, true) && !is_dir($baseDir)) {
      if (!@mkdir($baseDir, 0775, true) && !is_dir($baseDir)) {
        throw new RuntimeException('No pude crear la carpeta de uploads: ' . $baseDir);
      }
    }
  }
  if (!is_writable($baseDir)) {
    @chmod($baseDir, 0755);
    if (!is_writable($baseDir)) {
      @chmod($baseDir, 0775);
      if (!is_writable($baseDir)) {
        throw new RuntimeException('La carpeta no es escribible: ' . $baseDir);
      }
    }
  }

  $okExt    = ['pdf','jpg','jpeg','png','webp','heic','heif','gif','mp4','mov','avi','mkv','doc','docx','xls','xlsx','zip','7z','rar'];
  $maxBytes = 50 * 1024 * 1024; // 50MB
  $skipped  = [];

  $hasFiles = isset($_FILES['adjuntos']) && (
    (is_array($_FILES['adjuntos']['name']) && !empty($_FILES['adjuntos']['name'])) ||
    (!is_array($_FILES['adjuntos']['name']) && $_FILES['adjuntos']['name'] !== '')
  );

  if ($hasFiles) {
    $f = $_FILES['adjuntos'];
    $names  = is_array($f['name']) ? $f['name'] : [$f['name']];
    $tmps   = is_array($f['tmp_name']) ? $f['tmp_name'] : [$f['tmp_name']];
    $sizes  = is_array($f['size']) ? $f['size'] : [$f['size']];
    $errors = is_array($f['error']) ? $f['error'] : [$f['error']];

    $errMap = [
      UPLOAD_ERR_INI_SIZE   => 'excede upload_max_filesize',
      UPLOAD_ERR_FORM_SIZE  => 'excede MAX_FILE_SIZE del form',
      UPLOAD_ERR_PARTIAL    => 'subido parcialmente',
      UPLOAD_ERR_NO_FILE    => 'no se subió archivo',
      UPLOAD_ERR_NO_TMP_DIR => 'sin carpeta temporal',
      UPLOAD_ERR_CANT_WRITE => 'no se pudo escribir en disco',
      UPLOAD_ERR_EXTENSION  => 'extensión de PHP detuvo la subida',
    ];

    foreach ($names as $i => $name) {
      $name  = (string)$name;
      $tmp   = (string)($tmps[$i] ?? '');
      $size  = (int)($sizes[$i] ?? 0);
      $error = (int)($errors[$i] ?? UPLOAD_ERR_NO_FILE);

      if ($error !== UPLOAD_ERR_OK) { $skipped[] = "$name: " . ($errMap[$error] ?? "error $error"); continue; }
      if ($size <= 0) { $skipped[] = "$name: tamaño 0"; continue; }
      if ($size > $maxBytes) { $skipped[] = "$name: supera ".round($maxBytes/1024/1024)."MB"; continue; }

      $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
      if (!in_array($ext, $okExt, true)) { $skipped[] = "$name: extensión no permitida ($ext)"; continue; }
      if (!is_uploaded_file($tmp)) { $skipped[] = "$name: no es un archivo subido por PHP"; continue; }

      $safe = preg_replace('/[^a-z0-9\.\-\_]+/i', '_', $name);
      $dest = $baseDir . '/' . $id . '_' . bin2hex(random_bytes(6)) . '_' . $safe;

      if (!@move_uploaded_file($tmp, $dest)) {
        @chmod($baseDir, 0755);
        if (!@move_uploaded_file($tmp, $dest)) {
          @chmod($baseDir, 0775);
          if (!@move_uploaded_file($tmp, $dest)) {
            $skipped[] = "$name: fallo al mover a $dest";
            continue;
          }
        }
      }

      $rel = 'uploads/' . $year . '/' . $month . '/' . $id . '/' . basename($dest);

      $pdo->prepare("
        INSERT INTO protocolo_solicitud_adjuntos (id_solicitud, ruta, nombre_original, tamano, created_at)
        VALUES (?,?,?,?, NOW())
      ")->execute([$id, $rel, $name, $size]);
    }
  }

  $pdo->commit();

  // ===== WhatsApp (fuera de la transacción)
  try {
    $tablaUsuarios = 'usuarios_cache';
    $q = $pdo->prepare("
      SELECT nombre_completo AS nombre, telefono AS tel
      FROM {$tablaUsuarios}
      WHERE UPPER(nombre_completo)=UPPER('PROTOCOLOS / GARANTIAS')
        AND telefono IS NOT NULL AND telefono <> ''
      LIMIT 1
    ");
    $q->execute();
    if ($dest = $q->fetch(PDO::FETCH_ASSOC)) {
      $nombreContacto = $dest['nombre'] ?: 'PROTOCOLOS / GARANTIAS';
      $digits = preg_replace('/\D+/', '', (string)$dest['tel']);
      if (strlen($digits) >= 10) {
        $e164 = (strlen($digits) === 10) ? '+52'.$digits : '+'.$digits;

        $plantilla = 'portal_nuevo_ga';   // ajusta al nombre real
        $idioma    = 'en_US';
        $componentes = [[
          'type'=>'body',
          'parameters'=>[
            ['type'=>'text','text'=>$nombreContacto],
            ['type'=>'text','text'=>$folio],
            ['type'=>'text','text'=>($sucursal!==''?$sucursal:'S/D')],
          ]
        ]];

        wa_send_template($e164, $plantilla, $componentes, $idioma);
        @file_put_contents(__DIR__.'/wa_new_protocolo.log',
          date('c')." $folio -> $e164 [$nombreContacto]\n", FILE_APPEND);
      } else {
        @file_put_contents(__DIR__.'/wa_new_protocolo.log',
          date('c')." $folio: teléfono inválido PROTOCOLOS / GARANTIAS ({$dest['tel']})\n", FILE_APPEND);
      }
    } else {
      @file_put_contents(__DIR__.'/wa_new_protocolo.log',
        date('c')." $folio: no se encontró contacto PROTOCOLOS / GARANTIAS\n", FILE_APPEND);
    }
  } catch (Throwable $we) {
    @file_put_contents(__DIR__.'/wa_new_protocolo.log',
      date('c')." ERROR $folio: ".$we->getMessage()."\n", FILE_APPEND);
    // no interrumpimos la respuesta al cliente
  }

  // ===== Respuesta JSON final
  $notaAdj = !empty($skipped) ? ('Algunos archivos no se guardaron: '.implode(' | ', $skipped)) : '';
  resp(true, [
    'folio' => $folio,
    'row'   => [
      'id' => $id,
      'descripcion' => mb_strlen($desc)>160 ? (mb_substr($desc,0,160).'…') : $desc,
      'estado' => 0,
      'created_at' => date('Y-m-d H:i:s'),
    ],
    'nota_adjuntos' => $notaAdj,
  ]);

} catch (Throwable $e) {
  if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
  http_response_code(400);
  resp(false, ['msg'=>$e->getMessage()]);
}

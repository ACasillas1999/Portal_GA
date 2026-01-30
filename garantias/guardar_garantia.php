<?php
// ===== JSON limpio =====
while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors','0'); error_reporting(E_ALL);
set_error_handler(function($lvl,$msg,$file,$line){ http_response_code(500); echo json_encode(['ok'=>false,'msg'=>"$msg ($file:$line)"]); exit; });
set_exception_handler(function($e){ http_response_code(500); echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); exit; });

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/WhatsBusiness.php'; // funciones WhatsApp

ini_set('session.cookie_httponly','1');
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off') ini_set('session.cookie_secure','0'); else ini_set('session.cookie_secure','1');
session_name('GA'); session_start();

function resp($ok,$a=[]){ echo json_encode(['ok'=>$ok]+$a); exit; }
function digits($s){ return preg_replace('/\D+/','',(string)$s); }

// Identidad
$uid = $_SESSION['user_id'] ?? $_SESSION['ID'] ?? $_SESSION['idUsuario'] ?? $_SESSION['usuario_id'] ?? null;
if (!$uid) resp(false, ['msg'=>'No autenticado']);

// POST
$email            = strtolower(trim($_POST['email'] ?? ''));
$telefono         = digits($_POST['telefono'] ?? '');
$producto         = trim($_POST['producto'] ?? '');
$no_serie         = trim($_POST['no_serie'] ?? '');
$codigo_factura   = trim($_POST['codigo_factura'] ?? '');
$no_factura       = trim($_POST['no_factura'] ?? '');
$fecha_factura    = trim($_POST['fecha_factura'] ?? '');
$marca            = trim($_POST['marca'] ?? '');
$sucursal         = strtolower(trim($_POST['sucursal'] ?? ''));
$descripcion      = trim($_POST['descripcion_falla'] ?? '');
$nombre_contacto  = trim($_POST['nombre_contacto'] ?? '');
$vendedor_id      = isset($_POST['vendedor']) && $_POST['vendedor'] !== '' ? (int)$_POST['vendedor'] : null;

// Validaciones básicas
$err=[];
if (!filter_var($email, FILTER_VALIDATE_EMAIL))                  $err[]='Correo inválido';
if (!preg_match('/^\d{10}$/',$telefono))                         $err[]='Teléfono inválido (10 dígitos)';
if ($producto==='')                                              $err[]='Nombre del producto requerido';
if ($descripcion==='' || mb_strlen($descripcion)<5)              $err[]='Describe la falla con más detalle';
if ($nombre_contacto==='' || mb_strlen($nombre_contacto)<3)      $err[]='Nombre de contacto requerido';
if ($fecha_factura!=='' && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$fecha_factura)) $err[]='Fecha de factura inválida (YYYY-MM-DD)';

$permitidas = ['deasa','tapatia','dimegsa','iluminacion','segsa','fesa','codi','vallarta','queretaro','gabsa','aiesa'];
if (!in_array($sucursal, $permitidas, true)) $err[]='Sucursal inválida';

if ($err) resp(false,['msg'=>implode('. ',$err)]);

// Normaliza sucursal para consultas/WhatsApp
$map = [
  'deasa'=>'DEASA','tapatia'=>'TAPATIA','dimegsa'=>'DIMEGSA','iluminacion'=>'ILUMINACION',
  'segsa'=>'SEGSA','fesa'=>'FESA','codi'=>'CODI','vallarta'=>'VALLARTA',
  'queretaro'=>'QUERETARO','gabsa'=>'GABSA','aiesa'=>'AIESA'
];
$sucUpper = $map[$sucursal] ?? strtoupper($sucursal);

// ¿La sucursal tiene vendedores? → entonces vendedor_id es obligatorio
$stCnt = $pdo->prepare("SELECT COUNT(*) FROM usuarios_cache WHERE TRIM(UPPER(sucursal)) = TRIM(UPPER(:s))");
$stCnt->execute([':s'=>$sucUpper]);
$tieneVendedores = (int)$stCnt->fetchColumn() > 0;

if ($tieneVendedores && !$vendedor_id) {
  resp(false, ['msg'=>'Debes seleccionar el vendedor que te atendió.']);
}

// Si hay vendedor, valida que pertenezca a la sucursal y toma sus datos
$destino = null;
if ($vendedor_id) {
  $q = $pdo->prepare("
    SELECT COALESCE(NULLIF(nombre_factura,''), NULLIF(nombre_completo,'')) AS nombre,
           telefono AS tel
    FROM usuarios_cache
    WHERE id_usuario = :id
      AND TRIM(UPPER(sucursal)) = TRIM(UPPER(:suc))
    LIMIT 1
  ");
  $q->execute([':id'=>$vendedor_id, ':suc'=>$sucUpper]);
  $destino = $q->fetch(PDO::FETCH_ASSOC);
  if (!$destino) {
    resp(false, ['msg'=>'El vendedor seleccionado no pertenece a la sucursal.']);
  }
}

// Detecta si existe columna vendedor_id en garantia_solicitudes
$hasVendedorCol = false;
try {
  $chk = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
                        WHERE TABLE_SCHEMA = DATABASE()
                          AND TABLE_NAME = 'garantia_solicitudes'
                          AND COLUMN_NAME = 'vendedor_id'");
  $chk->execute();
  $hasVendedorCol = (bool)$chk->fetchColumn();
} catch (Throwable $e) { /* no-op */ }

try{
  $pdo->beginTransaction();

  // Inserta base con folio temporal
  if ($hasVendedorCol) {
    $sql = "INSERT INTO garantia_solicitudes
      (folio, id_usuario, email, telefono, producto, no_serie, codigo_factura, no_factura,
       fecha_factura, marca, sucursal, descripcion_falla, nombre_contacto, estado, vendedor_id, created_at)
      VALUES
      ('TEMP', :uid, :email, :tel, :prod, :serie, :cod, :nfact, :ffact, :marca, :sucursal, :desc, :nomc, 'Recibida', :vend, NOW())";
    $params = [
      ':uid'=>$uid, ':email'=>$email, ':tel'=>$telefono, ':prod'=>$producto, ':serie'=>($no_serie!==''?$no_serie:null),
      ':cod'=>($codigo_factura!==''?$codigo_factura:null), ':nfact'=>($no_factura!==''?$no_factura:null),
      ':ffact'=>($fecha_factura!==''?$fecha_factura:null), ':marca'=>($marca!==''?$marca:null),
      ':sucursal'=>($sucursal!==''?$sucursal:null), ':desc'=>$descripcion, ':nomc'=>$nombre_contacto,
      ':vend'=>$vendedor_id
    ];
  } else {
    $sql = "INSERT INTO garantia_solicitudes
      (folio, id_usuario, email, telefono, producto, no_serie, codigo_factura, no_factura,
       fecha_factura, marca, sucursal, descripcion_falla, nombre_contacto, estado, created_at)
      VALUES
      ('TEMP', :uid, :email, :tel, :prod, :serie, :cod, :nfact, :ffact, :marca, :sucursal, :desc, :nomc, 'Recibida', NOW())";
    $params = [
      ':uid'=>$uid, ':email'=>$email, ':tel'=>$telefono, ':prod'=>$producto, ':serie'=>($no_serie!==''?$no_serie:null),
      ':cod'=>($codigo_factura!==''?$codigo_factura:null), ':nfact'=>($no_factura!==''?$no_factura:null),
      ':ffact'=>($fecha_factura!==''?$fecha_factura:null), ':marca'=>($marca!==''?$marca:null),
      ':sucursal'=>($sucursal!==''?$sucursal:null), ':desc'=>$descripcion, ':nomc'=>$nombre_contacto
    ];
  }

  $st = $pdo->prepare($sql);
  $st->execute($params);

  $id = (int)$pdo->lastInsertId();
  if ($id <= 0) throw new RuntimeException('No se obtuvo el ID.');

  // Folio definitivo GAR-YYYY-000001
  $folio = sprintf('GAR-%s-%06d', date('Y'), $id);
  $pdo->prepare("UPDATE garantia_solicitudes SET folio=? WHERE id=?")->execute([$folio,$id]);

  // Adjuntos (igual que antes)
  if (!empty($_FILES['adjuntos']['name'][0])) {
    $baseDir = __DIR__ . '/uploads/' . date('Y/m');
    if (!is_dir($baseDir)) @mkdir($baseDir, 0775, true);
    $okExt = ['jpg','jpeg','png','gif','webp','mp4','mov','avi','mkv','webm','pdf','zip'];
    $f = $_FILES['adjuntos'];
    for ($i=0; $i<count($f['name']); $i++){
      if ($f['error'][$i] !== UPLOAD_ERR_OK) continue;
      if ($f['size'][$i] > 40*1024*1024) continue; // 40MB

      $ext = strtolower(pathinfo($f['name'][$i], PATHINFO_EXTENSION));
      if (!in_array($ext,$okExt,true)) continue;

      $safe = preg_replace('/[^a-z0-9\.\-\_]+/i','_', $f['name'][$i]);
      $dest = $baseDir . '/' . $id . '_' . uniqid('',true) . '_' . $safe;
      if (move_uploaded_file($f['tmp_name'][$i], $dest)) {
        $rel = 'uploads/' . date('Y/m') . '/' . basename($dest);
        $pdo->prepare("INSERT INTO garantia_adjuntos (id_solicitud, ruta, nombre_original, tamano) VALUES (?,?,?,?)")
            ->execute([$id, $rel, $f['name'][$i], (int)$f['size'][$i]]);
      }
    }
  }

  $pdo->commit();

  // ====== WhatsApp SOLO al vendedor elegido ======
  try {
    if ($vendedor_id && $destino) {
      $nombre = $destino['nombre'] ?: 'Vendedor';
      $telRaw = (string)($destino['tel'] ?? '');
      $digits = preg_replace('/\D+/', '', $telRaw);
      if ($digits !== '' && strlen($digits) >= 10) {
        $e164 = (strlen($digits) === 10) ? '+52'.$digits : '+'.$digits;

        $plantilla = 'portal_nuevo_ga'; // cambia si tienes otra para garantías
        $idioma    = 'en_US';
        $componentes = [[
          'type' => 'body',
          'parameters' => [
            ['type'=>'text','text'=> $nombre],
            ['type'=>'text','text'=> $folio],
            ['type'=>'text','text'=> $sucUpper],
          ]
        ]];

        wa_send_template($e164, $plantilla, $componentes, $idioma);
        file_put_contents(__DIR__.'/wa_new_garantia.log', date('c')." $folio->$e164 [$nombre]\n", FILE_APPEND);
      } else {
        file_put_contents(__DIR__.'/wa_new_garantia.log', date('c')." $folio: vendedor sin teléfono válido\n", FILE_APPEND);
      }
    } else {
      // No hay vendedor seleccionado (o no aplica) → no se envía a nadie
      file_put_contents(__DIR__.'/wa_new_garantia.log', date('c')." $folio: sin vendedor; no se envía WA\n", FILE_APPEND);
    }
  } catch (Throwable $e) {
    file_put_contents(__DIR__.'/wa_new_garantia.log', date('c')." ERROR $folio: ".$e->getMessage()."\n", FILE_APPEND);
  }

  // Respuesta final al cliente
  resp(true, ['folio'=>$folio, 'id'=>$id, 'sucursal'=>$sucursal, 'vendedor_id'=>$vendedor_id]);

} catch(Throwable $e){
  if ($pdo->inTransaction()) $pdo->rollBack();
  resp(false,['msg'=>$e->getMessage()]);
}

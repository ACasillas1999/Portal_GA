<?php
// /protocolos/subir_adjunto_protocolo.php
declare(strict_types=1);
date_default_timezone_set('America/Mexico_City');

/* =========================
   Sesión + CORS + Seguridad
   ========================= */
session_name('SEGAOC');
ini_set('session.cookie_httponly','1');
ini_set('session.cookie_secure','1');      // HTTPS en el subdominio
ini_set('session.cookie_samesite','None'); // permitir cross-site
session_start();

// CORS: sólo orígenes conocidos
$allowedOrigins = [
  'https://clientes.grupoascencio.com.mx',
  'http://192.168.60.194',
  'http://192.168.80.194',
  'http://localhost', 'http://127.0.0.1',
  'http://localhost:8080', 'http://localhost:80',
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
  header('Access-Control-Allow-Origin: '.$origin);
  header('Vary: Origin');
  header('Access-Control-Allow-Credentials: true');
  header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
  header('Access-Control-Allow-Methods: POST, OPTIONS');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

// Host esperado
$allowedHost = 'clientes.grupoascencio.com.mx';
$reqHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
if (strcasecmp($reqHost, $allowedHost) !== 0) {
  http_response_code(403);
  header('Content-Type: application/json; charset=UTF-8');
  echo json_encode(['ok'=>false,'msg'=>'Host no permitido']);
  exit;
}

header('Content-Type: application/json; charset=UTF-8');

/* =========================
   Autenticación (sesión o token)
   ========================= */
// Secreto compartido: MISMO que en protocolo_detalle.php
if (!defined('UPLOAD_SHARED_SECRET')) {
  define('UPLOAD_SHARED_SECRET', 'c3b9f0a0a3a7f2d5b1c2e4f8a9d0e1f2c4b6d8e0f1a2b3c4d5e6f70890ab12cd');
}

$authOK   = false;
$authMode = '';

// 1) Sesión del subdominio
$logged = ($_SESSION['loggedin'] ?? $_SESSION['logged'] ?? false) === true;
if ($logged) {
  $rolUP = strtoupper((string)($_SESSION['Rol'] ?? $_SESSION['rol'] ?? ''));
  $rolesPermitidos = ['ADMIN','AUXILIAR_O','AUXILIAR_S','VENDEDOR','GERENTE','JEFE_ALMACEN','ENTRADAS','COORDINADOR','TICKETS','TEMPORAL'];
  if (in_array($rolUP, $rolesPermitidos, true)) {
    $authOK = true;
    $authMode = 'session';
  }
}

// 2) Token HMAC (cuando no viaja la cookie)
if (!$authOK) {
  $tok = (string)($_POST['up_token'] ?? '');
  if ($tok && strpos($tok, '.') !== false) {
    [$b64, $sig] = explode('.', $tok, 2);
    $b64std = strtr($b64, '-_', '+/');
    $b64std .= str_repeat('=', (4 - strlen($b64std) % 4) % 4);
    $json = base64_decode($b64std, true);
    if ($json !== false) {
      $calc = hash_hmac('sha256', $json, UPLOAD_SHARED_SECRET);
      if (hash_equals($calc, $sig)) {
        $payload = json_decode($json, true);
        $now = time();
        if (is_array($payload) && isset($payload['sid'], $payload['ts']) && ($now - (int)$payload['ts']) <= 600) {
          $postSid = isset($_POST['id_solicitud']) ? (int)$_POST['id_solicitud'] : 0;
          if ($postSid > 0 && $postSid === (int)$payload['sid']) {
            $authOK = true;
            $authMode = 'token';
          }
        }
      }
    }
  }
}

if (!$authOK) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit;
}

/* =========================
   Config + DB
   ========================= */
// Carga SOLO config.php del docroot
$cfgPath = dirname(__DIR__) . '/config.php';
if (!is_file($cfgPath)) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'No se encontró config.php','path'=>$cfgPath]); exit;
}
require_once $cfgPath;

// Helper local para obtener PDO (usa las constantes de config.php)
if (!function_exists('db')) {
  function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $opts = [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, $opts);
    return $pdo;
  }
}

$pdo = db();

/* =========================
   Rutas de publicación
   ========================= */
if (!defined('PROTO_PUBLIC_BASE')) {
  // Debe incluir /protocolos/ porque ahí quedarán los archivos
  define('PROTO_PUBLIC_BASE', 'https://clientes.grupoascencio.com.mx/protocolos/');
}
if (!defined('PROTO_UPLOAD_ROOT')) {
  // Carpeta física donde guardar: <docroot>/protocolos
  define('PROTO_UPLOAD_ROOT', rtrim($_SERVER['DOCUMENT_ROOT'], '/').'/protocolos');
}

// Verificación de carpeta base
if (!is_dir(PROTO_UPLOAD_ROOT)) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'PROTO_UPLOAD_ROOT no existe','root'=>PROTO_UPLOAD_ROOT]); exit;
}
if (!is_writable(PROTO_UPLOAD_ROOT)) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'PROTO_UPLOAD_ROOT sin permisos de escritura','root'=>PROTO_UPLOAD_ROOT]); exit;
}

// Helpers
function proto_public_url(string $rutaBD): string {
  $p = ltrim(trim($rutaBD),'/');
  return rtrim(PROTO_PUBLIC_BASE,'/') . '/' . $p;
}
function slugify_name(string $name): string {
  $name = preg_replace('/[^\w\.\-]+/u','_', $name);
  return trim($name,'_');
}

/* =========================
   Inputs y validaciones
   ========================= */
$id_solicitud = isset($_POST['id_solicitud']) ? (int)$_POST['id_solicitud'] : 0;
if ($id_solicitud <= 0) { http_response_code(422); echo json_encode(['ok'=>false,'msg'=>'id_solicitud inválido']); exit; }

// CSRF sólo cuando hay sesión
if ($authMode === 'session') {
  $csrf = (string)($_POST['csrf'] ?? '');
  if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'CSRF inválido']); exit;
  }
}

if (!isset($_FILES['archivo']) || !is_uploaded_file($_FILES['archivo']['tmp_name'])) {
  http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'No se recibió archivo']); exit;
}

// Archivo
$f = $_FILES['archivo'];
$maxBytes = 30 * 1024 * 1024; // 30MB
if ($f['size'] <= 0 || $f['size'] > $maxBytes) {
  http_response_code(413); echo json_encode(['ok'=>false,'msg'=>'Tamaño inválido (máx 30MB)']); exit;
}
$orig = $f['name'] ?? 'archivo';
$ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
$permitidas = [
  'jpg','jpeg','png','gif','webp','bmp','svg',
  'pdf','mp4','mov','avi','mkv','webm','m4v',
  'txt','doc','docx','xls','xlsx','csv','ppt','pptx'
];
if ($ext === '' || !in_array($ext, $permitidas, true)) {
  http_response_code(415); echo json_encode(['ok'=>false,'msg'=>'Extensión no permitida']); exit;
}

/* =========================
   Guardado en disco + BD
   ========================= */
$sub = 'uploads/' . date('Y/m/d'); // ruta relativa para BD/URL
$destDir = rtrim(PROTO_UPLOAD_ROOT, '/') . '/' . $sub;
if (!is_dir($destDir)) {
  if (!mkdir($destDir, 0755, true) && !is_dir($destDir)) {
    http_response_code(500); echo json_encode(['ok'=>false,'msg'=>'No se pudo crear carpeta destino','dir'=>$destDir]); exit;
  }
}

$base = slugify_name(pathinfo($orig, PATHINFO_FILENAME));
$final = date('H_i_s') . '_' . bin2hex(random_bytes(6)) . '_' . $base . '.' . $ext;
$destPath = $destDir . '/' . $final;

if (!move_uploaded_file($f['tmp_name'], $destPath)) {
  http_response_code(500); echo json_encode(['ok'=>false,'msg'=>'No se pudo mover el archivo']); exit;
}
@chmod($destPath, 0644);

// Guardar en BD
$rutaBD = $sub . '/' . $final;
$st = $pdo->prepare("
  INSERT INTO protocolo_solicitud_adjuntos (id_solicitud, ruta, nombre_original, tamano, created_at)
  VALUES (?, ?, ?, ?, NOW())
");
$st->execute([$id_solicitud, $rutaBD, $orig, (int)$f['size']]);

$idAdj = (int)$pdo->lastInsertId();
$urlPublica = proto_public_url($rutaBD);

// Respuesta
echo json_encode([
  'ok'    => true,
  'id'    => $idAdj,
  'ruta'  => $rutaBD,
  'url'   => $urlPublica,
  'nombre_original' => $orig,
  'tamano' => (int)$f['size'],
  'fs_path' => $destPath,
  'root'    => PROTO_UPLOAD_ROOT,
  'mode'    => $authMode,
]);

<?php
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/config.php';

/* === Sesión consistente con el portal === */
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? '1' : '0');
session_name('GA');
session_start();

header('Content-Type: application/json; charset=UTF-8');

/* === Helper Turnstile === */
function verificar_turnstile(string $token, ?string $ip = null): bool {
  // Define en config.php: define('TURNSTILE_SECRET', '0x4AAAAA...'); (tu clave privada)
  $secret = defined('TURNSTILE_SECRET') ? TURNSTILE_SECRET : '';
  if ($token === '' || $secret === '') return false;

  $post = http_build_query([
    'secret'   => $secret,
    'response' => $token,
    'remoteip' => $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '')
  ]);

  $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
  curl_setopt_array($ch, [
    CURLOPT_POST            => true,
    CURLOPT_POSTFIELDS      => $post,
    CURLOPT_RETURNTRANSFER  => true,
    CURLOPT_TIMEOUT         => 8,
  ]);
  $res = curl_exec($ch);
  $err = curl_error($ch);
  curl_close($ch);

  if ($err || !$res) return false;
  $data = json_decode($res, true);
  if (empty($data['success'])) return false;

  // Endurecer (opcional):
  if (!empty($data['action']) && $data['action'] !== 'login') return false;
  if (!empty($data['hostname'])) {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (stripos($host, $data['hostname']) === false) return false;
  }
  return true;
}

try {
  $in = json_decode(file_get_contents('php://input'), true);
  if (!is_array($in)) throw new Exception('JSON inválido');

  $email = strtolower(trim($in['email'] ?? ''));
  $pass  = (string)($in['password'] ?? '');
  $tsTok = (string)($in['cf_turnstile_token'] ?? '');

  // 1) Validaciones básicas
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { echo json_encode(['ok'=>false,'msg'=>'Email inválido']); exit; }
  if ($pass === '') { echo json_encode(['ok'=>false,'msg'=>'Contraseña requerida']); exit; }

  // 2) Verificación Turnstile (antes de consultar credenciales)
  if (!verificar_turnstile($tsTok)) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'msg'=>'Captcha inválido o expirado.']); exit;
  }

  // 3) Autenticación
  $q = $pdo->prepare('SELECT id, username, email, password, email_verified_at FROM usuarios WHERE email = ? LIMIT 1');
  $q->execute([$email]);
  $u = $q->fetch();

  if (!$u || !password_verify($pass, $u['password'])) {
    echo json_encode(['ok'=>false,'msg'=>'Credenciales inválidas']); exit;
  }

  // 4) Verificación de correo (si aplica)
  if (empty($u['email_verified_at'])) {
    echo json_encode(['ok'=>false,'msg'=>'Tu correo no está verificado. ¿Quieres reenviar el enlace?', 'needVerify'=>true]); exit;
  }

  // 5) Sesión
  session_regenerate_id(true);               // anti fijación de sesión
  $_SESSION['loggedin']      = true;
  $_SESSION['user_id']       = (int)$u['id'];
  $_SESSION['username']      = $u['username'];
  $_SESSION['Email']         = $u['email'];
  $_SESSION['Rol']           = $_SESSION['Rol'] ?? 'Cliente';
  $_SESSION['login_time']    = time();
  $_SESSION['last_activity'] = time();
  $_SESSION['last_regen']    = time();

  echo json_encode([
    'ok' => true,
    'user' => ['id'=>(int)$u['id'], 'username'=>$u['username']]
  ]);

} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'msg'=>'Error: '.$e->getMessage()]);
}

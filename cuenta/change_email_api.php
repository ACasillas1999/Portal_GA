<?php
// cuenta/change_email_api.php
declare(strict_types=1);

session_name('GA');
session_set_cookie_params([
  'path' => '/',
  'httponly' => true,
  'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
  'samesite' => 'Lax',
]);
session_start();

while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors','0'); error_reporting(E_ALL);

set_error_handler(function($l,$m){ http_response_code(500); echo json_encode(['ok'=>false,'msg'=>"PHP: $m"]); exit; });
set_exception_handler(function($e){ echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); exit; });

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/mailer.php';

try{
  $userId = (int)($_SESSION['user_id'] ?? 0);
  if ($userId <= 0) { http_response_code(401); throw new Exception('No autenticado'); }

  $in = json_decode(file_get_contents('php://input') ?: '[]', true);
  if (!is_array($in)) { http_response_code(400); throw new Exception('JSON inválido'); }

  $email = strtolower(trim((string)($in['email'] ?? '')));
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { http_response_code(422); throw new Exception('Correo inválido'); }

  // Correo actual
  $cur = $pdo->prepare('SELECT username, email FROM usuarios WHERE ID=? LIMIT 1');
  $cur->execute([$userId]);
  $u = $cur->fetch(PDO::FETCH_ASSOC);
  if (!$u) { http_response_code(404); throw new Exception('Usuario no encontrado'); }
  if ($email === strtolower((string)$u['email'])) { http_response_code(400); throw new Exception('Es el mismo correo actual'); }

  // Único (excluye al propio usuario)
  $ch = $pdo->prepare('SELECT ID FROM usuarios WHERE email=? AND ID<>? LIMIT 1');
  $ch->execute([$email, $userId]);
  if ($ch->fetch()) { http_response_code(409); throw new Exception('Ya existe una cuenta con ese correo'); }

  // Token de verificación
  $token   = bin2hex(random_bytes(32));
  $hashBin = hash('sha256', $token, true); // guarda BINARIO en email_verify_token_hash
  $expira  = (new DateTime('+1 day'))->format('Y-m-d H:i:s');

  $up = $pdo->prepare('
    UPDATE usuarios
       SET email=?,
           email_verified_at=NULL,
           email_verify_token_hash=?,
           email_verify_expires=?,
           email_verify_last_sent=NOW(),
           email_verify_send_count=COALESCE(email_verify_send_count,0)+1,
           updated_at=NOW()
     WHERE ID=?
  ');
  // bind para varbinary
  $up->bindParam(1, $email);
  $up->bindParam(2, $hashBin, PDO::PARAM_LOB);
  $up->bindParam(3, $expira);
  $up->bindParam(4, $userId, PDO::PARAM_INT);
  $up->execute();

  $verifyUrl = rtrim(APP_URL, '/') . '/auth/verificar.php?token=' . urlencode($token) . '&email=' . urlencode($email);

  // Envía mail
  $env = enviarCorreoVerificacion($email, $u['username'] ?: 'Usuario', $verifyUrl);
  if (empty($env['ok'])) {
    http_response_code(500);
    throw new Exception('Se cambió el correo, pero falló el envío de verificación.');
  }

  echo json_encode(['ok'=>true,'msg'=>'Te enviamos un enlace para verificar tu nuevo correo.']);
} catch (Throwable $e){
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}

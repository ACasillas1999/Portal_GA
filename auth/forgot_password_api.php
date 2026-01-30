<?php
// auth/forgot_password_api.php
while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors','0'); error_reporting(E_ALL);

set_error_handler(function($l,$m){ echo json_encode(['ok'=>false,'msg'=>"PHP: $m"]); exit; });
set_exception_handler(function($e){ echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); exit; });

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/mailer.php';

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Método no permitido');
  $in = json_decode(file_get_contents('php://input'), true);
  if (!is_array($in)) throw new Exception('JSON inválido');
  $email = strtolower(trim($in['email'] ?? ''));
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Correo inválido');

  // Busca el usuario
  $st = $pdo->prepare('SELECT id, username, email_verified_at FROM usuarios WHERE email = ? LIMIT 1');
  $st->execute([$email]);
  $u = $st->fetch();

  // Respuesta genérica si no existe (no revelar)
  if (!$u) {
    echo json_encode(['ok'=>true,'msg'=>'Si la cuenta existe, te enviaremos un enlace para restablecer.']);
    exit;
  }

  // (Opcional) Solo permitir si ya verificó correo
 if (empty($u['email_verified_at'])) { throw new Exception('Tu cuenta aún no está verificada.'); }

  // Genera token (1 hora)
  $token   = bin2hex(random_bytes(16));
  $hashBin = hash('sha256', $token, true);
  $expira  = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

  $up = $pdo->prepare('UPDATE usuarios SET reset_token_hash = ?, reset_expires = ? WHERE id = ?');
  $up->bindParam(1, $hashBin, PDO::PARAM_LOB);
  $up->bindParam(2, $expira);
  $up->bindParam(3, $u['id'], PDO::PARAM_INT);
  $up->execute();

  $resetUrl = APP_URL . '/auth/reset.php?token=' . urlencode($token) . '&email=' . urlencode($email);
  $env = enviarCorreoReset($email, $u['username'] ?: 'Usuario', $resetUrl);

  if (!$env['ok']) throw new Exception('No se pudo enviar el correo. '.($env['error'] ?? ''));

  echo json_encode(['ok'=>true,'msg'=>'Si la cuenta existe, te enviamos un enlace para restablecer. Revisa tu correo.']);
  exit;

} catch (Throwable $e) {
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
  exit;
}

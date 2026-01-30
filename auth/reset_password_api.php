<?php
// auth/reset_password_api.php
while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors','0'); error_reporting(E_ALL);

set_error_handler(function($l,$m){ echo json_encode(['ok'=>false,'msg'=>"PHP: $m"]); exit; });
set_exception_handler(function($e){ echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); exit; });

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/db.php';

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Método no permitido');
  $in = json_decode(file_get_contents('php://input'), true);
  if (!is_array($in)) throw new Exception('JSON inválido');

  $email = strtolower(trim($in['email'] ?? ''));
  $token = $in['token'] ?? '';
  $pass  = (string)($in['password'] ?? '');

  if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !$token) throw new Exception('Datos inválidos');
  if (strlen($pass) < 6) throw new Exception('La contraseña debe tener al menos 6 caracteres');

  $st = $pdo->prepare('SELECT id, reset_token_hash, reset_expires FROM usuarios WHERE email = ? LIMIT 1');
  $st->execute([$email]);
  $u = $st->fetch();
  if (!$u) throw new Exception('Enlace inválido.');

  if (empty($u['reset_expires']) || strtotime($u['reset_expires']) < time()) {
    throw new Exception('El enlace ha expirado. Solicita uno nuevo.');
  }

  $hashBin = hash('sha256', $token, true);
  if (!is_string($u['reset_token_hash']) || !hash_equals($u['reset_token_hash'], $hashBin)) {
    throw new Exception('Token inválido.');
  }

  $newHash = password_hash($pass, PASSWORD_DEFAULT);

  $up = $pdo->prepare('
    UPDATE usuarios
       SET password = ?, reset_token_hash = NULL, reset_expires = NULL, password_changed_at = NOW()
     WHERE id = ?
  ');
  $up->execute([$newHash, $u['id']]);

  echo json_encode(['ok'=>true,'msg'=>'Tu contraseña se actualizó correctamente.']);
  exit;

} catch (Throwable $e) {
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
  exit;
}

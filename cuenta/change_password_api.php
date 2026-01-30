<?php
// cuenta/change_password_api.php
declare(strict_types=1);

session_name('GA'); // <-- MISMO nombre que en ajustes.php
ini_set('session.cookie_httponly','1');
ini_set('session.cookie_secure', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? '1' : '0');
ini_set('session.cookie_samesite','Lax'); // cookie se envía en peticiones same-site (AJAX)
session_start();

while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors','0'); error_reporting(E_ALL);

set_error_handler(function($l,$m){ echo json_encode(['ok'=>false,'msg'=>"PHP: $m"]); exit; });
set_exception_handler(function($e){ echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); exit; });

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/db.php';

try{
  $userId = (int)($_SESSION['user_id'] ?? 0);
  if ($userId <= 0) throw new Exception('No autenticado');

  $in = json_decode(file_get_contents('php://input'), true);
  if (!is_array($in)) throw new Exception('JSON inválido');

  $cur = (string)($in['current_password'] ?? '');
  $new = (string)($in['new_password'] ?? '');
  if (strlen($new) < 6) throw new Exception('La contraseña nueva es muy corta');

  // leer hash (soporta 'password' o 'contraseña')
$st = $pdo->prepare('SELECT ID, password FROM usuarios WHERE ID=? LIMIT 1');
$st->execute([$userId]);
$u = $st->fetch();
if (!$u) throw new Exception('Usuario no encontrado');

if (!password_verify($cur, (string)$u['password']))
  throw new Exception('La contraseña actual no es correcta');

$newHash = password_hash($new, PASSWORD_DEFAULT);
$up = $pdo->prepare('
  UPDATE usuarios
  SET password=?, password_changed_at=NOW(),
      reset_token_hash=NULL, reset_expires=NULL
  WHERE ID=?
');
$up->execute([$newHash, $userId]);
echo json_encode(['ok'=>true,'msg'=>'Contraseña actualizada']);
} catch (Throwable $e){
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}

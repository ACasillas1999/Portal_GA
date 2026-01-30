<?php
// cuenta/update_profile_api.php
declare(strict_types=1);

session_name('GA'); // mismo nombre que en ajustes.php
session_set_cookie_params([
  'path'     => '/',
  'httponly' => true,
  'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off'),
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

try{
  $userId = (int)($_SESSION['user_id'] ?? 0);
  if ($userId <= 0) { http_response_code(401); throw new Exception('No autenticado'); }

  $inRaw = file_get_contents('php://input');
  $in    = json_decode($inRaw ?: '[]', true);
  if (!is_array($in)) { http_response_code(400); throw new Exception('JSON inválido'); }

  $username = trim((string)($in['username'] ?? ''));
  $RFC      = strtoupper(trim((string)($in['RFC'] ?? '')));
  $Telefono = preg_replace('/\D+/', '', (string)($in['Telefono'] ?? ''));

  $err=[];
  if (mb_strlen($username) < 3) $err[] = 'Usuario muy corto';
  if (!preg_match('/^([A-ZÑ&]{3,4})\d{6}([A-Z0-9]{2,3})$/', $RFC)) $err[]='RFC inválido';
  if (!preg_match('/^\d{10}$/', $Telefono)) $err[]='Teléfono inválido';
  if ($err) { http_response_code(422); echo json_encode(['ok'=>false,'msg'=>implode('. ',$err)]); exit; }

  // OJO: tu PK es "ID" (mayúsculas)
  $st = $pdo->prepare('UPDATE usuarios SET username=?, RFC=?, Telefono=?, updated_at=NOW() WHERE ID=?');
  $st->execute([$username, $RFC, $Telefono, $userId]);

  echo json_encode(['ok'=>true,'msg'=>'Perfil actualizado']);
} catch (Throwable $e){
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}

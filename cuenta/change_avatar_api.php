<?php
// cuenta/change_avatar_api.php
ini_set('session.cookie_httponly', '1');
session_name('GA');
session_start();

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__.'/../config.php';
require_once __DIR__.'/../app/db.php'; // $pdo

function out($arr, $code=200){ http_response_code($code); echo json_encode($arr); exit; }

if (empty($_SESSION['user_id'])) out(['ok'=>false,'msg'=>'AUTH'], 401);

// Lee JSON del body
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
$pick = isset($data['avatar_file']) ? (string)$data['avatar_file'] : '';
$pick = basename($pick); // evita rutas

if ($pick === '') out(['ok'=>false,'msg'=>'FALTA_AVATAR'], 400);

// Mismo listado que usas en el front:
$allowed = ['iconAvatar1.png','iconAvatar2.png','iconAvatar3.png','iconAvatar4.png','iconAvatar5.png'];

// Valida que esté permitido
if (!in_array($pick, $allowed, true)) out(['ok'=>false,'msg'=>'NO_PERMITIDO'], 400);

// Valida que exista físicamente
$baseDir = realpath(__DIR__ . '/../assets/avatars');
if (!$baseDir) out(['ok'=>false,'msg'=>'DIR_AVATARS_NO_ENCONTRADO'], 500);

$abs = $baseDir . DIRECTORY_SEPARATOR . $pick;
if (!is_file($abs)) out(['ok'=>false,'msg'=>'ARCHIVO_NO_EXISTE'], 400);

// Guarda SOLO el nombre en BD (tal como lo lees en ajustes.php)
$st = $pdo->prepare('UPDATE usuarios SET avatar = :a, updated_at = NOW() WHERE id = :id');
$st->execute([':a'=>$pick, ':id'=>$_SESSION['user_id']]);

$url = APP_URL . '/assets/avatars/' . rawurlencode($pick);
out(['ok'=>true, 'url'=>$url]);

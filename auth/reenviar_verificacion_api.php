<?php
// auth/reenviar_verificacion_api.php
// API JSON para reenviar enlace de verificación

while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Handlers → siempre responder JSON
set_error_handler(function($lvl,$msg,$file,$line){
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'PHP','msg'=>"$msg ($file:$line)"]);
  exit;
});
set_exception_handler(function($e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'EXC','msg'=>$e->getMessage()]);
  exit;
});

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/mailer.php';

try {
  // Solo POST JSON
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'msg'=>'Método no permitido']);
    exit;
  }

  $raw = file_get_contents('php://input');
  $in  = json_decode($raw, true);
  if (!is_array($in)) {
    throw new Exception('JSON inválido');
  }
  $email = strtolower(trim($in['email'] ?? ''));
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    throw new Exception('Correo inválido');
  }

  // Buscar usuario
  $st = $pdo->prepare('SELECT id, username, email_verified_at, email_verify_token_hash, email_verify_expires, email_verify_last_sent, email_verify_send_count FROM usuarios WHERE email = ? LIMIT 1');
  $st->execute([$email]);
  $u = $st->fetch();
  if (!$u) {
    // No reveles si existe o no: responde genérico
    echo json_encode(['ok'=>true, 'msg'=>'Si la cuenta existe, hemos enviado un nuevo enlace.']);
    exit;
  }

  // Si ya está verificado, no tiene caso reenviar
  if (!empty($u['email_verified_at'])) {
    echo json_encode(['ok'=>false, 'msg'=>'Esta cuenta ya está verificada.']);
    exit;
  }

  // (Opcional) Rate limit: no más de 1 por minuto
  $USE_RATE_LIMIT = true; // pon false si no agregaste columnas
  if ($USE_RATE_LIMIT && !empty($u['email_verify_last_sent'])) {
    $last = strtotime($u['email_verify_last_sent']);
    if ($last && (time() - $last) < 60) {
      $wait = 60 - (time() - $last);
      echo json_encode(['ok'=>false, 'msg'=>"Espera {$wait}s para reenviar de nuevo."]);
      exit;
    }
  }

  // ¿Token vigente o generamos uno nuevo?
  $needNewToken = true;
  if (!empty($u['email_verify_token_hash']) && !empty($u['email_verify_expires'])) {
    if (strtotime($u['email_verify_expires']) > time()) {
      // hay token vigente; lo reutilizamos (opcional)
      $needNewToken = false;
    }
  }

  if ($needNewToken) {
    $token   = bin2hex(random_bytes(16));
    $hashBin = hash('sha256', $token, true);
    $expira  = (new DateTime('+1 day'))->format('Y-m-d H:i:s');

    $up = $pdo->prepare('
      UPDATE usuarios
         SET email_verify_token_hash = ?, email_verify_expires = ?
       WHERE id = ?
    ');
    $up->bindParam(1, $hashBin, PDO::PARAM_LOB);
    $up->bindParam(2, $expira);
    $up->bindParam(3, $u['id'], PDO::PARAM_INT);
    $up->execute();
  } else {
    // Si reusamos token vigente, hay que reconstruirlo para el link…
    // PERO no tenemos el token en texto plano (guardamos solo hash).
    // Por seguridad, lo mejor es SIEMPRE crear uno nuevo:
    $token   = bin2hex(random_bytes(16));
    $hashBin = hash('sha256', $token, true);
    $expira  = (new DateTime('+1 day'))->format('Y-m-d H:i:s');

    $up = $pdo->prepare('
      UPDATE usuarios
         SET email_verify_token_hash = ?, email_verify_expires = ?
       WHERE id = ?
    ');
    $up->bindParam(1, $hashBin, PDO::PARAM_LOB);
    $up->bindParam(2, $expira);
    $up->bindParam(3, $u['id'], PDO::PARAM_INT);
    $up->execute();
  }

  // Armamos URL y mandamos correo
  $token = $token ?? bin2hex(random_bytes(16)); // por si acaso
  $verifyUrl = APP_URL . '/auth/verificar.php?token=' . urlencode($token) . '&email=' . urlencode($email);

  $env = enviarCorreoVerificacion($email, $u['username'] ?: 'Usuario', $verifyUrl);
  if (!$env['ok']) {
    throw new Exception('No se pudo enviar el correo. ' . ($env['error'] ?? ''));
  }

  // Actualiza rate-limit (opcional)
  if ($USE_RATE_LIMIT) {
    $updRL = $pdo->prepare('UPDATE usuarios SET email_verify_last_sent = NOW(), email_verify_send_count = email_verify_send_count + 1 WHERE id = ?');
    $updRL->execute([$u['id']]);
  }

  echo json_encode(['ok'=>true, 'msg'=>'Hemos reenviado el enlace de verificación. Revisa tu correo (y Spam).']);
  exit;

} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok'=>false, 'msg'=>$e->getMessage()]);
  exit;
}

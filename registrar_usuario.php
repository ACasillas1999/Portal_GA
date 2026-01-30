<?php
// ===== Salida JSON limpia =====
while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Handlers: cualquier warning/exception => JSON
set_error_handler(function($lvl,$msg,$file,$line){
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'PHP','msg'=>"$msg ($file:$line)"]);
  exit;
});
set_exception_handler(function($e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'EXCEPTION','msg'=>$e->getMessage()]);
  exit;
});

// ===== Includes (solo una vez) =====
require_once __DIR__ . '/config.php';     // define APP_URL, SMTP_*, CAFILE_PATH, etc.
require_once __DIR__ . '/app/db.php';     // define $pdo (PDO)
require_once __DIR__ . '/app/mailer.php'; // define enviarCorreoVerificacion($to,$name,$url)

// ===== Lógica =====
try {
  $raw = file_get_contents('php://input');
  $in  = json_decode($raw, true);
  if (!is_array($in)) {
    throw new Exception('JSON inválido en la solicitud');
  }

$nombre = trim($in['nombre'] ?? $in['username'] ?? '');
  $email    = strtolower(trim($in['email'] ?? ''));
  $password = (string)($in['password'] ?? '');
  $RFC      = strtoupper(trim($in['RFC'] ?? ''));
  $Telefono = preg_replace('/\D+/', '', (string)($in['Telefono'] ?? ''));

  // Validaciones
  $err = [];
if ($nombre === '' || mb_strlen($nombre) < 3) $err[] = 'Nombre completo requerido';
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err[] = 'Email inválido';
  if (strlen($password) < 6) $err[] = 'La contraseña debe tener al menos 6 caracteres';
  if ($RFC === '' || !preg_match('/^([A-ZÑ&]{3,4})\d{6}([A-Z0-9]{2,3})$/', $RFC)) $err[] = 'RFC inválido';
  if ($Telefono === '' || !preg_match('/^\d{10}$/', $Telefono)) $err[] = 'Teléfono inválido (10 dígitos)';
  if ($err) { echo json_encode(['ok'=>false,'msg'=>implode('. ', $err)]); exit; }

  // Email único
  $q = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
  $q->execute([$email]);
  if ($q->fetch()) { echo json_encode(['ok'=>false,'msg'=>'Ya existe una cuenta con ese email']); exit; }


  
  // Crear usuario + token
  $passwordHash = password_hash($password, PASSWORD_DEFAULT);
  $pdo->beginTransaction();

  // OJO: ajusta nombres de columnas según tu tabla (rfc / telefono si están en minúsculas)
 $ins = $pdo->prepare('
  INSERT INTO usuarios (username, email, password, RFC, Telefono, created_at)
  VALUES (?,?,?,?,?,NOW())
');
$ins->execute([$nombre, $email, $passwordHash, $RFC, $Telefono]);

  $userId = (int)$pdo->lastInsertId();

  $token   = bin2hex(random_bytes(16));
  $hashBin = hash('sha256', $token, true);             // VARBINARY(32)
  $expira  = (new DateTime('+1 day'))->format('Y-m-d H:i:s');

  $up = $pdo->prepare('
    UPDATE usuarios
       SET email_verify_token_hash = ?, email_verify_expires = ?
     WHERE id = ?
  ');
  $up->bindParam(1, $hashBin, PDO::PARAM_LOB);
  $up->bindParam(2, $expira);
  $up->bindParam(3, $userId, PDO::PARAM_INT);
  $up->execute();

  $pdo->commit();

  // Enviar correo de verificación
  $verifyUrl = APP_URL . '/auth/verificar.php?token=' . urlencode($token) . '&email=' . urlencode($email);
$env = enviarCorreoVerificacion($email, $nombre, $verifyUrl);

  if (!$env['ok']) {
    // Opcional: podrías marcar un estado "pendiente de verificación" y permitir reenviar después
    echo json_encode(['ok'=>false,'error'=>'MAIL','msg'=>'Cuenta creada, pero falló el envío de verificación. Intenta más tarde.','detail'=>$env['error'] ?? null]);
    exit;
  }

  echo json_encode([
    'ok' => true,
    'msg' => 'Te enviamos un enlace de verificación a ' . $email . '. Revisa tu bandeja y Spam.',
    'needVerify' => true
  ]);
  exit;

} catch (Throwable $e) {
  if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'SERVER','msg'=>$e->getMessage()]);
  exit;
}

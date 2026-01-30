<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/mailer.php';
require_once __DIR__ . '/../config.php';
session_start();

$errores = [];
$exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nombre = trim($_POST['nombre'] ?? '');
  $email  = trim($_POST['email'] ?? '');
  $pass   = $_POST['password'] ?? '';

  if ($nombre === '') $errores[] = 'El nombre es obligatorio';
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errores[] = 'Email inválido';
  if (strlen($pass) < 6) $errores[] = 'La contraseña debe tener al menos 6 caracteres';

  if (!$errores) {
    // Email único
    $q = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
    $q->execute([$email]);
    if ($q->fetch()) $errores[] = 'Ya existe un usuario con ese email';
  }

  if (!$errores) {
    $passwordHash = password_hash($pass, PASSWORD_DEFAULT);
    $pdo->beginTransaction();
    try {
      $stmt = $pdo->prepare('INSERT INTO usuarios (nombre, email, password_hash, created_at) VALUES (?, ?, ?, NOW())');
      $stmt->execute([$nombre, $email, $passwordHash]);
      $idUsuario = (int)$pdo->lastInsertId();

      $token   = bin2hex(random_bytes(16));     // token público (32 hex)
      $hashBin = hash('sha256', $token, true);  // 32 bytes crudos -> VARBINARY(32)
      $expira  = (new DateTime('+1 day'))->format('Y-m-d H:i:s');

      $u = $pdo->prepare('UPDATE usuarios SET email_verify_token_hash = ?, email_verify_expires = ? WHERE id = ?');
      $u->bindParam(1, $hashBin, PDO::PARAM_LOB);
      $u->bindParam(2, $expira);
      $u->bindParam(3, $idUsuario, PDO::PARAM_INT);
      $u->execute();

      $pdo->commit();

      $verifyUrl = APP_URL . '/auth/verificar.php?token=' . urlencode($token) . '&email=' . urlencode($email);
      if (enviarCorreoVerificacion($email, $nombre, $verifyUrl)) {
        $exito = 'Registro exitoso. Revisa tu correo para verificar tu cuenta.';
      } else {
        $errores[] = 'No se pudo enviar el correo de verificación.';
      }
    } catch (Throwable $e) {
      $pdo->rollBack();
      $errores[] = 'Error al registrar: ' . $e->getMessage();
    }
  }
}
?>
<!doctype html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Registro</title>
<style>body{font-family:system-ui,Segoe UI,Roboto,sans-serif;max-width:520px;margin:40px auto;padding:0 16px}input,button{width:100%;padding:12px;margin:6px 0}.ok{background:#e6ffed;border:1px solid #a6f3b1;padding:10px;border-radius:6px}.err{background:#ffecec;border:1px solid #ffb3b3;padding:10px;border-radius:6px}</style>
</head><body>
  <h1>Crear cuenta</h1>
  <?php if ($exito): ?><div class="ok"><?=htmlspecialchars($exito)?></div><?php endif; ?>
  <?php if ($errores): ?><div class="err"><ul><?php foreach($errores as $e){ echo '<li>'.htmlspecialchars($e).'</li>'; } ?></ul></div><?php endif; ?>

  <form method="post" autocomplete="off">
    <label>Nombre
      <input type="text" name="nombre" required value="<?=htmlspecialchars($_POST['nombre'] ?? '')?>">
    </label>
    <label>Email
      <input type="email" name="email" required value="<?=htmlspecialchars($_POST['email'] ?? '')?>">
    </label>
    <label>Contraseña
      <input type="password" name="password" required minlength="6">
    </label>
    <button type="submit">Registrarme</button>
  </form>

  <p>¿Ya tienes cuenta? <a href="<?=APP_URL?>/auth/login.php">Inicia sesión</a></p>
</body></html>

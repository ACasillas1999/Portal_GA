<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/mailer.php';
require_once __DIR__ . '/../config.php';

$email  = $_GET['email'] ?? ($_POST['email'] ?? '');
$motivo = $_GET['motivo'] ?? '';
$exito = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Email inválido';
  } else {
    $q = $pdo->prepare('SELECT id, nombre, email_verified_at FROM usuarios WHERE email = ? LIMIT 1');
    $q->execute([$email]);
    $u = $q->fetch();

    if (!$u) {
      $error = 'No existe una cuenta con ese email';
    } elseif (!empty($u['email_verified_at'])) {
      $exito = 'Tu cuenta ya está verificada. Puedes iniciar sesión.';
    } else {
      $token   = bin2hex(random_bytes(16));
      $hashBin = hash('sha256', $token, true);
      $expira  = (new DateTime('+1 day'))->format('Y-m-d H:i:s');

      $up = $pdo->prepare('UPDATE usuarios SET email_verify_token_hash = ?, email_verify_expires = ? WHERE id = ?');
      $up->bindParam(1, $hashBin, PDO::PARAM_LOB);
      $up->bindParam(2, $expira);
      $up->bindParam(3, $u['id'], PDO::PARAM_INT);
      $up->execute();

      $verifyUrl = APP_URL . '/auth/verificar.php?token=' . urlencode($token) . '&email=' . urlencode($email);
      if (enviarCorreoVerificacion($email, $u['nombre'], $verifyUrl)) {
        $exito = 'Te enviamos un nuevo enlace de verificación. Revisa tu correo.';
      } else {
        $error = 'No pudimos enviar el correo de verificación.';
      }
    }
  }
}
?>
<!doctype html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reenviar verificación</title>
<style>body{font-family:system-ui,Segoe UI,Roboto,sans-serif;max-width:520px;margin:40px auto;padding:0 16px}input,button{width:100%;padding:12px;margin:6px 0}.ok{background:#e6ffed;border:1px solid #a6f3b1;padding:10px;border-radius:6px}.err{background:#ffecec;border:1px solid #ffb3b3;padding:10px;border-radius:6px}.note{background:#eef5ff;border:1px solid #c7dbff;padding:10px;border-radius:6px}</style>
</head><body>
  <h1>Reenviar verificación</h1>
  <?php if ($motivo === 'no_verificado'): ?><div class="note">Tu cuenta aún no está verificada. Te podemos enviar un nuevo enlace.</div><?php endif; ?>
  <?php if ($motivo === 'expirado'): ?><div class="note">Tu enlace había expirado. Ingresa tu email para generar uno nuevo.</div><?php endif; ?>
  <?php if ($exito): ?><div class="ok"><?=htmlspecialchars($exito)?></div><?php endif; ?>
  <?php if ($error): ?><div class="err"><?=htmlspecialchars($error)?></div><?php endif; ?>

  <form method="post" autocomplete="off">
    <label>Email
      <input type="email" name="email" required value="<?=htmlspecialchars($email)?>">
    </label>
    <button type="submit">Enviar nuevo enlace</button>
  </form>

  <p><a href="<?=APP_URL?>/auth/login.php">Volver a iniciar sesión</a></p>
</body></html>

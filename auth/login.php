<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../config.php';
session_start();

$errores = [];
$msg = $_GET['msg'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $pass  = $_POST['password'] ?? '';

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errores[] = 'Email inválido';
  if ($pass === '') $errores[] = 'Contraseña requerida';

  if (!$errores) {
    $q = $pdo->prepare('SELECT id, nombre, email, password_hash, email_verified_at FROM usuarios WHERE email = ? LIMIT 1');
    $q->execute([$email]);
    $u = $q->fetch();

    if (!$u || !password_verify($pass, $u['password_hash'])) {
      $errores[] = 'Credenciales inválidas';
    } elseif (empty($u['email_verified_at'])) {
      header('Location: '.APP_URL.'/auth/reenviar_verificacion.php?email='.urlencode($email).'&motivo=no_verificado');
      exit;
    } else {
      $_SESSION['user_id']   = (int)$u['id'];
      $_SESSION['user_name'] = $u['nombre'];
      header('Location: '.APP_URL.'/index.php'); // cambia a tu home
      exit;
    }
  }
}
?>
<!doctype html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login</title>
<style>body{font-family:system-ui,Segoe UI,Roboto,sans-serif;max-width:520px;margin:40px auto;padding:0 16px}input,button{width:100%;padding:12px;margin:6px 0}.err{background:#ffecec;border:1px solid #ffb3b3;padding:10px;border-radius:6px}.ok{background:#e6ffed;border:1px solid #a6f3b1;padding:10px;border-radius:6px}</style>
</head><body>
  <h1>Iniciar sesión</h1>
  <?php if ($msg === 'ya_verificado'): ?><div class="ok">Tu correo ya estaba verificado. Ahora puedes iniciar sesión.</div><?php endif; ?>
  <?php if ($errores): ?><div class="err"><ul><?php foreach($errores as $e){ echo '<li>'.htmlspecialchars($e).'</li>'; } ?></ul></div><?php endif; ?>

  <form method="post" autocomplete="off">
    <label>Email
      <input type="email" name="email" required value="<?=htmlspecialchars($_POST['email'] ?? '')?>">
    </label>
    <label>Contraseña
      <input type="password" name="password" required>
    </label>
    <button type="submit">Entrar</button>
  </form>

  <p>¿No tienes cuenta? <a href="<?=APP_URL?>/auth/registro.php">Regístrate</a></p>
</body></html>

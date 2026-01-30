<?php
// auth/verificar.php
// Página HTML (no JSON). No imprimas nada antes del HTML.

require_once __DIR__ . '/../config.php'; // <- define constantes
require_once __DIR__ . '/../app/db.php'; // <- crea $pdo

// Helpers
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$ok = false;
$msg = '';
try {
  $token = $_GET['token'] ?? '';
  $email = $_GET['email'] ?? '';

  if ($token === '' || $email === '') {
    throw new Exception('Enlace incompleto o inválido.');
  }
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    throw new Exception('Correo inválido.');
  }

  // Buscar usuario por email
  $st = $pdo->prepare('SELECT id, email_verified_at, email_verify_token_hash, email_verify_expires FROM usuarios WHERE email = ? LIMIT 1');
  $st->execute([$email]);
  $u = $st->fetch();

  if (!$u) {
    throw new Exception('No existe una cuenta con ese correo.');
  }

  // Ya verificado
  if (!empty($u['email_verified_at'])) {
    $ok = true;
    $msg = 'Tu correo ya estaba verificado. Ya puedes iniciar sesión.';
  } else {
    // Verificar expiración
    if (empty($u['email_verify_expires']) || strtotime($u['email_verify_expires']) < time()) {
      throw new Exception('El enlace de verificación ha expirado. Solicita uno nuevo desde el login.');
    }

    // Comparar hash (la columna es VARBINARY(32))
    $hashBin = hash('sha256', $token, true);
    $stored  = $u['email_verify_token_hash'];

    // $stored puede venir como string binario desde PDO
    if (!is_string($stored) || !hash_equals($stored, $hashBin)) {
      throw new Exception('Token inválido o ya utilizado.');
    }

    // Marcar verificado y limpiar token
    $upd = $pdo->prepare('UPDATE usuarios SET email_verified_at = NOW(), email_verify_token_hash = NULL, email_verify_expires = NULL WHERE id = ?');
    $upd->execute([$u['id']]);

    $ok = true;
    $msg = '¡Correo verificado con éxito! Ya puedes iniciar sesión.';
  }

} catch (Throwable $e) {
  $ok = false;
  $msg = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Verificación de correo</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    :root { color-scheme: light dark; }
    body {
      margin:0; padding:0; font-family: system-ui, Segoe UI, Roboto, sans-serif;
      min-height:100dvh; display:grid; place-items:center;
      background: radial-gradient(1200px 600px at 30% -10%, #1f2a44 0%, #0f1422 40%, #0a0c12 100%);
      color:#fff;
    }
    .card {
      max-width: 520px; padding: 28px 24px; border-radius: 16px;
      background: rgba(255,255,255,.06); backdrop-filter: blur(6px);
      box-shadow: 0 12px 40px rgba(0,0,0,.35);
      text-align: center;
    }
    h1 { margin:0 0 8px; font-size: 1.6rem; }
    p  { margin:0 0 16px; opacity:.95; }
    .ok { color: #7CFC9B; font-weight: 600; }
    .err{ color: #ff7b7b; font-weight: 600; }
    a.btn {
      display:inline-block; margin-top:10px; padding:10px 16px; border-radius:10px;
      text-decoration:none; font-weight:600; letter-spacing:.2px;
      background:#ff8f3c; color:#111; transition:transform .08s ease;
    }
    a.btn:active { transform: scale(.98); }
  </style>
</head>
<body>
  <div class="card">
    <h1><?= $ok ? 'Verificación completada' : 'No se pudo verificar' ?></h1>
    <p class="<?= $ok ? 'ok':'err' ?>"><?= h($msg) ?></p>
    <a class="btn" href="<?= h(APP_URL) ?>/">Ir al inicio de sesión</a>
  </div>
</body>
</html>

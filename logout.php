<?php
// logout.php
session_name('GA');
session_start();

$valid = ($_SERVER['REQUEST_METHOD'] === 'POST')
      && isset($_POST['t'], $_SESSION['csrf_logout'])
      && hash_equals($_SESSION['csrf_logout'], $_POST['t']);

if ($valid) {
  // Limpia sesión
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
  }
  session_destroy();
}

// Redirige al login (ajusta ruta a tu página de acceso)
$redirect = 'index.php'; // o '/index.php' según tu proyecto
header("Location: {$redirect}");
exit;

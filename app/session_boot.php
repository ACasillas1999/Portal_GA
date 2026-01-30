<?php
// app/session_boot.php
declare(strict_types=1);

if (!defined('GA_SESSION_BOOTSTRAPPED')) {
  define('GA_SESSION_BOOTSTRAPPED', true);

  // Arranca sesión solo si aún no está activa (evita warnings)
  if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? '1' : '0';
    ini_set('session.cookie_secure', $secure);
    session_name('GA');
    session_start();
  }

  // Parámetros de control
  $MAX_INACTIVITY = 30 * 60;   // 30 min sin actividad
  $MAX_ABSOLUTE   = 8  * 3600; // 8 horas desde login
  $REGEN_EVERY    = 5  * 60;   // regenerar ID cada 5 min
  $LOGIN_URL      = (defined('APP_URL') ? APP_URL : '') . '/index.php';

  function _redir_login(string $reason = 'login_requerido'): void {
    global $LOGIN_URL;
    $_SESSION['flash_msg'] = $reason;
    header('Location: ' . $LOGIN_URL . '?msg=' . urlencode($reason), true, 302);
    exit;
  }

  // Llama esta función en páginas protegidas
  function require_login(): void {
    global $MAX_INACTIVITY, $MAX_ABSOLUTE, $REGEN_EVERY;
    $now = time();

    // 1) ¿Hay sesión válida?
    $logged = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;

    // 2) Caducidad por inactividad
    if (isset($_SESSION['last_activity']) && ($now - (int)$_SESSION['last_activity']) > $MAX_INACTIVITY) {
      session_unset(); session_destroy(); _redir_login('sesion_expirada');
    }

    // 3) Caducidad absoluta
    if (isset($_SESSION['login_time']) && ($now - (int)$_SESSION['login_time']) > $MAX_ABSOLUTE) {
      session_unset(); session_destroy(); _redir_login('sesion_maxima');
    }

    // 4) Si no está logueado
    if (!$logged) _redir_login('login_requerido');

    // 5) Regeneración periódica del ID
    $lastReg = (int)($_SESSION['last_regen'] ?? 0);
    if ($now - $lastReg > $REGEN_EVERY) {
      session_regenerate_id(true);
      $_SESSION['last_regen'] = $now;
    }

    // 6) Marca actividad
    $_SESSION['last_activity'] = $now;
  }
}

<?php
// ===== Certificados =====
if (!defined('CAFILE_PATH')) {
  define('CAFILE_PATH', __DIR__ . DIRECTORY_SEPARATOR . 'certs' . DIRECTORY_SEPARATOR . 'cacert.pem');
}

// ===== App =====
if (!defined('SMTP_DEBUG')) define('SMTP_DEBUG', 2);
if (!defined('APP_URL'))    define('APP_URL', 'https://clientes.grupoascencio.com.mx');

// ===== BD (PDO) =====
if (!defined('DB_DSN'))  define('DB_DSN',  'mysql:host=18.211.75.118;dbname=gpoascen_portal;charset=utf8mb4');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '04nm2fdLefCxM');

// ===== SMTP =====
// IMPORTANTE: Usa una sola combinación:
// Opción A (SMTPS implícito): 465 + 'ssl'
// Opción B (STARTTLS):        587 + 'tls'
if (!defined('SMTP_HOST'))        define('SMTP_HOST', 'smtp.gmail.com'); // ¡Que coincida con el CN/SAN del certificado!
if (!defined('SMTP_USER'))        define('SMTP_USER', 'verificacion.ascencio@gmail.com');
if (!defined('SMTP_PASS'))        define('SMTP_PASS', 'brls pjes nevx dfle'); // <- RÓTALA URGENTE
if (!defined('SMTP_PORT'))        define('SMTP_PORT', 587);
if (!defined('SMTP_SECURE'))      define('SMTP_SECURE', 'tls'); // <-- antes 'tls'
if (!defined('SMTP_FROM_EMAIL'))  define('SMTP_FROM_EMAIL', SMTP_USER);
if (!defined('SMTP_FROM_NAME'))   define('SMTP_FROM_NAME',  'Portal GA verificación');


// --- Bridges para compatibilidad con el código ---
if (!defined('SMTP_FROM') && defined('SMTP_FROM_EMAIL')) {
  define('SMTP_FROM', SMTP_FROM_EMAIL);
}
if (!defined('SMTP_FROM_NAME')) {
  define('SMTP_FROM_NAME', 'Portal GA'); // ya lo tienes, este es fallback
}
// Lista de admins para notificación (separados por ;)
if (!defined('ADMIN_NOTIFY_LIST')) {
  define('ADMIN_NOTIFY_LIST', 'j.rubio@grupoascencio.com.mx');
}

define('TURNSTILE_SECRET', '0x4AAAAAAB4wQWqR_YfoGVspydwcApI77kE');
?>
<?php
// Respuesta JSON limpia
while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors','0');
error_reporting(E_ALL);

// 👇 SOLO mailer.php (ya incluye config.php con require_once)
require_once __DIR__ . '/app/mailer.php';

$to  = $_GET['to'] ?? 'tu_correo@ejemplo.com';
$url = APP_URL . '/auth/verificar.php?token=debug&email=' . urlencode($to);

echo json_encode(enviarCorreoVerificacion($to, 'Debug', $url));

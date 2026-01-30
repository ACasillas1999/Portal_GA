<?php
// Conexiones/Conexion.php  (versión PDO)
declare(strict_types=1);

$DB_HOST = '18.211.75.118';   // o 'localhost'
$DB_PORT = 3306;          // si usas 3307 cámbialo aquí
$DB_NAME = 'gpoascen_portal';
$DB_USER = 'Portal_GA';
$DB_PASS = 'laqueseawea123';

function db(): PDO {
  global $DB_HOST, $DB_PORT, $DB_NAME, $DB_USER, $DB_PASS;
  $dsn = "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4";
  $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  // Opcional: zona horaria en la sesión de MySQL
  // $pdo->exec("SET time_zone = '-06:00'");
  return $pdo;
}

<?php
// app/db.php
if (!defined('DB_DSN')) {
  require_once __DIR__ . '/../config.php';
}

$dsn  = DB_DSN;
$user = DB_USER;
$pass = DB_PASS;

$pdo = new PDO($dsn, $user, $pass, [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES   => false,
]);

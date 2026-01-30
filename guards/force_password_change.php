<?php
// C:\xampp\htdocs\Portal_GA\guards\force_password_change.php
declare(strict_types=1);

// Ajusta si tu base cambia
const BASE_URL = 'https://clientes.grupoascencio.com.mx';

// Inicia sesión SOLO si no está activa
if (session_status() === PHP_SESSION_NONE) {
  session_name('GA');    // mismo nombre que usa tu login
  session_start();
}

// Si no está logueado, al login del portal
if (!($_SESSION['loggedin'] ?? false)) {
  header('Location: ' . BASE_URL . '/'); exit;
}

require_once dirname(__DIR__) . '/app/db.php'; // $pdo
$userId = (int)($_SESSION['user_id'] ?? 0);

// Si por alguna razón falta el id, al login
if ($userId <= 0) { header('Location: ' . BASE_URL . '/'); exit; }

// Evita loop en la página de cambio
$isChangePage = (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'first_change_password.php');

// Consulta si ya cambió la contraseña
$st = $pdo->prepare('SELECT password_changed_at FROM usuarios WHERE id = :id LIMIT 1');
$st->execute([':id' => $userId]);
$changedAt = $st->fetchColumn();

// Si NO ha cambiado (NULL) y no estamos ya en la página de cambio: redirige
if (empty($changedAt) && !$isChangePage) {
  header('Location: ' . BASE_URL . '/first_change_password.php'); exit;
}

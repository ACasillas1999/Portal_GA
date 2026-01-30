<?php
// ===== Sesión consistente =====
ini_set('session.cookie_httponly', '1');
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
  ini_set('session.cookie_secure', '0');
} else {
  ini_set('session.cookie_secure', '1');
}
session_name('GA');
session_start();

date_default_timezone_set('America/Mexico_City');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/db.php'; // $pdo

function h($s)
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function fmtSize($b)
{
  $b = (int)$b;
  if ($b < 1024) return $b . ' B';
  $kb = $b / 1024;
  if ($kb < 1024) return number_format($kb, 1) . ' KB';
  return number_format($kb / 1024, 2) . ' MB';
}
function badge($estadoRaw)
{
  // Soporta numérico (0/1/11) y texto heredado
  if (is_numeric($estadoRaw)) {
    $c = (int)$estadoRaw;
    if ($c === 1)  return '<span class="status final"><span class="dot"></span>Aprobado</span>';
    if ($c === 11) return '<span class="status cancelado"><span class="dot"></span>Rechazado</span>';
    return '<span class="status proceso"><span class="dot"></span>En revisión</span>'; // 0 u otros
  }

  $e = mb_strtolower(trim((string)$estadoRaw));
  // Normaliza textos anteriores
  if (in_array($e, ['enviada', 'enviado', 'recibida', 'en revisión', 'revision', 'procesando'], true)) {
    return '<span class="status proceso"><span class="dot"></span>En revisión</span>';
  }
  if (in_array($e, ['aprobado', 'aprobada', 'entregado', 'entregada'], true)) {
    return '<span class="status final"><span class="dot"></span>Aprobado</span>';
  }
  if (in_array($e, ['rechazado', 'rechazada', 'cancelado', 'cancelada'], true)) {
    return '<span class="status cancelado"><span class="dot"></span>Rechazado</span>';
  }

  // Fallback
  return '<span class="status proceso"><span class="dot"></span>' . h($estadoRaw ?: 'En revisión') . '</span>';
}


// ===== Leer sesión (varias claves) =====
$uid = $_SESSION['user_id'] ?? $_SESSION['ID'] ?? $_SESSION['idUsuario'] ?? $_SESSION['usuario_id'] ?? null;
$emailSess = $_SESSION['Email'] ?? $_SESSION['email'] ?? $_SESSION['Correo'] ?? null;
$rol = $_SESSION['Rol'] ?? '';

// ===== ID =====
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  http_response_code(400);
  exit('ID inválido');
}

// ===== Cargar solicitud =====
$st = $pdo->prepare("SELECT * FROM protocolo_solicitudes WHERE id = ? LIMIT 1");
$st->execute([$id]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row) {
  http_response_code(404);
  exit('Solicitud no encontrada');
}

// ===== Autorización =====
$isOwner = false;
if ($uid && (int)$row['id_usuario'] === (int)$uid) $isOwner = true;
if ($emailSess && strtolower($row['email']) === strtolower($emailSess)) $isOwner = true;
if (!($rol === 'Admin' || $isOwner)) {
  http_response_code(403);
  header("Refresh:3; url=index.php");
  echo '<div style="background-color: #ffeded; border: 1px solid #f5c6cb; padding: 15px; border-radius: 8px; text-align: center; color: #721c24; font-family: Arial, sans-serif;">
          <strong>No tienes permisos para ver esta solicitud.</strong><br>
          Serás redirigido al inicio en 3 segundos.
        </div>';
  exit;
}


// ===== Admin: guardar cambios =====
$msg = '';
if ($rol === 'Admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_update'])) {
  $estado = trim($_POST['estado'] ?? '');
  $url    = trim($_POST['url_entrega'] ?? '');
  $permitidos = ['Recibida', 'En revisión', 'Enviada', 'Rechazada'];
  if (!in_array($estado, $permitidos, true)) $estado = $row['estado'];

  $pdo->beginTransaction();
  // fecha_envio si se marca Enviada y no tenía
  if ($estado === 'Enviada' && empty($row['fecha_envio'])) {
    $q = $pdo->prepare("UPDATE protocolo_solicitudes
                        SET estado = ?, url_entrega = NULLIF(?,''), fecha_envio = NOW(), updated_at = NOW()
                        WHERE id = ?");
    $q->execute([$estado, $url, $id]);
  } else {
    $q = $pdo->prepare("UPDATE protocolo_solicitudes
                        SET estado = ?, url_entrega = NULLIF(?,''), updated_at = NOW()
                        WHERE id = ?");
    $q->execute([$estado, $url, $id]);
  }
  $pdo->commit();

  // recargar datos
  $st = $pdo->prepare("SELECT * FROM protocolo_solicitudes WHERE id = ? LIMIT 1");
  $st->execute([$id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  $msg = 'Cambios guardados.';
}

// ===== Adjuntos =====
$adj = $pdo->prepare("SELECT id, ruta, nombre_original, tamano, created_at
                      FROM protocolo_solicitud_adjuntos
                      WHERE id_solicitud = ? ORDER BY id ASC");
$adj->execute([$id]);
$files = $adj->fetchAll(PDO::FETCH_ASSOC);

// ===== Datos =====
$folio = $row['folio'];


if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title>Solicitud <?= h($folio) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" href="/portal_ga/assets/img/iconpestalla.png" type="image/x-icon">
  <link rel="stylesheet" href="./Estilos/style.css?v=1.0">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">


  <style>
    :root {
      --ga-blue: #005996;
      --ga-blue-600: #0b5fa3;
      --ga-orange: #ed6c24;
      --ink: #1f2937;
      --muted: #88888a;
      --line: #e5e7eb;
      --bg: #f4f6f9;
      --card: #ffffff;
      --radius: 18px;
      --shadow: 0 12px 32px rgba(2, 12, 32, 0.1);
      --container: 1200px;
      --font-sans: "Montserrat", system-ui, -apple-system, "Segoe UI", Roboto,
        "Helvetica Neue", Arial, "Noto Sans", sans-serif;
    }

    * {
      box-sizing: border-box;
      font-family: var(--font-sans) !important;
    }

    .breadcrumb {
      display: flex;
      align-items: center;
      margin: 8px 0 16px;
    }

    .breadcrumb a {

      text-decoration: none;
    }

    .btn-sm {
      display: inline-block;
      padding: 6px 10px;
      text-decoration: none;
      color: #fff;
      text-decoration: none;
      font-weight: 900;
      font-size: 13px;
      letter-spacing: 0.4px;
      padding: 10px 14px;
      border-radius: 4px;
      margin-left: auto;

    }

    .hdr {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      margin-bottom: 10px;
    }

    .hdr h1 {
      margin: 0;
      font-size: clamp(20px, 2.4vw, 28px);
    }

    .wrap {
      width: min(calc(100% - 40px), 1100px);
      margin: 20px auto
    }

    .card {
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 16px;
      padding: 16px;
      margin-bottom: 10px
    }

    .card h3 {
      margin: 0 0 10px;
      font-size: 16px;
      color: var(--ga-orange);
    }

    .kv {
      display: grid;
      grid-template-columns: minmax(140px, 40%) 1fr;
      /* más flexible en desktop/tablet */
      gap: 8px 12px;
    }

    .kv>div {
      min-width: 0;
      /* clave para que no empuje el grid */
      overflow-wrap: anywhere;
      /* partir palabras/líneas largas */
      word-break: break-word;
      /* por si vienen cadenas sin espacios */
    }

    .kv div {
      padding: 4px 0;
      border-bottom: 1px dotted #eee;
    }

    .kv div:nth-child(2n) {
      border-bottom: 1px dotted #eee;
    }

    .hdr {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      flex-wrap: wrap
    }

    .hdr h1 {
      margin: 0;
      font-size: 28px;
      color: var(--ga-orange);
      font-weight: 800
    }

    .kv {
      width: 100%;
      border-collapse: collapse
    }

    .kv th,
    .kv td {
      border-bottom: 1px solid #e5e7eb;
      padding: 10px 8px;
      vertical-align: top
    }

    .kv th {
      width: 240px;
      text-align: left;
      color: #4b5563;
      font-weight: 700
    }

    .desc {
      white-space: pre-wrap
    }

    .muted {
      color: #6b7280
    }

    .back {
      display: inline-block;
      margin-bottom: 10px;
      text-decoration: none
    }

    .ok {
      background: #ecfdf5;
      border: 1px solid #a7f3d0;
      color: #065f46;
      padding: 8px 10px;
      border-radius: 10px;
      margin-bottom: 10px;
      display: inline-block
    }

    .admin form {
      display: grid;
      gap: 10px;
      grid-template-columns: 1fr 2fr auto
    }

    .admin select,
    .admin input {
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      padding: 10px
    }

    .admin button {
      background: var(--ga-blue);
      color: #fff;
      border: 0;
      border-radius: 10px;
      padding: 10px 16px;
      font-weight: 700
    }

    .files a {
      display: inline-flex;
      gap: 8px;
      align-items: center
    }

    /* Layout dos columnas (chat izq, detalle der) */
    .cols {
      display: grid;
      grid-template-columns: 380px 1fr;
      /* izq fija */
      gap: 26px;
      align-items: start;
    }

    .col-left,
    .col-right {
      min-width: 0;
    }

    .comments-card {
      background: #fff;
      border-radius: 18px;
      box-shadow: var(--shadow);
      padding: 18px 20px;
      border: 1px solid #eaeef4;
    }

    .col-left .comments-card {
      display: flex;
      flex-direction: column;
      max-height: calc(100vh - 160px);
      position: sticky;
      top: 16px;
    }

    .comment-list {
      display: flex;
      flex-direction: column;
      gap: 12px;
      margin-top: 8px;
      overflow: auto;
      padding-right: 6px;
      flex: 1 1 auto;
      min-height: 140px;
    }

    .comment-item {
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      padding: 10px 12px;
      background: #fafafa
    }

    .comment-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 6px
    }

    .comment-author {
      display: flex;
      align-items: center;
      gap: 8px;
      font-weight: 700;
      color: #0b1b34
    }

    .badge-rol {
      font-size: 11px;
      padding: 2px 8px;
      border-radius: 999px;
      background: #ecf2fb;
      color: #0b5fa3;
      font-weight: 700
    }

    .comment-time {
      font-size: 12px;
      color: #6b7280
    }

    .comment-body {
      white-space: pre-wrap;
      color: #0f172a
    }

    .comment-empty {
      color: #64748b;
      background: #f8fafc;
      border: 1px dashed #e2e8f0;
      border-radius: 12px;
      padding: 14px;
      text-align: center
    }

    .comment-form {
      margin-top: 14px;
      border-top: 1px solid #eef0f4;
      padding-top: 12px;
      display: flex;
      flex-direction: column;
      gap: 10px;
      position: sticky;
      bottom: 0;
      background: #fff
    }

    .comment-form textarea {
      width: 100%;
      min-height: 90px;
      padding: 10px 12px;
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      resize: vertical;
      font: 14px/1.4 system-ui, sans-serif
    }

    .comment-actions {
      display: flex;
      gap: 10px;
      align-items: center
    }

    .btn-primary {
      background: var(--ga-orange);
      color: #fff;
      border: 0;
      border-radius: 12px;
      padding: 10px 14px;
      font-weight: 700;
      cursor: pointer;
      margin-left: auto;
    }

    .btn-primary:disabled {
      opacity: .6;
      cursor: not-allowed
    }

    .status {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 6px 10px;
      border-radius: 999px;
      font-weight: 600;
      font-size: 12px;
    }

    .status.proceso {
      background: #fff7ed;
      color: #b45309;
      border-color: 1px #fde68a;
    }

    .help {
      font-size: 12px;
      color: #64748b
    }

    /* Responsive */
    @media (max-width:1100px) {
      .cols {
        grid-template-columns: 1fr;
        gap: 16px
      }

      .col-left .comments-card {
        position: static;
        max-height: none
      }
    }

    @media (max-width: 900px) {
      .grid {
        grid-template-columns: 1fr
      }

      .kv {
        grid-template-columns: 1fr;
      }
    }


    /* === Chat look & feel === */
    .comment-list.chat {
      display: flex;
      flex-direction: column;
      gap: 10px;
      max-height: clamp(260px, 45vh, 520px);
      overflow: auto;
      padding: 10px;
      background: #f8fafc;
      border: 1px solid #e5e7eb;
      border-radius: 14px;
    }

    /* Cada mensaje */
    .msg {
      display: flex;
      max-width: 80%;
    }

    .msg .bubble {
      padding: 10px 12px;
      border-radius: 16px;
      box-shadow: 0 1px 2px rgba(0, 0, 0, .06);
      font-size: 14px;
      line-height: 1.35;
    }

    /* Mensajes del otro (izquierda) */
    .msg.other {
      align-self: flex-start;
    }

    .msg.other .bubble {
      background: #fff;
      color: #0f172a;
      border: 1px solid #e5e7eb;
      border-top-left-radius: 6px;
    }

    /* Mis mensajes (derecha) */
    .msg.mine {
      align-self: flex-end;
      text-align: right;
    }

    .msg.mine .bubble {
      background: #0b5fa3;
      color: #fff;
      border: 0;
      border-top-right-radius: 6px;
    }

    /* Encabezado dentro de la burbuja */
    .bhead {
      font-size: 12px;
      opacity: .85;
      display: flex;
      gap: 8px;
      align-items: center;
      margin-bottom: 4px;
    }

    .bhead .who {
      font-weight: 700;
    }

    .bhead .time {
      opacity: .8;
    }

    table.kv2 {
      display: block;
      /* permite controlar el ancho/scroll si hiciera falta */
      width: 100%;
      max-width: 100%;
    }

    /* Misma grilla para thead y cada fila del tbody */
    table.kv2 thead tr,
    table.kv2 tbody tr {
      display: grid;
      grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1fr);
      /* 3 columnas */
    }

    /* Evita desbordes por textos/URLs largos, sin dar estilo visual */
    table.kv2 th,
    table.kv2 td {
      min-width: 0;
      overflow-wrap: anywhere;
      /* permite cortar palabras/links largos */
    }

    /* Si hay enlaces largos dentro de las celdas */
    table.kv2 td a {
      display: inline-block;
      max-width: 100%;
      overflow-wrap: anywhere;
    }

    .kv2 {
      overflow-x: auto;
    }

    @media (max-width: 768px) {
      table.kv2 thead {
        display: none;
        /* escondemos categorías */
      }

      table.kv2 tbody tr {
        grid-template-columns: 1fr;
        /* una sola columna */
        margin-bottom: 12px;
      }

      table.kv2 tbody td {
        display: flex;
        justify-content: space-between;
      }

      /* opcional: agrega categoría como label antes del valor */
      table.kv2 tbody td:nth-child(1)::before {
        content: "Archivo: ";
        font-weight: bold;
      }

      table.kv2 tbody td:nth-child(2)::before {
        content: "Subido: ";
        font-weight: bold;
      }

      table.kv2 tbody td:nth-child(3)::before {
        content: "Tamaño: ";
        font-weight: bold;
      }
    }

    /* Miniaturas */
    .thumb {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 96px;
      height: 64px;
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      background: #fff;
      padding: 0;
      cursor: pointer;
      box-shadow: 0 1px 2px rgba(0, 0, 0, .04);
    }

    .thumb img {
      max-width: 100%;
      max-height: 100%;
      border-radius: 8px;
      object-fit: cover;
    }

    .thumb span {
      font: 600 12px/1 var(--font-sans);
      color: #334155;
    }

    .thumb-pdf {
      background: #fff5f0;
      border-color: #fdba74;
    }

    .thumb-video {
      background: #f0f9ff;
      border-color: #93c5fd;
    }

    .thumb-audio {
      background: #f8fafc;
      border-color: #cbd5e1;
    }

    .thumb-file {
      text-decoration: none;
    }

    /* Modal */
    #file-modal {
      position: fixed;
      inset: 0;
      z-index: 9999;
      background: rgba(15, 23, 42, .6);
      display: none;
      /* toggled with .open */
    }

    #file-modal.open {
      display: block;
    }

    #file-modal .dialog {
      position: absolute;
      inset: auto 0 0 0;
      /* center below */
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: min(96vw, 980px);
      height: min(90vh, 720px);
      background: #fff;
      border-radius: 14px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, .25);
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }

    #file-modal .bar {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 10px 12px;
      border-bottom: 1px solid #e5e7eb;
      background: #f8fafc;
    }

    #file-modal .bar .title {
      font-weight: 700;
      color: #0b1b34;
      font-size: 14px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    #file-modal .bar .spacer {
      flex: 1;
    }

    #file-modal .bar .btn {
      border: 0;
      background: #0b5fa3;
      color: #fff;
      padding: 8px 10px;
      border-radius: 10px;
      font-weight: 700;
      cursor: pointer;
    }

    #file-modal .body {
      flex: 1;
      background: #111827;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    #file-modal .body .viewer {
      width: 100%;
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    #file-modal img.viewer-el {
      max-width: 100%;
      max-height: 100%;
      object-fit: contain;
      background: #111827;
    }

    #file-modal iframe.viewer-el {
      width: 100%;
      height: 100%;
      border: 0;
      background: #111827;
    }

    #file-modal video.viewer-el,
    #file-modal audio.viewer-el {
      max-width: 100%;
      width: min(100%, 960px);
      outline: none;
      background: #111827;
    }

    @media (max-width: 640px) {
      #file-modal .dialog {
        width: 95vw;
        height: 80vh;
      }
    }

    /* === Grid de tarjetas === */
    .file-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
      gap: 16px;
    }

    /* === Tarjeta === */
    .file-card {
      display: flex;
      flex-direction: column;
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 16px;
      box-shadow: var(--shadow);
      overflow: hidden;
    }

    /* Miniatura */
    .file-thumb {
      display: block;
      width: 100%;
      aspect-ratio: 16 / 10;
      background: #f3f4f6;
      border: 0;
      padding: 0;
      cursor: pointer;
    }

    .file-thumb img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }

    /* Fallback para no-imagen (PDF/Video/Audio/otros) */
    .file-thumb-fallback {
      width: 100%;
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(180deg, #f8fafc, #eef2f7);
    }

    .file-thumb-fallback .ft-ext {
      font-weight: 800;
      letter-spacing: .6px;
      color: #0b5fa3;
      background: #eaf2fd;
      border: 1px solid #cfe1fb;
      padding: 6px 10px;
      border-radius: 999px;
    }

    /* Meta */
    .file-meta {
      padding: 10px 12px 0 12px;
    }

    .file-name {
      font-weight: 700;
      color: #0b1b34;
      font-size: 14px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .file-sub {
      margin-top: 4px;
      color: #6b7280;
      font-size: 12px;
      display: flex;
      gap: 6px;
      align-items: center;
    }

    /* Acciones */
    .file-actions {
      padding: 12px;
      margin-top: auto;
      display: flex;
      justify-content: flex-end;
    }

    .btn-download {
      display: inline-block;
      background: #6b7280;
      color: #fff;
      text-decoration: none;
      font-weight: 800;
      font-size: 13px;
      letter-spacing: .2px;
      padding: 10px 14px;
      border-radius: 8px;
    }

    .btn-download:hover {
      filter: brightness(.95);
    }

    /* Hover sutil sobre la miniatura */
    .file-thumb:hover {
      filter: brightness(.98);
    }
  </style>
  <!-- Microsoft Clarity -->
<script type="text/javascript">
  (function(c,l,a,r,i,t,y){
    c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
    t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
    y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
  })(window, document, "clarity", "script", "tm0znm12k6");
</script>

<?php
// === Datos del usuario (si existe sesión) ===
session_start();
$clarity_id   = $_SESSION['user_id']   ?? '';
$clarity_name = $_SESSION['username']  ?? ($_SESSION['Email'] ?? '');
$clarity_rol  = $_SESSION['Rol']       ?? 'Invitado';
$clarity_suc  = $_SESSION['Sucursal']  ?? '';
?>

<script>
(function(){
  // Evita duplicar el envío
  if (!window.__clarityIdentDone) {
    window.__clarityIdentDone = true;
    window.clarity = window.clarity || function(){(window.clarity.q=window.clarity.q||[]).push(arguments);};

    <?php if (!empty($clarity_id) || !empty($clarity_name)): ?>
      // Identificador de usuario visible en Clarity
      clarity("identify", "<?= htmlspecialchars($clarity_name ?: $clarity_id, ENT_QUOTES, 'UTF-8') ?>");
      // Propiedades extra
      clarity("set", "rol",      "<?= htmlspecialchars($clarity_rol, ENT_QUOTES, 'UTF-8') ?>");
      clarity("set", "sucursal", "<?= htmlspecialchars($clarity_suc, ENT_QUOTES, 'UTF-8') ?>");
    <?php endif; ?>
  }
})();
</script>

</head>

<body>

  <?php require __DIR__ . '/../Componentes/sidebar.php'; ?>

  <main class="wrap">
    <nav class="breadcrumb">
      <a class="btn-sm" href="./index.php">Volver al historial</a>
    </nav>

    <header class="hdr">
      <div>
        <h1>Folio <?= h($folio) ?></h1>
      </div>
      <div><?= badge($row['estado']) ?></div>
    </header>

    <div class="cols">
      <!-- IZQUIERDA: Comentarios -->
      <div class="col-left">
        <section class="comments-card" aria-labelledby="comments-title">
          <h2 class="comments-title" id="comments-title" style="margin:0 0 12px;font:800 20px/1.2 ui-sans-serif,system-ui,'Segoe UI',Roboto;color:#0b1b34;display:flex;align-items:center;gap:8px">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#ed6c24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M21 15a4 4 0 0 1-4 4H7l-4 4V7a4 4 0 0 1 4-4h6" />
              <path d="M17 3h4v4" />
              <path d="M16 8l5-5" />
            </svg>
            Comentarios
          </h2>

          <div id="comment-list" class="comment-list chat" role="list"></div>

          <?php if ($rol === 'Admin' || $isOwner): ?>
            <form id="comment-form" class="comment-form" autocomplete="off">
              <textarea id="cuerpo" name="cuerpo" placeholder="Escribe un comentario…"></textarea>
              <div class="comment-actions">
                <button class="btn-primary" id="btn-enviar" type="submit">Publicar</button>
              </div>
              <input type="hidden" name="id_solicitud" value="<?= (int)$row['id'] ?>">
              <input type="hidden" name="csrf" value="<?= h($csrfToken) ?>">
            </form>
          <?php else: ?>
            <div class="comment-empty">No tienes permisos para comentar esta solicitud.</div>
          <?php endif; ?>
        </section>
      </div>

      <!-- DERECHA: Detalle + Adjuntos + Admin -->
      <div class="col-right">
        <article class="card">
          <h3>Información</h3>
          <div class="kv">
            <div><b>Fecha de solicitud</b></div>
            <div><?= h(date('d/m/Y H:i', strtotime($row['created_at']))) ?></div>
            <div><b>Última actualización</b></div>
            <div><?= h($row['updated_at'] ? date('d/m/Y H:i', strtotime($row['updated_at'])) : '-') ?></div>
            <div><b>Nombre de contacto</b></div>
            <div><?= h($row['nombre_contacto']) ?></div>
            <div><b>Empresa / Razón social</b></div>
            <div><?= h($row['empresa'] ?: '—') ?></div>
            <div><b>Sucursal</b></div>
            <div><?= h($row['sucursal'] ?: '—') ?></div>
            <div><b>Correo</b></div>
            <div><?= h($row['email']) ?></div>
            <div><b>Teléfono</b></div>
            <div><?= h($row['telefono']) ?></div>
            <div><b>No. de serie</b></div>
            <div><?= h($row['no_serie'] ?: '—') ?></div>
            <div><b>No. de factura</b></div>
            <div><?= h($row['no_factura'] ?: '—') ?></div>
            <div><b>Fecha de factura</b></div>
            <div><?= h($row['fecha_factura'] ?: '—') ?></div>
            <div><b>Descripción</b></div>
            <div><?= h($row['descripcion']) ?></div>
          </div>
        </article>

        <article class="card">
          <h3 style="margin:0 0 12px 0">Adjuntos (<?= count($files) ?>)</h3>

          <?php if (!$files): ?>
            <p class="muted">Sin archivos adjuntos.</p>
          <?php else: ?>
            <div class="file-grid">
              <?php foreach ($files as $f):
                $url  = (string)$f['ruta'];
                $name = $f['nombre_original'] ?: basename($url);
                $path = parse_url($url, PHP_URL_PATH) ?? '';
                $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));

                $isImg   = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'avif'], true);
                $isPdf   = ($ext === 'pdf');
                $isVideo = in_array($ext, ['mp4', 'webm', 'ogg'], true);
                $isAudio = in_array($ext, ['mp3', 'ogg', 'wav', 'm4a'], true);
                $type    = $isImg ? 'image' : ($isPdf ? 'pdf' : ($isVideo ? 'video' : ($isAudio ? 'audio' : 'file')));
                $previewSrc = $isPdf ? ($url . '#view=FitH') : $url;
              ?>
                <div class="file-card">
                  <button class="file-thumb js-preview"
                    data-type="<?= h($type) ?>"
                    data-src="<?= h($previewSrc) ?>"
                    data-name="<?= h($name) ?>"
                    aria-label="Vista previa: <?= h($name) ?>">
                    <?php if ($isImg): ?>
                      <img loading="lazy" src="<?= h($url) ?>" alt="<?= h($name) ?>" />
                    <?php else: ?>
                      <div class="file-thumb-fallback">
                        <div class="ft-ext"><?= strtoupper($ext ?: 'FILE') ?></div>
                      </div>
                    <?php endif; ?>
                  </button>

                  <div class="file-meta">
                    <div class="file-name" title="<?= h($name) ?>"><?= h($name) ?></div>
                    <div class="file-sub">
                      <span><?= h($f['created_at'] ? date('d/m/Y H:i', strtotime($f['created_at'])) : '—') ?></span>
                      <span>·</span>
                      <span><?= h(fmtSize($f['tamano'])) ?></span>
                    </div>
                  </div>

                  <div class="file-actions">
                    <a class="btn-download" href="<?= h($url) ?>" download>
                      Descargar
                    </a>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </article>



        <!--<section class="card">
          <div class="hdr">
            <h1>Folio <?= h($folio) ?></h1>
            <div><?= badge($row['estado']) ?></div>
          </div>
          <?php if ($msg): ?><div class="ok"><?= h($msg) ?></div><?php endif; ?>
          <table class="kv" role="table" aria-label="Detalles">
            <tbody>
              <tr>
                <th>Fecha de solicitud</th>
                <td><?= h(date('d/m/Y H:i', strtotime($row['created_at']))) ?></td>
              </tr>
              <tr>
                <th>Última actualización</th>
                <td><?= h($row['updated_at'] ? date('d/m/Y H:i', strtotime($row['updated_at'])) : '-') ?></td>
              </tr>
              <tr>
                <th>Nombre de contacto</th>
                <td><?= h($row['nombre_contacto']) ?></td>
              </tr>
              <tr>
                <th>Empresa / Razón social</th>
                <td><?= h($row['empresa'] ?: '—') ?></td>
              </tr>
              <tr>
                <th>Sucursal</th>
                <td><?= h($row['sucursal'] ?: '—') ?></td>
              </tr>
              <tr>
                <th>Correo</th>
                <td><a href="mailto:<?= h($row['email']) ?>"><?= h($row['email']) ?></a></td>
              </tr>
              <tr>
                <th>Teléfono</th>
                <td><?= h($row['telefono']) ?></td>
              </tr>
              <tr>
                <th>No. de serie</th>
                <td><?= h($row['no_serie'] ?: '—') ?></td>
              </tr>
              <tr>
                <th>No. de factura</th>
                <td><?= h($row['no_factura'] ?: '—') ?></td>
              </tr>
              <tr>
                <th>Fecha de factura</th>
                <td><?= h($row['fecha_factura'] ?: '—') ?></td>
              </tr>
              <tr>
                <th>Descripción</th>
                <td class="desc"><?= h($row['descripcion']) ?></td>
              </tr>
            </tbody>
          </table>
        </section>

        <section class="card files">
          <h2 style="margin:0 0 10px 0">Adjuntos</h2>
          <?php if (!$files): ?>
            <p class="muted">Sin archivos adjuntos.</p>
          <?php else: ?>
            <table class="kv">
              <thead>
                <tr>
                  <th>Archivo</th>
                  <th>Subido</th>
                  <th>Tamaño</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($files as $f): ?>
                  <tr>
                    <td><a href="<?= h($f['ruta']) ?>" target="_blank" rel="noopener"><?= h($f['nombre_original'] ?: basename($f['ruta'])) ?></a></td>
                    <td><?= h($f['created_at'] ? date('d/m/Y H:i', strtotime($f['created_at'])) : '—') ?></td>
                    <td><?= h(fmtSize($f['tamano'])) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </section>-->

        <?php if ($rol === 'Admin'): ?>
          <section class="card admin">
            <h2 style="margin:0 0 10px 0">Acciones (Admin)</h2>
            <form method="post">
              <input type="hidden" name="admin_update" value="1">
              <label>
                <div class="muted" style="font-size:12px">Estatus</div>
                <select name="estado">
                  <?php $opts = ['Recibida', 'En revisión', 'Enviada', 'Rechazada'];
                  foreach ($opts as $o) {
                    $sel = $row['estado'] === $o ? ' selected' : '';
                    echo "<option$sel>" . h($o) . "</option>";
                  } ?>
                </select>
              </label>
              <label>
                <div class="muted" style="font-size:12px">URL de entrega (si aplica)</div>
                <input type="url" name="url_entrega" value="<?= h($row['url_entrega']) ?>" placeholder="https://...">
              </label>
              <button type="submit">Guardar cambios</button>
            </form>
            <p class="muted" style="margin-top:8px">Si marcas <b>Enviada</b> y no tenía fecha, se registrará <b>fecha_envio = ahora</b>.</p>
          </section>
        <?php endif; ?>
      </div>
    </div>
  </main>
  <script>
    window.PROTO = {
      id: <?= (int)$row['id'] ?>,
      csrf: <?= json_encode($csrfToken) ?>,
      me: <?= (int)($uid ?? 0) ?> // <-- mi id para saber si el mensaje es mío
    };
  </script>
  <script>
    (function() {
      const list = document.getElementById('comment-list');
      const form = document.getElementById('comment-form');
      const btn = document.getElementById('btn-enviar');

      function escapeHtml(s) {
        return (s || '').replace(/[&<>"']/g, m => ({
          '&': '&amp;',
          '<': '&lt;',
          '>': '&gt;',
          '"': '&quot;',
          "'": '&#039;'
        } [m]));
      }

      function nearBottom(el, px = 80) {
        return (el.scrollHeight - el.scrollTop - el.clientHeight) <= px;
      }

      function scrollToBottom() {
        try {
          list.scrollTop = list.scrollHeight;
        } catch (_) {}
      }

      // === NUEVO: chat-bubble renderer
      function renderItem(it) {
        const meId = Number(PROTO.me || 0);
        const uid = Number(it.id_usuario ?? it.user_id ?? it.usuario_id ?? it.uid ?? 0);
        const mine = meId && uid === meId;
        const side = mine ? 'mine' : 'other';

        const who = escapeHtml(it.nombre || (mine ? 'Tú' : 'Usuario'));
        const when = escapeHtml((it.created_at || '').replace('T', ' ').slice(0, 16));
        const body = escapeHtml(it.cuerpo || '').replace(/\n/g, '<br>');

        const el = document.createElement('div');
        el.className = `msg ${side}`;
        el.setAttribute('role', 'listitem');
        el.innerHTML = `
      <div class="bubble">
        <div class="bhead">
          <span class="who">${who}</span>
          <span class="time">· ${when}</span>
        </div>
        <div class="btext">${body}</div>
      </div>`;
        return el;
      }

      async function loadComments() {
        if (!list) return;
        list.innerHTML = '<div class="comment-empty">Cargando comentarios…</div>';
        try {
          const r = await fetch(`comentarios.php?id_solicitud=${encodeURIComponent(PROTO.id)}`, {
            credentials: 'same-origin'
          });
          const d = await r.json();
          if (!d.ok) throw new Error(d.error || 'ERROR');

          if (!d.items.length) {
            list.innerHTML = '<div class="comment-empty">Aún no hay comentarios.</div>';
            return;
          }
          list.innerHTML = '';
          d.items.forEach(it => list.appendChild(renderItem(it)));
          scrollToBottom(); // ⬅️ baja al final en carga inicial
        } catch (e) {
          list.innerHTML = '<div class="comment-empty">No se pudieron cargar los comentarios.</div>';
        }
      }

      async function onSubmit(ev) {
        ev.preventDefault();
        if (!form) return;
        const fd = new FormData(form);
        const cuerpo = (fd.get('cuerpo') || '').trim();
        if (!cuerpo) {
          alert('Escribe un comentario.');
          return;
        }

        const autoscroll = nearBottom(list);

        btn.disabled = true;
        btn.textContent = 'Publicando…';
        try {
          const r = await fetch('comentarios.php', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
          });
          const d = await r.json();
          if (!d.ok) throw new Error(d.error || 'ERROR');
          if (list.querySelector('.comment-empty')) list.innerHTML = '';
          list.appendChild(renderItem(d.item));
          form.cuerpo.value = '';

          if (autoscroll) scrollToBottom(); // ⬅️ solo si estabas “abajo”
        } catch (e) {
          alert('No se pudo publicar el comentario.');
        } finally {
          btn.disabled = false;
          btn.textContent = 'Publicar';
        }
      }

      loadComments();
      if (form) form.addEventListener('submit', onSubmit);
    })();
  </script>


  <script>
    (function() {
      const modal = document.getElementById('file-modal');
      const viewer = document.getElementById('fm-viewer');
      const titleEl = document.getElementById('fm-title');
      const openEl = document.getElementById('fm-open');
      const closeEl = document.getElementById('fm-close');

      function clearViewer() {
        while (viewer.firstChild) viewer.removeChild(viewer.firstChild);
      }

      function makeEl(tag, attrs) {
        const el = document.createElement(tag);
        Object.entries(attrs || {}).forEach(([k, v]) => {
          if (k === 'text') el.textContent = v;
          else el.setAttribute(k, v);
        });
        return el;
      }

      function openPreview({
        type,
        src,
        name
      }) {
        clearViewer();

        let el;
        if (type === 'image') {
          el = makeEl('img', {
            class: 'viewer-el',
            src,
            alt: name || 'Imagen'
          });
        } else if (type === 'pdf') {
          // Iframe suele dar mejor zoom/scroll/descarga
          el = makeEl('iframe', {
            class: 'viewer-el',
            src
          });
        } else if (type === 'video') {
          el = makeEl('video', {
            class: 'viewer-el',
            src,
            controls: '',
            playsinline: ''
          });
        } else if (type === 'audio') {
          el = makeEl('audio', {
            class: 'viewer-el',
            src,
            controls: ''
          });
        } else {
          // Fallback genérico
          el = makeEl('iframe', {
            class: 'viewer-el',
            src
          });
        }
        viewer.appendChild(el);

        titleEl.textContent = name || 'Archivo';
        openEl.href = src.replace(/#.*$/, ''); // abre el archivo “limpio” en nueva pestaña
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
      }

      function closePreview() {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        clearViewer();
      }

      // Delegación de eventos para thumbs
      /*document.addEventListener('click', function(ev){
        const btn = ev.target.closest('.js-preview');
        if (!btn) return;
        ev.preventDefault();
        const type = btn.getAttribute('data-type');
        const src  = btn.getAttribute('data-src');
        const name = btn.getAttribute('data-name');
        openPreview({type, src, name});
      });

      // Cerrar
      closeEl.addEventListener('click', closePreview);
      modal.addEventListener('click', (e)=>{
        // clic fuera del diálogo
        if (e.target === modal) closePreview();
      });
      document.addEventListener('keydown', (e)=>{
        if (e.key === 'Escape' && modal.classList.contains('open')) closePreview();
      });*/
      document.addEventListener('click', (e) => {
        const btn = e.target.closest('.js-preview');
        if (!btn) return;

        const type = btn.dataset.type; // 'image' | 'pdf' | 'video' | 'audio' | 'file'
        const src = btn.dataset.src; // URL del recurso (para PDF ya viene con #view=FitH)
        const name = btn.dataset.name || 'Vista previa';

        // HTML que pondremos dentro del modal:
        let html = '';

        if (type === 'image') {
          html = `
      <img src="${src}" alt="${name}"
           style="max-width:100%;height:auto;display:block;margin:0 auto;border-radius:8px;">
    `;
        } else if (type === 'pdf') {
          html = `
      <iframe src="${src}"
              title="${name}"
              style="width:100%;height:70vh;border:none;border-radius:8px;"></iframe>
    `;
        } else if (type === 'video') {
          html = `
      <video src="${src}" controls
             style="width:100%;height:auto;display:block;border-radius:8px;"></video>
    `;
        } else if (type === 'audio') {
          html = `
      <audio src="${src}" controls
             style="width:100%;display:block;"></audio>
    `;
        } else {
          // Otros archivos: solo mostramos info y un botón para abrir/descargar
          html = `
      <div style="text-align:center">
        <p style="margin-bottom:1rem;">No hay vista previa disponible para <strong>${name}</strong>.</p>
        <a class="swal2-confirm swal2-styled" href="${src}" target="_blank" rel="noopener">
          Abrir / Descargar
        </a>
      </div>
    `;
        }
      
      
        Swal.fire({
          html: `
    <div style="margin-bottom:10px;font-size:14px;color:#fff;">
      ${name}
    </div>
    ${html} <!-- Aquí va la vista previa (imagen, pdf, video...) -->
    <div style="margin-top:15px;text-align:center;">
      <a href="${src}" download
         style="display:inline-block;padding:8px 16px;background:#ed6b1f;color:#fff;
                text-decoration:none;border-radius:6px;font-size:14px;">
        Descargar
      </a>
    </div>
  `,
          showConfirmButton: false,
          showCloseButton: true,
          width: '40vw',
          customClass: {
            popup: 'ga-preview-popup'
          }
        });
       });
     
      



      
    })();

  </script>

  <style>
     .ga-preview-popup {
      max-width: 1200px;
      /* en pantallas grandes no pasa de aquí */
    }

    @media (max-width: 768px) {
      .ga-preview-popup {
        width: 95vw !important;
        /* en móviles casi todo el ancho */
      }
    }

    @media (min-width: 769px) and (max-width: 1024px) {
  .ga-preview-popup {
    width: 85vw !important;     /* ocupa ~85% del ancho */
    max-width: 900px;           /* límite más reducido que desktop */
    padding: 1rem !important;   /* respiración */
  }
  .ga-preview-title {
    font-size: 15px;            /* un poco más visible que en móvil */
    margin-bottom: 12px;
  }
  .ga-preview-frame {
    max-height: 70vh;           /* evita scroll muy largo */
  }
  .ga-preview-media {
    max-height: 65vh;
  }
  .ga-preview-download {
    padding: 10px 20px;
    font-size: 15px;
  }
}
  </style>


  <style>
    /* Visor dentro de SweetAlert2 */
    .swal2-popup.viewer {
      padding: 0;
      overflow: hidden;
    }

    .swal2-title.viewer-title {
      font-size: 14px;
      font-weight: 800;
      margin: 10px 12px 0;
      text-align: left;
    }

    .swal2-html-container.viewer-html {
      margin: 0;
      padding: 0;
    }

    .viewer-wrap {
      width: min(96vw, 980px);
      height: min(90vh, 720px);
      background: #111827;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .viewer-el {
      max-width: 100%;
      max-height: 100%;
      width: 100%;
      height: 100%;
      object-fit: contain;
      background: #111827;
      border: 0;
      outline: none;
    }
  </style>

  <script>
    (function() {
      const esc = (s) => (s || '').replace(/[&<>"']/g, m => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;'
      } [m]));

      function contentForPreview(type, src, name) {
        if (type === 'image') return `<div class="viewer-wrap"><img class="viewer-el" src="${esc(src)}" alt="${esc(name||'Imagen')}"></div>`;
        if (type === 'pdf') return `<div class="viewer-wrap"><iframe class="viewer-el" src="${esc(src)}"></iframe></div>`;
        if (type === 'video') return `<div class="viewer-wrap"><video class="viewer-el" src="${esc(src)}" controls playsinline></video></div>`;
        if (type === 'audio') return `<div class="viewer-wrap"><audio class="viewer-el" src="${esc(src)}" controls></audio></div>`;
        return `<div class="viewer-wrap"><iframe class="viewer-el" src="${esc(src)}"></iframe></div>`;
      }

      async function openSwalPreview({
        type,
        src,
        name
      }) {
        const clean = (src || '').replace(/#.*$/, '');
        const res = await Swal.fire({
          title: esc(name || 'Archivo'),
          html: contentForPreview(type, src, name),
          focusConfirm: false,
          showConfirmButton: true,
          confirmButtonText: 'Abrir en pestaña',
          showDenyButton: true,
          denyButtonText: 'Descargar',
          showCancelButton: true,
          cancelButtonText: 'Cerrar',
          width: 'auto',
          customClass: {
            popup: 'viewer',
            title: 'viewer-title',
            htmlContainer: 'viewer-html'
          }
        });
        if (res.isConfirmed) {
          window.open(clean, '_blank', 'noopener');
        } else if (res.isDenied) {
          const a = document.createElement('a');
          a.href = clean;
          if (name) a.download = name;
          document.body.appendChild(a);
          a.click();
          a.remove();
        }
      }

      // Delegación: miniaturas .js-preview (ya las generas en tus tarjetas)
     /* document.addEventListener('click', (ev) => {
        const btn = ev.target.closest('.js-preview');
        if (!btn) return;
        ev.preventDefault();
        openSwalPreview({
          type: btn.getAttribute('data-type'),
          src: btn.getAttribute('data-src'),
          name: btn.getAttribute('data-name')
        });
      });*/











    })();
  </script>

</body>

</html>
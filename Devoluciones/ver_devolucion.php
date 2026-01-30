<?php

declare(strict_types=1);
date_default_timezone_set('America/Mexico_City');
require_once __DIR__ . '/../app/session_boot.php'; // <-- ya lo tienes
require_login();                                   // <-- agrega esta línea
// <-- primera línea visible

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/db.php'; // $pdo


function back_to_index(string $msg = ''): void
{
  // Lleva el mensaje como query param opcional (?msg=...)
  $url = './index.php';
  if ($msg !== '') {
    $url .= '?msg=' . urlencode($msg);
  }
  header('Location: ' . $url, true, 302);
  exit;
}

function h($s)
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function fdt($s)
{
  if (!$s) return '—';
  $t = strtotime($s);
  return $t ? date('d/m/Y g:i A', $t) : h($s);
}
function status_label_cliente(int $code): string
{
  if ($code === 0) return 'Enviado';
  if (in_array($code, [1, 3], true)) return 'En proceso';
  if ($code === 2) return 'Pase a tienda';

  if ($code === 4) return 'Aprobado';
  if (in_array($code, [10, 20, 30], true)) return 'Cancelado';
  return '—';
}

function estatusBadgeCliente(int $code): string
{
  $label = status_label_cliente($code);
  if ($code === 0)                    return '<span class="status enviado"><span class="dot"></span>' . h($label) . '</span>';
  if (in_array($code, [1, 2, 3], true)) return '<span class="status proceso"><span class="dot"></span>' . h($label) . '</span>';

  if ($code === 4)                    return '<span class="status nota"><span class="dot"></span>' . h($label) . '</span>';
  if (in_array($code, [10, 20, 30], true)) return '<span class="status cancelado"><span class="dot"></span>' . h($label) . '</span>';
  return '<span class="status"><span class="dot"></span>' . h($label) . '</span>';
}


// 1) Validar id
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  back_to_index('Folio inválido o no proporcionado.');
}

// 2) Cargar devolución
$st = $pdo->prepare("
  SELECT 
    d.*,
    u.username, 
    u.avatar,
    -- Etiqueta amigable del vendedor (si existe vendedor_id)
    COALESCE(NULLIF(v.nombre_factura, ''), NULLIF(v.nombre_completo, '')) AS vendedor_nombre,
    v.telefono AS vendedor_tel
  FROM devoluciones d
  LEFT JOIN usuarios u       ON u.ID = d.user_id
  LEFT JOIN usuarios_cache v ON v.id_usuario = d.vendedor_id
  WHERE d.id = ?
  LIMIT 1
");
$st->execute([$id]);
$dev = $st->fetch(PDO::FETCH_ASSOC);

if (!$dev) {
  back_to_index('No se encontró la devolución solicitada.');
}

// 3) Adjuntos
$sta = $pdo->prepare("
  SELECT id, ruta, tipo, original, created_at
  FROM devolucion_adjuntos
  WHERE devolucion_id = ?
  ORDER BY id ASC
");
$sta->execute([$id]);
$adjuntos = $sta->fetchAll(PDO::FETCH_ASSOC);
// ... justo después de cargar $dev y $adjuntos
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];
$uid  = $_SESSION['user_id'] ?? null;       // para mostrar el form solo a logueados
// === Autorización: solo Admin o dueño de la devolución ===
$uid = $_SESSION['user_id'] ?? $_SESSION['ID'] ?? $_SESSION['idUsuario'] ?? null;
$rol = $_SESSION['Rol'] ?? '';
$isAdmin = ($rol === 'Admin');
$isOwner = $uid && (int)$dev['user_id'] === (int)$uid;

if (!($isAdmin || $isOwner)) {
  // Mensaje neutro (no revela si existe o no)
  back_to_index('La devolución no existe o no tienes permiso para verla.');
}

$LOCK_STATUSES = [10, 20, 30, 5];
$isLocked = in_array((int)$dev['estatus'], $LOCK_STATUSES, true);


?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="icon" href="..\assets\img\iconpestalla.png" type="image/x-icon">
  <title>Devolución <?= h($dev['folio']) ?></title>
  <link rel="stylesheet" href="./Estilos/styles_index.css?v=1.1">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

    .page {
      max-width: 1100px;
      margin: 0 auto;
      padding: 18px;
    }

    .breadcrumb {
      display: flex;
      align-items: center;
      margin: 8px 0 16px;
    }

    .breadcrumb a {

      text-decoration: none;
    }

    .hdr {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
    }

    .hdr h1 {
      margin: 0;
      font-size: clamp(20px, 2.4vw, 28px);
    }

    .meta {
      color: #4b5563;
      margin-top: 6px;
    }

    .grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 16px;
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
      color: var(--ga-blue-600);
    }

    .kv {
      display: grid;
      grid-template-columns: 200px 1fr;
      gap: 8px 12px;
    }

    .kv div {
      padding: 4px 0;
      border-bottom: 1px dotted #eee;
    }

    .kv div:nth-child(2n) {
      border-bottom: 1px dotted #eee;
    }

    .motivo {
      white-space: pre-wrap;
    }

    .gal {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
      gap: 14px;
    }

    .tile {
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      overflow: hidden;
      background: #fafafa;
    }

    .tile .cap {
      padding: 10px;
      font-size: 13px;
      color: #374151;
      display: flex;
      justify-content: space-between;
      gap: 10px;
      align-items: center;
    }

    .tile a {
      text-decoration: none;
    }

    .status {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 6px 10px;
      border-radius: 999px;
      font-weight: 600;
      font-size: 12px
    }

    .status .dot {
      width: 8px;
      height: 8px;
      border-radius: 999px;
      display: inline-block
    }

    .status.proceso {
      background: #fff7ed;
      color: #b45309;
      border: 1px solid #fde68a
    }

    .status.proceso .dot {
      background: #f59e0b
    }

    .status.aprobado {
      background: #ecfeff;
      color: #0e7490;
      border: 1px solid #a5f3fc
    }

    .status.aprobado .dot {
      background: #06b6d4
    }

    .status.rechazado {
      background: #fef2f2;
      color: #b91c1c;
      border: 1px solid #fecaca
    }

    .status.rechazado .dot {
      background: #ef4444
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

    .watermark {
      position: absolute;
      right: -20px;
      top: -20px;
      opacity: .06;
      width: 320px;
      height: 320px
    }

    @media (max-width: 900px) {
      .grid {
        grid-template-columns: 1fr
      }

      .kv {
        grid-template-columns: 150px 1fr
      }
    }

    .two {
      display: grid;
      grid-template-columns: 340px 1fr;
      gap: 16px;
      margin-top: 18px;
    }

    @media (max-width: 900px) {
      .two {
        grid-template-columns: 1fr
      }
    }

    .comments-card {
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 16px;
      padding: 14px;
    }

    .comments-title {
      margin: 0 0 8px;
      font-weight: 800;
      font-size: 16px;
      color: #0b1b34;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .comment-list {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .comment-item {
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      padding: 10px;
      background: #fafafa;
    }

    .comment-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 6px;
    }

    .comment-author {
      display: flex;
      align-items: center;
      gap: 8px;
      font-weight: 700;
      color: #0b1b34;
    }

    .badge-rol {
      font-size: 11px;
      padding: 2px 8px;
      border-radius: 999px;
      background: #ecf2fb;
      color: #0b5fa3;
      font-weight: 700;
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
      padding: 12px;
      text-align: center;
    }

    .comment-form {
      margin-top: 10px;
      border-top: 1px solid #eef0f4;
      padding-top: 10px;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .comment-form textarea {
      width: 100%;
      min-height: 90px;
      padding: 10px;
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      resize: vertical;
      font: 14px/1.4 system-ui, sans-serif;
    }

    .btn-primary {
      background: #0b5fa3;
      color: #fff;
      border: 0;
      border-radius: 4px;
      padding: 10px 14px;
      font-weight: 700;
      cursor: pointer;
      margin-left: auto;
    }

    .btn-primary:disabled {
      opacity: .6;
      cursor: not-allowed;
    }

    .help {
      font-size: 12px;
      color: #64748b
    }

    /* La tarjeta de comentarios en columna */
    .comments-card {
      display: flex;
      flex-direction: column;
    }

    /* La lista scrollea y tiene un tope de altura */
    .comment-list {
      flex: 1 1 auto;
      /* ocupa el espacio disponible */
      max-height: clamp(220px, 40vh, 480px);
      /* límite responsivo */
      overflow-y: auto;
      /* scroll vertical */
      padding-right: 6px;
      /* respiro para la barra */
      overscroll-behavior: contain;
      /* evita arrastrar el body al final */
    }

    /* Opcional: el área vacía no se estira demasiado */
    .comment-empty {
      min-height: 90px;
    }

    /* === Chat look & feel === */
    .comment-list.chat {
      display: flex;
      flex-direction: column;
      gap: 10px;
      max-height: clamp(260px, 45vh, 520px);
      overflow-y: auto;
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

    .status.enviado {
      background: #ecf3ff;
      color: #0b5fa3;
      border: 1px solid #cfe1ff
    }

    .status.enviado .dot {
      background: #0d6efd
    }

    .status.proceso {
      background: #fff7ed;
      color: #b45309;
      border: 1px solid #fde68a
    }

    /* ya la tienes, ok */
    .status.proceso .dot {
      background: #f59e0b
    }

    .status.nota {
      background: #ecfdf5;
      color: #047857;
      border: 1px solid #a7f3d0
    }

    .status.nota .dot {
      background: #20c997
    }

    .status.cancelado {
      background: #fef2f2;
      color: #b91c1c;
      border: 1px solid #fecaca
    }

    .status.cancelado .dot {
      background: #dc3545
    }

    /* Botón de miniatura dentro de .tile */
    .tile-thumb {
      display: block;
      width: 100%;
      height: 200px;
      padding: 0;
      border: 0;
      cursor: pointer;
      background: #f3f4f6;
    }

    .tile-fallback {
      width: 100%;
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(180deg, #f8fafc, #eef2f7);
    }

    .tile-fallback .tf-ext {
      font-weight: 800;
      letter-spacing: .6px;
      color: #0b5fa3;
      background: #eaf2fd;
      border: 1px solid #cfe1fb;
      padding: 6px 10px;
      border-radius: 999px;
    }

    /* === Modal genérico (igual que el de protocolos) === */
    #file-modal {
      position: fixed;
      inset: 0;
      z-index: 9999;
      background: rgba(15, 23, 42, .6);
      display: none;
    }

    #file-modal.open {
      display: block;
    }

    #file-modal .dialog {
      position: absolute;
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

    #file-modal .viewer {
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

    @media (max-width:640px) {
      #file-modal .dialog {
        width: 95vw;
        height: 80vh;
      }
    }

    .ga-preview-title {
      font-size: 15px !important;
      /* más pequeño */
      font-weight: normal;
      /* opcional, para que no sea tan cargado */
      color: #ffffffff;
      /* opcional */
    }

    .ga-preview-popup {
      max-width: 1200px;
      /* en pantallas grandes no pasa de aquí */
    }

    @media (min-width: 769px) and (max-width: 1024px) {
      .ga-preview-popup {
        width: 85vw !important;
        /* ocupa ~85% del ancho */
        max-width: 900px;
        /* límite más reducido que desktop */
        padding: 1rem !important;
        /* respiración */
      }

      .ga-preview-title {
        font-size: 15px;
        /* un poco más visible que en móvil */
        margin-bottom: 12px;
      }

      .ga-preview-frame {
        max-height: 70vh;
        /* evita scroll muy largo */
      }

      .ga-preview-media {
        max-height: 65vh;
      }

      .ga-preview-download {
        padding: 10px 20px;
        font-size: 15px;
      }
    }

    @media (max-width: 768px) {
      .ga-preview-popup {
        width: 95vw !important;
        /* en móviles casi todo el ancho */
      }
    }

    /* Móvil */
    @media (max-width: 768px) {
      .ga-preview-popup {
        width: 96vw !important;
        /* casi todo el ancho en móvil */
        padding: 0 !important;
      }

      .ga-preview-title {
        font-size: 13px;
        margin-bottom: 8px;
      }

      .ga-preview-frame {
        max-height: 76vh;
        /* un pelín más alto en móvil */
      }

      .ga-preview-media {
        max-height: 72vh;
        /* y el media ligeramente más alto */
      }

      .ga-preview-download {
        width: 100%;
        /* botón full-width para dedo gordo */
        text-align: center;
      }
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
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}
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

  <main id="app" class="page">
    <nav class="breadcrumb">
      <a class="btn-sm" href="./index.php">Volver al historial</a>
    </nav>

    <header class="hdr">
      <div>
        <h1>Devolución <?= h($dev['folio']) ?></h1>
        <div class="meta">
          <span>Creada: <?= fdt($dev['created_at']) ?></span> ·
          <span>Actualizada: <?= fdt($dev['updated_at']) ?></span> ·
          <span>Usuario: <?= h($dev['username'] ?: '—') ?></span>
        </div>
      </div>
      <div><?= estatusBadgeCliente((int)$dev['estatus']) ?></div>
    </header>

    <!--<svg class="watermark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M3 7l9 4 9-4M3 7l9-4 9 4M3 7v10l9 4 9-4V7" />
      <path d="M12 11v10" />
    </svg>-->

    <section class="two">
      <!-- Columna izquierda: Comentarios -->
      <aside class="comments-card" aria-labelledby="comments-title">
        <h3 class="comments-title" id="comments-title">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0b5fa3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M21 15a4 4 0 0 1-4 4H7l-4 4V7a4 4 0 0 1 4-4h6" />
            <path d="M17 3h4v4" />
            <path d="M16 8l5-5" />
          </svg>
          Comentarios
        </h3>

        <div id="comment-list" class="comment-list chat" role="list"></div>

        <?php if (!empty($uid)) : ?>
          <form id="comment-form" class="comment-form" autocomplete="off">
            <textarea id="cuerpo" name="cuerpo" placeholder="Escribe un comentario…"></textarea>
            <div style="display:flex;align-items:center;gap:10px">
              <button class="btn-primary" id="btn-enviar" type="submit">Publicar</button>
              <!--<span class="help">Se mostrará con tu nombre de usuario.</span>-->
            </div>
            <input type="hidden" name="id_devolucion" value="<?= (int)$dev['id'] ?>">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
          </form>
        <?php else: ?>
          <div class="comment-empty">Inicia sesión para publicar comentarios.</div>
        <?php endif; ?>
      </aside>

      <!-- Columna derecha: Detalle (tus tarjetas actuales) -->
      <div>
        <article class="card">
          <h3>Información del producto</h3>
          <div class="kv">
            <div><b>Nombre del producto</b></div>
            <div><?= h($dev['nombre_producto'] ?: '—') ?></div>
            <div><b>Marca</b></div>
            <div><?= h($dev['marca'] ?: '—') ?></div>
            <div><b>No. de serie</b></div>
            <div><?= h($dev['no_serie'] ?: '—') ?></div>
            <div><b>Código en factura</b></div>
            <div><?= h($dev['codigo_factura'] ?: '—') ?></div>
            <div><b>No. de factura</b></div>
            <div><?= h($dev['no_factura'] ?: '—') ?></div>
            <div><b>Sucursal</b></div>
            <div><?= h($dev['sucursal'] ?: '—') ?></div>
            <div><b>Vendedor que lo atendió</b></div>
<div><?= h($dev['vendedor_nombre'] ?? '—') ?></div>

<?php if (!empty($dev['vendedor_tel'])): ?>
  <div><b>Teléfono del vendedor</b></div>
  <div><?= h($dev['vendedor_tel']) ?></div>
<?php endif; ?>
            
          </div>
        </article>

        <article class="card">
          <h3>Contacto</h3>
          <div class="kv">
            <div><b>Nombre</b></div>
            <div><?= h($dev['contacto_nombre'] ?: '—') ?></div>
            <div><b>Teléfono</b></div>
            <div><?= h($dev['contacto_tel'] ?: '—') ?></div>
            <div><b>Correo</b></div>
            <div><?= h($dev['contacto_email'] ?: '—') ?></div>
          </div>
        </article>

        <article class="card">
          <h3>Motivo de la devolución</h3>
          <div class="motivo"><?= h($dev['motivo'] ?: '—') ?></div>
        </article>

        <article class="card">
          <h3>Adjuntos (<?= count($adjuntos) ?>)</h3>
          <?php if (!$adjuntos): ?>
            <p class="meta">No hay archivos adjuntos.</p>
          <?php else: ?>
            <div class="gal">
              <?php foreach ($adjuntos as $a):
                $ruta = (string)$a['ruta'];
                $url  = $ruta;
                $orig = $a['original'] ?: basename($ruta);
                $tipo = (string)$a['tipo']; // 'foto', 'video' u otros

                // Detecta por extensión para PDF/audio/otros
                $path = parse_url($ruta, PHP_URL_PATH) ?? '';
                $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $isImg   = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'avif'], true) || $tipo === 'foto';
                $isPdf   = ($ext === 'pdf');
                $isVideo = in_array($ext, ['mp4', 'webm', 'ogg'], true) || $tipo === 'video';
                $isAudio = in_array($ext, ['mp3', 'ogg', 'wav', 'm4a'], true);
                $prevType = $isImg ? 'image' : ($isPdf ? 'pdf' : ($isVideo ? 'video' : ($isAudio ? 'audio' : 'file')));
                $previewSrc = $isPdf ? ($url . '#view=FitH') : $url;
              ?>
                <div class="tile">
                  <button class="tile-thumb js-preview"
                    data-type="<?= h($prevType) ?>"
                    data-src="<?= h($previewSrc) ?>"
                    data-name="<?= h($orig) ?>"
                    aria-label="Vista previa: <?= h($orig) ?>">
                    <?php if ($isImg): ?>
                      <img src="<?= h($url) ?>" alt="<?= h($orig) ?>" style="width:100%;height:200px;object-fit:cover;display:block">
                    <?php else: ?>
                      <div class="tile-fallback">
                        <div class="tf-ext"><?= strtoupper($ext ?: 'FILE') ?></div>
                      </div>
                    <?php endif; ?>
                  </button>

                  <div class="cap">
                    <span title="<?= h($orig) ?>" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:70%"><?= h($orig) ?></span>
                    <a class="btn-sm" href="<?= h($url) ?>" download>Descargar</a>
                  </div>
                </div>
              <?php endforeach; ?>

            </div>
          <?php endif; ?>
        </article>
      </div>
    </section>

  </main>
  <script>
    window.DEV = {
      id: <?= (int)$dev['id'] ?>,
      csrf: <?= json_encode($csrf) ?>,
      me: <?= (int)($uid ?? 0) ?> // 👈 mi id para alinear burbujas

    };
  </script>

  <script>
    (function() {
      const list = document.getElementById('comment-list');
      const form = document.getElementById('comment-form');
      const btn = document.getElementById('btn-enviar');

      let lastId = 0;
      let stop = false; // por si quieres pausar con visibilitychange
      let backoff = 1200; // backoff en errores

      function esc(s) {
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

      function renderItem(it) {
        const meId = Number(DEV.me || 0);
        const uid = Number(it.id_usuario ?? it.user_id ?? it.usuario_id ?? it.uid ?? 0);
        const mine = meId && uid === meId;
        const side = mine ? 'mine' : 'other';

        const who = esc(it.nombre || (mine ? 'Tú' : 'Usuario'));
        const when = esc((it.created_at || '').replace('T', ' ').slice(0, 16));
        const body = esc(it.cuerpo || '').replace(/\n/g, '<br>');

        const el = document.createElement('div');
        el.className = `msg ${side}`;
        el.dataset.id = String(it.id || '');
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

      async function initialLoad() {
        list.innerHTML = '<div class="comment-empty">Cargando comentarios…</div>';
        try {
          const r = await fetch(`comentarios.php?id_devolucion=${encodeURIComponent(DEV.id)}`, {
            credentials: 'same-origin'
          });
          const d = await r.json();
          if (!d.ok) {
            throw new Error(d.error || 'ERROR');
          }

          if (!d.items?.length) {
            list.innerHTML = '<div class="comment-empty">Aún no hay comentarios.</div>';
            lastId = d.last_id || 0; // por si el API lo manda aunque no haya items
            return;
          }

          list.innerHTML = '';
          const shouldScroll = true; // primera carga: baja al final
          d.items.forEach(it => list.appendChild(renderItem(it)));
          // lastId: del API o del último DOM
          lastId = Number(d.last_id || list.lastElementChild?.dataset.id || 0);
          if (shouldScroll) scrollToBottom();

          // Arranca el long-polling
          poll();
        } catch (e) {
          list.innerHTML = '<div class="comment-empty">No se pudieron cargar los comentarios.</div>';
          console.error(e);
        }
      }

      async function poll() {
        if (stop) return;
        try {
          const params = new URLSearchParams({
            id_devolucion: String(DEV.id),
            after_id: String(lastId || 0),
            timeout: '20' // espera hasta 20s si no hay nuevos
          });
          const r = await fetch(`comentarios.php?${params.toString()}`, {
            credentials: 'same-origin'
          });
          const d = await r.json();

          if (d?.ok) {
            // si hay nuevos, se insertan
            if (Array.isArray(d.items) && d.items.length) {
              const autoscroll = nearBottom(list);
              d.items.forEach(it => list.appendChild(renderItem(it)));
              lastId = Number(d.last_id || list.lastElementChild?.dataset.id || lastId);
              if (autoscroll) scrollToBottom();
            } else {
              // sin nuevos: last_id puede venir igual
              if (d.last_id) lastId = Number(d.last_id);
            }
            backoff = 300; // rápido si todo bien
          } else {
            // algo respondió mal
            backoff = Math.min(backoff * 1.6, 6000);
          }
        } catch (e) {
          console.warn('poll error', e);
          backoff = Math.min(backoff * 1.6, 6000);
        } finally {
          if (!stop) setTimeout(poll, backoff);
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
          lastId = Math.max(Number(lastId || 0), Number(d.item?.id || 0));
          if (autoscroll) scrollToBottom();
        } catch (e) {
          alert('No se pudo publicar el comentario.');
          console.error(e);
        } finally {
          btn.disabled = false;
          btn.textContent = 'Publicar';
        }
      }

      // Opcional: pausar/reanudar cuando cambie la visibilidad de la pestaña
      document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
          stop = true;
        } else {
          stop = false;
          poll();
        }
      });

      // Go!
      initialLoad();
      if (form) form.addEventListener('submit', onSubmit);
    })();
  </script>


  <div id="file-modal" aria-hidden="true">
    <div class="dialog" role="dialog" aria-modal="true" aria-label="Vista previa de archivo">
      <div class="bar">
        <div class="title" id="fm-title">Archivo</div>
        <div class="spacer"></div>
        <a id="fm-open" class="btn" href="#" target="_blank" rel="noopener">Abrir en pestaña</a>
        <button id="fm-close" class="btn" type="button">Cerrar</button>
      </div>
      <div class="body">
        <div class="viewer" id="fm-viewer"></div>
      </div>
    </div>
  </div>

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
          k === 'text' ? el.textContent = v : el.setAttribute(k, v);
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
          el = makeEl('iframe', {
            class: 'viewer-el',
            src
          });
        }
        viewer.appendChild(el);
        titleEl.textContent = name || 'Archivo';
        openEl.href = src.replace(/#.*$/, ''); // limpia anchors tipo #view=FitH
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
      }

      function closePreview() {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        clearViewer();
      }

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

      // Delegación: clicks en cualquier .js-preview
      /*document.addEventListener('click', (ev) => {
        const btn = ev.target.closest('.js-preview');
        if (!btn) return;
        ev.preventDefault();
        openPreview({
          type: btn.getAttribute('data-type'),
          src: btn.getAttribute('data-src'),
          name: btn.getAttribute('data-name')
        });
      });

      closeEl.addEventListener('click', closePreview);
      modal.addEventListener('click', (e) => {
        if (e.target === modal) closePreview();
      });
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.classList.contains('open')) closePreview();
      });*/


    })();
  </script>

  <style>
    .ga-preview-popup {
  max-width: 1200px; /* en pantallas grandes no pasa de aquí */
}

@media (max-width: 768px) {
  .ga-preview-popup {
    width: 95vw !important; /* en móviles casi todo el ancho */
  }
}
    
  </style>

</body>

</html>

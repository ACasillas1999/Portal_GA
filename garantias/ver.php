<?php
// garantias/ver.php — Detalle de Garantía (solo lectura)
ini_set('session.cookie_httponly', '1');
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
  ini_set('session.cookie_secure', '0');
} else {
  ini_set('session.cookie_secure', '1');
}
session_name('GA');
session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/db.php';

function h($s)
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function v($s)
{
  return ($s !== null && $s !== '') ? h($s) : '—';
}

// === Mapeo de estado (vista CLIENTE) ===
// 0 -> Enviado
// 1..5 -> En proceso
// 10/20/30/40/50 -> Cancelado
// 51 -> Nota de crédito
// 52 -> Reemplazo
function status_label_cliente_g(int $code): string
{
  if ($code === 0) return 'Enviado';
  if (in_array($code, [1, 2, 3, 4, 5], true)) return 'En proceso';
  if ($code === 51) return 'Nota de crédito';
  if ($code === 52) return 'Reemplazo';
  if (in_array($code, [10, 20, 30, 40, 50], true)) return 'Cancelado';
  return '—';
}

function status_class_cliente_g(int $code): string
{
  if ($code === 0) return 'enviado';
  if (in_array($code, [1, 2, 3, 4, 5], true)) return 'proceso';
  if ($code === 51 || $code === 52) return 'nota';
  if (in_array($code, [10, 20, 30, 40, 50], true)) return 'cancelado';
  return 'proceso';
}


$id    = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$folio = trim($_GET['folio'] ?? '');
if (!$id && $folio === '') {
  http_response_code(400);
  exit('Falta parámetro id o folio.');
}

$uid   = $_SESSION['user_id'] ?? null;
$rol   = $_SESSION['Rol'] ?? '';
$email = $_SESSION['Email'] ?? ($_SESSION['Correo'] ?? null);

/* ——— Consultas SOLO con placeholders nombrados ——— */
if ($id) {
  if ($rol === 'Admin') {
    $st = $pdo->prepare("SELECT * FROM garantia_solicitudes WHERE id=:id LIMIT 1");
    $st->execute([':id' => $id]);
  } else {
    $st = $pdo->prepare("
      SELECT * FROM garantia_solicitudes
      WHERE id=:id AND (id_usuario = :uid OR email = :email)
      LIMIT 1
    ");
    $st->execute([':id' => $id, ':uid' => $uid, ':email' => $email]);
  }
} else { // por folio
  if ($rol === 'Admin') {
    $st = $pdo->prepare("SELECT * FROM garantia_solicitudes WHERE folio=:folio LIMIT 1");
    $st->execute([':folio' => $folio]);
  } else {
    $st = $pdo->prepare("
      SELECT * FROM garantia_solicitudes
      WHERE folio=:folio AND (id_usuario = :uid OR email = :email)
      LIMIT 1
    ");
    $st->execute([':folio' => $folio, ':uid' => $uid, ':email' => $email]);
  }
}

$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row) {
  http_response_code(404);
?>
  <!DOCTYPE html>
  <html lang="es">

  <head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="3;url=index.php">
    <link rel="icon" href="/portal_ga/assets/img/iconpestalla.png" type="image/x-icon">
    <title>No encontrado</title>
    <style>
      body {
        background: #f8fafc;
        font-family: Arial, sans-serif;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
        margin: 0;
      }

      .mensaje {
        padding: 20px;
        background: #fee2e2;
        border: 1px solid #fecaca;
        border-radius: 5px;
        color: #991b1b;
        text-align: center;
        font-size: 1.2em;
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
    <div class="mensaje">No encontrado o sin permiso. Redirigiendo al índice...</div>
  </body>

  </html>
<?php
  exit;
}

// Adjuntos
$st2 = $pdo->prepare("SELECT id, ruta,nombre_original, tamano FROM garantia_adjuntos WHERE id_solicitud=:id ORDER BY id ASC");
$st2->execute([':id' => $row['id']]);
$adj = $st2->fetchAll(PDO::FETCH_ASSOC);



/* Campos para vista */
$folio          = $row['folio'];
$fechaRegistro  = date('Y-m-d', strtotime($row['created_at']));
$correoRem      = $row['email'];
$producto       = $row['producto'];
$noSerie        = $row['no_serie'];
$codigoFactura  = $row['codigo_factura'];
$noFactura      = $row['no_factura'];
$fechaFactura   = $row['fecha_factura'];
$marca          = $row['marca'];
$descFalla      = $row['descripcion_falla'];
$nombreCto      = $row['nombre_contacto'];
$tel            = $row['telefono'];
$sucursal       = $row['sucursal'];
$codeEstado     = (int)($row['estado'] ?? 0);          // 👈 numérico
$labelCli       = status_label_cliente_g($codeEstado);  // 👈 texto para el cliente
$clsCli         = status_class_cliente_g($codeEstado);

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="icon" href="/portal_ga/assets/img/iconpestalla.png" type="image/x-icon">
  <title>Garantía <?= h($folio) ?></title>
  <link rel="stylesheet" href="./Estilos/style.css?v=1.0">
  <link rel="stylesheet" href="./Estilos/styles_index.css?v=1.1">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <style>
    :root {
      --ga-blue: #0b5fa3;
      --ink: #0b1b34;
      --font-sans: "Montserrat", system-ui, -apple-system, "Segoe UI", Roboto,
        "Helvetica Neue", Arial, "Noto Sans", sans-serif;
    }

    * {
      box-sizing: border-box;
      font-family: var(--font-sans) !important;
    }

    .wrap {


      /*width: min(1100px, calc(100% - 40px));
      margin: 22px auto*/
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
      color: var(--ink) !important;
      margin: 0;
      font-size: clamp(20px, 2.4vw, 28px);
    }

    .meta {
      color: #4b5563;
      margin-top: 6px;
    }


    .back {
      display: inline-block;
      margin: 4px 0 14px;
      color: var(--ga-blue);
      text-decoration: none;
      font-weight: 700
    }

    .card {
      background: #fff;
      border-radius: 18px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, .08);
      padding: 18px 20px
    }

    .title-row {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 8px
    }

    .logo-box {
      width: 42px;
      height: 42px;
      border-radius: 12px;
      display: grid;
      place-items: center;
      background: #ecf2fb
    }

    .title {
      margin: 0;
      font: 800 24px/1.2 ui-sans-serif, system-ui, "Segoe UI", Roboto, Arial;
      color: var(--ink)
    }

    .folio {
      font-weight: 800
    }

    .status {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 10px;
      border-radius: 999px;
      font-weight: 700;
      font-size: 12px;
      margin-left: 10px
    }

    .status.proceso {
      background: #fef3c7;
      color: #92400e
    }

    .status.aprobado {
      background: #d1fae5;
      color: #065f46
    }

    .status.rechazado {
      background: #fee2e2;
      color: #991b1b
    }

    .detail {
      width: 100%;
      border-collapse: collapse;
      margin-top: 8px;
      min-width: 0px;
    }

   h3{
     margin: 0 0 12px;
    font: 800 20px / 1.2 ui-sans-serif, system-ui, "Segoe UI", Roboto;
    color: #0b1b34;
    display: flex
;
    align-items: center;
    gap: 8px;
    }

    .detail th,
    .detail td {
      padding: 10px 12px;
      border-bottom: 1px solid #eef0f4;
      vertical-align: top
    }

    .detail th {
      width: 280px;
      background: #f9fafb;
      text-align: left;
      font-weight: 700;
      color: #374151
    }

    .detail tr:last-child th,
    .detail tr:last-child td {
      border-bottom: 0
    }

    .files {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin-top: 12px
    }

    .file {
      display: inline-flex;
      gap: 8px;
      align-items: center;
      padding: 8px 12px;
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      background: #fff;
      text-decoration: none;
      color: #0b1b34
    }

    .legend {
      margin-top: 14px;
      padding: 12px 14px;
      background: #f0f9ff;
      border-left: 4px solid #0ea5e9;
      color: #083344;
      border-radius: 8px
    }

    .watermark {
      position: fixed;
      right: 24px;
      top: 24px;
      width: 160px;
      height: 160px;
      color: #e5eefb;
      pointer-events: none
    }

    .comments-card {
      background: #fff;
      border-radius: 18px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, .08);
      padding: 18px 20px;
      margin-top: 16px
    }

    .comments-title {
      margin: 0 0 12px;
      font: 800 20px/1.2 ui-sans-serif, system-ui, "Segoe UI", Roboto;
      color: #0b1b34;
      display: flex;
      align-items: center;
      gap: 8px
    }

    .comment-list {
      display: flex;
      flex-direction: column;
      gap: 12px;
      margin-top: 8px
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
      gap: 10px
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
      align-items: center;
    }

    .btn-primary {
      background: var(--ink);
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
      cursor: not-allowed
    }

    .help {
      font-size: 12px;
      color: #64748b
    }

    /* ===== Layout dos columnas ===== */
    .cols {
      display: grid;
      grid-template-columns: 1fr 1.15fr;
      /* Izq: chat | Der: detalle */
      gap: 18px;
      align-items: start;
    }

    /* La tarjeta de comentarios se comporta como panel con scroll en la lista */
    .col-left .comments-card {
      display: flex;
      flex-direction: column;
      max-height: calc(100vh - 180px);
      /* ajusta según tu header/sidebar */
    }

    .comment-list {
      overflow: auto;
      padding-right: 6px;
      flex: 1 1 auto;
      /* ocupa el alto disponible del panel */
      min-height: 120px;
    }

    /* El form siempre visible al fondo del panel */
    .comment-form {
      position: sticky;
      bottom: 0;
      background: #fff;
      border-top: 1px solid #eef0f4;
      padding-top: 12px;
    }

    /* Responsive: apilar en móviles */
    @media (max-width: 900px) {
      .cols {
        grid-template-columns: 1fr !important;
      }

      .col-left .comments-card {
        max-height: none;
      }

       .wrap {
        width: 100%;
    padding: 12px;
      }


      /*Pruebas*/

     .kv {
        grid-template-columns: 150px 1fr !important;
    }
    /*Fin Pruebas*/



    }

    /* ===== Layout dos columnas con más respiro ===== */
    .cols {
      display: grid;
      grid-template-columns: 380px 1fr;
      /* izq fija (chat), der flexible */
      gap: 26px;
      /* <-- más espacio entre columnas */
      align-items: start;
    }

    /* Asegura que no se desborden las columnas */
    .col-left,
    .col-right {
      min-width: 0;
    }

    /* El panel de comentarios con altura útil y scroll en la lista */
    .col-left .comments-card {
      display: flex;
      flex-direction: column;
      max-height: calc(100vh - 160px);
      position: sticky;
      top: 16px;
      /* se “despega” del borde superior */
    }

    /* La lista scrollea, el form queda visible */
    .comment-list {
      overflow: auto;
      padding-right: 6px;
      flex: 1 1 auto;
      min-height: 140px;
    }

    /* Ya no necesitamos margen extra arriba del comments-card */
    .comments-card {
      margin-top: 0;
    }

    /* Responsive: apilar en pantallas chicas */
    @media (max-width: 1100px) {
     
      .cols {
        grid-template-columns: 1fr;
        gap: 16px;
      }

      .col-left .comments-card {
        position: static;
        max-height: none;
      }
    }

    /* Contenedor principal un poquito más ancho */
    .wrap {
      max-width: 1100px;
    margin: 0 auto;
    padding: 18px;
    }

    /* Tarjetas con borde sutil para que no se "mezclen" visualmente */
    .card,
    .comments-card {
      background: #fff;
      border-radius: 18px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, .08);
      padding: 18px 20px;
      border: 1px solid #eaeef4;
      /* <-- nuevo */
    }

    /* Chat look */
    .comment-list.chat {
      display: flex;
      flex-direction: column;
      gap: 10px;
      overflow: auto;
      max-height: clamp(260px, 45vh, 520px);
      padding: 10px;
      background: #f8fafc;
      border: 1px solid #e5e7eb;
      border-radius: 14px;
    }

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

    .msg.other {
      align-self: flex-start;
    }

    .msg.other .bubble {
      background: #fff;
      color: #0f172a;
      border: 1px solid #e5e7eb;
      border-top-left-radius: 6px;
    }

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

    /* Mantén el form visible y la lista scrolleable */
    .col-left .comments-card {
      display: flex;
      flex-direction: column;
    }

    .comment-list {
      flex: 1 1 auto;
    }

    .comment-form {
      position: sticky;
      bottom: 0;
      background: #fff;
    }

    /* Badges nuevas para cliente */
    .status.enviado {
      background: #ecf3ff;
      color: #0b5fa3;
      border: 1px solid #cfe1ff
    }

    .status.proceso {
      background: #fff7ed;
      color: #b45309;
      border: 1px solid #fde68a
    }

    .status.nota {
      background: #ecfdf5;
      color: #047857;
      border: 1px solid #a7f3d0
    }

    .status.cancelado {
      background: #fef2f2;
      color: #b91c1c;
      border: 1px solid #fecaca
    }

    /*Pruebas de Tabla*/
    .card {
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 16px;
      padding: 16px;
      margin-bottom: 10px
    }
     .kv {
      /*display: grid;
      grid-template-columns: 200px 1fr;
      gap: 8px 12px;*/

      display: grid;
  grid-template-columns: minmax(140px, 40%) 1fr; /* más flexible en desktop/tablet */
  gap: 8px 12px;
    }

    .kv div {
      /*padding: 4px 0;
      border-bottom: 1px dotted #eee;*/
      min-width: 0;               /* clave para que no empuje el grid */
  overflow-wrap: anywhere;    /* partir palabras/líneas largas */
  word-break: break-word;     /* por si vienen cadenas sin espacios */
     
    }

    .kv div:nth-child(2n) {
      border-bottom: 1px dotted #eee;
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

    .ga-preview-title {
  font-size: 15px !important; /* más pequeño */
  font-weight: normal;        /* opcional, para que no sea tan cargado */
  color: #ffffffff;                /* opcional */
}

ga-preview-popup {
  max-width: 1200px; /* en pantallas grandes no pasa de aquí */
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

@media (max-width: 768px) {
  .ga-preview-popup {
    width: 95vw !important; /* en móviles casi todo el ancho */
  }
}

/* Móvil */
@media (max-width: 768px) {
  .ga-preview-popup {
    width: 96vw !important;    /* casi todo el ancho en móvil */
    padding: 0 !important;
  }
  .ga-preview-title {
    font-size: 13px;
    margin-bottom: 8px;
  }
  .ga-preview-frame {
    max-height: 76vh;          /* un pelín más alto en móvil */
  }
  .ga-preview-media {
    max-height: 72vh;          /* y el media ligeramente más alto */
  }
  .ga-preview-download {
    width: 100%;               /* botón full-width para dedo gordo */
    text-align: center;
  }
}

  </style>
</head>

<body>

  <?php require __DIR__ . '/../Componentes/sidebar.php'; ?>

  <main class="wrap">
    <!--<a class="back" href="index.php">&larr; Volver al historial</a>-->
    <nav class="breadcrumb">
      <a class="btn-sm" href="./index.php">Volver al historial</a>
    </nav>

    <header class="hdr">
      <div>
        <h1>Garantia <span class="folio">— <?= h($folio) ?></span></h1>
      </div>
      <div><span class="status <?= h($clsCli) ?>"><?= h($labelCli) ?></span></div>
    </header>

    <!--<div class="title-row">
            <div class="logo-box" aria-hidden="true">
              <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#0b5fa3" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 7l9 4 9-4M3 7l9-4 9 4M3 7v10l9 4 9-4V7" />
                <path d="M12 11v10" />
              </svg>
            </div>
            <h1 class="title">
              Garantía <span class="folio">— <?= h($folio) ?></span>
              <span class="status <?= h($clsCli) ?>"><?= h($labelCli) ?></span>
            </h1>
          </div>-->

    <div class="cols">
      <!-- Columna IZQUIERDA: Comentarios -->
      <div class="col-left">
        <section class="comments-card" aria-labelledby="comments-title">
          <h2 class="comments-title" id="comments-title">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0b5fa3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M21 15a4 4 0 0 1-4 4H7l-4 4V7a4 4 0 0 1 4-4h6" />
              <path d="M17 3h4v4" />
              <path d="M16 8l5-5" />
            </svg>
            Comentarios
          </h2>

          <div id="comment-list" class="comment-list chat" role="list"></div>

          <?php if (!empty($uid) || !empty($email)) : ?>
            <form id="comment-form" class="comment-form" autocomplete="off">
              <textarea id="cuerpo" name="cuerpo" placeholder="Escribe un comentario…"></textarea>
              <div class="comment-actions">
                <button class="btn-primary" id="btn-enviar" type="submit">Publicar</button>
              </div>
              <input type="hidden" name="id_solicitud" value="<?= (int)$row['id'] ?>">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            </form>
          <?php else: ?>
            <div class="comment-empty">Inicia sesión para publicar comentarios de esta garantía.</div>
          <?php endif; ?>
        </section>
      </div>

      <!-- Columna DERECHA: Detalle -->
      <div class="col-right">
        <section class="card">
          <!--<div class="title-row">
            <div class="logo-box" aria-hidden="true">
              <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#0b5fa3" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 7l9 4 9-4M3 7l9-4 9 4M3 7v10l9 4 9-4V7" />
                <path d="M12 11v10" />
              </svg>
            </div>
            <h1 class="title">
              Garantía <span class="folio">— <?= h($folio) ?></span>
              <span class="status <?= h($clsCli) ?>"><?= h($labelCli) ?></span>
            </h1>
          </div>-->
          <h3>Información</h3>
          <div class="kv">
            <div><b>Correo remitente</b></div>
            <div><?= v($correoRem) ?></div>
            <div><b>Fecha del registro</b></div>
            <div><?= h($fechaRegistro) ?></div>
            <div><b>Nombre del producto</b></div>
            <div><?= v($producto) ?></div>
            <div><b>Código en factura</b></div>
            <div><?= v($codigoFactura) ?></div>
            <div><b>No. de factura</b></div>
            <div><?= v($noFactura) ?></div>
            <div><b>Fecha de factura</b></div>
            <div><?= v($fechaFactura) ?></div>
            <div><b>Marca</b></div>
            <div><?= v($marca) ?></div>
            <div><b>Descripción de la falla</b></div>
            <div><?= nl2br(v($descFalla)) ?></div>
            <div><b>Nombre de contacto</b></div>
            <div><?= v($nombreCto) ?></div>
            <div><b>Teléfono</b></div>
            <div><?= v($tel) ?></div>
            <div><b>Sucursal</b></div>
            <div><?= v($sucursal) ?></div>
          </div>
        </section>
         <!-- <table class="detail" aria-label="Detalle de garantía">
            <h3>Información</h3>
            <tbody>
              <tr>
                <th>Correo remitente</th>
                <td><?= v($correoRem) ?></td>
              </tr>
              <tr>
                <th>Fecha del registro</th>
                <td><?= h($fechaRegistro) ?></td>
              </tr>
              <tr>
                <th>Nombre del producto</th>
                <td><?= v($producto) ?></td>
              </tr>
              <tr>
                <th>No. de serie (si aplica)</th>
                <td><?= v($noSerie) ?></td>
              </tr>
              <tr>
                <th>Código en factura</th>
                <td><?= v($codigoFactura) ?></td>
              </tr>
              <tr>
                <th>No. de factura</th>
                <td><?= v($noFactura) ?></td>
              </tr>
              <tr>
                <th>Fecha de factura</th>
                <td><?= v($fechaFactura) ?></td>
              </tr>
              <tr>
                <th>Marca</th>
                <td><?= v($marca) ?></td>
              </tr>
              <tr>
                <th>Descripción de la falla</th>
                <td><?= nl2br(v($descFalla)) ?></td>
              </tr>
              <tr>
                <th>Nombre de contacto</th>
                <td><?= v($nombreCto) ?></td>
              </tr>
              <tr>
                <th>Teléfono</th>
                <td><?= v($tel) ?></td>
              </tr>
              <tr>
                <th>Sucursal</th>
                <td><?= v($sucursal) ?></td>
              </tr>
            </tbody>
          </table>-->

          

          <div class="legend">
            <strong>Leyenda:</strong>
            Con este folio acudir a la <strong>Sucursal</strong> donde realizó su compra para evaluación de las
            condiciones físicas del material y del empaque. Se envía con copia a su bandeja de entrada.
          </div>
        </section>

        <section class="card">
         <!-- <h3>Adjuntos (<?= count($adj) ?>)</h3>
            <?php if (!$adj): ?>
            <p class="meta">No hay archivos adjuntos.</p>
          <?php else: ?>
            <div class="gal">
              <?php foreach ($adj as $a):
                $ruta = (string)$a['ruta'];
                $url  = $ruta;
                $orig = $a['nombre_original'] ?: basename($ruta);
                $ruta = (string)$a['ruta'];
                $url  = $ruta; // 'foto', 'video' u otros

                // Detecta por extensión para PDF/audio/otros
                $path = parse_url($ruta, PHP_URL_PATH) ?? '';
                $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $isImg   = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'avif'], true) || $tipo === 'foto';
                $isPdf   = ($ext === 'pdf');
                //$isVideo = in_array($ext, ['mp4', 'webm', 'ogg'], true) || $tipo === 'video';
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
          <?php endif; ?>-->

          <h3>Adjuntos (<?= count($adj) ?>)</h3>

  <?php if (!$adj): ?>
    <p class="meta">No hay archivos adjuntos.</p>
  <?php else: ?>
    <div class="gal">
      <?php foreach ($adj as $a):
        // --- Datos base ---
        $ruta = (string)($a['ruta'] ?? '');
        $url  = $ruta;
        // Si no hay nombre_original, toma el basename de la ruta (sin querystring)
        $path = parse_url($ruta, PHP_URL_PATH) ?? '';
        $orig = ($a['nombre_original'] ?? '') ?: basename($path);

        // --- Detección por extensión ---
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: '');

        $IMG_EXT = ['jpg','jpeg','png','gif','webp','bmp','avif'];
        $VID_EXT = ['mp4','webm','ogg','mov','m4v'];
        $AUD_EXT = ['mp3','ogg','wav','m4a','aac','flac'];

        $isImg   = in_array($ext, $IMG_EXT, true);
        $isPdf   = ($ext === 'pdf');
        $isVideo = in_array($ext, $VID_EXT, true);
        $isAudio = in_array($ext, $AUD_EXT, true);

        // Tipo genérico para el visor
        $prevType = $isImg ? 'image'
                   : ($isPdf ? 'pdf'
                   : ($isVideo ? 'video'
                   : ($isAudio ? 'audio' : 'file')));

        // Para PDF ajusta vista horizontal si no trae ya un #
        $previewSrc = $isPdf
          ? ($url . (str_contains($url, '#') ? '' : '#view=FitH'))
          : $url;

        $extLabel = strtoupper($ext ?: 'FILE');
      ?>
        <div class="tile">
          <button class="tile-thumb js-preview"
                  data-type="<?= h($prevType) ?>"
                  data-src="<?= h($previewSrc) ?>"
                  data-name="<?= h($orig) ?>"
                  aria-label="Vista previa: <?= h($orig) ?>">
            <?php if ($isImg): ?>
              <img src="<?= h($url) ?>" alt="<?= h($orig) ?>"
                   style="width:100%;height:200px;object-fit:cover;display:block">
            <?php else: ?>
              <div class="tile-fallback">
                <div class="tf-ext"><?= h($extLabel) ?></div>
              </div>
            <?php endif; ?>
          </button>

          <div class="cap">
            <span title="<?= h($orig) ?>"
                  style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:70%">
              <?= h($orig) ?>
            </span>
            <a class="btn-sm" href="<?= h($url) ?>" download>Descargar</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

          
        </section>
      </div>
    </div>
  </main>


  <script>
    window.GARANTIA = {
      id: <?= (int)$row['id'] ?>,
      csrf: <?= json_encode($csrfToken) ?>,
      me: <?= (int)($_SESSION['user_id'] ?? 0) ?> // <-- mi id

    };
  </script>
  <script>
    (function() {
      const list = document.getElementById('comment-list');
      const form = document.getElementById('comment-form');
      const btn = document.getElementById('btn-enviar');

      let lastId = 0; // último id visto
      let timer = null;
      let busy = false;

      const baseId = GARANTIA.id;

      function escapeHtml(s) {
        return (s || '').replace(/[&<>"']/g, m => ({
          '&': '&amp;',
          '<': '&lt;',
          '>': '&gt;',
          '"': '&quot;',
          "'": '&#039;'
        } [m]));
      }

      function renderItem(it) {
        const meId = Number(GARANTIA.me || 0);
        const uid = Number(it.id_usuario ?? it.user_id ?? it.usuario_id ?? it.uid ?? 0);
        const mine = meId && uid === meId;
        const side = mine ? 'mine' : 'other';

        const who = escapeHtml(it.nombre || (mine ? 'Tú' : 'Usuario'));
        const when = escapeHtml((it.created_at || '').replace('T', ' ').slice(0, 16));
        const body = escapeHtml(it.cuerpo || '').replace(/\n/g, '<br>');

        const el = document.createElement('div');
        el.className = `msg ${side}`;
        el.dataset.id = it.id; // 👈 necesario para incremental
        el.setAttribute('role', 'listitem');
        el.innerHTML = `
      <div class="bubble">
        <div class="bhead"><span class="who">${who}</span><span class="time">· ${when}</span></div>
        <div class="btext">${body}</div>
      </div>`;
        return el;
      }

      function nearEnd(el) {
        return (el.scrollHeight - el.scrollTop - el.clientHeight) < 40;
      }

      function scrollToBottom() {
        try {
          list.scrollTop = list.scrollHeight;
        } catch (_) {}
      }

      function getAfterId() {
        const last = list.querySelector('[data-id]:last-child');
        return last ? Number(last.dataset.id) : lastId;
      }

      async function loadInitial() {
        list.innerHTML = '<div class="comment-empty">Cargando comentarios…</div>';
        try {
          const r = await fetch(`comentarios.php?id_solicitud=${encodeURIComponent(baseId)}`, {
            cache: 'no-store',
            credentials: 'same-origin'
          });
          const d = await r.json();
          list.innerHTML = '';
          (d.items || []).forEach(it => list.appendChild(renderItem(it)));
          lastId = Number(d.last_id || getAfterId() || 0);
          scrollToBottom();
        } catch (e) {
          list.innerHTML = '<div class="comment-empty">No se pudieron cargar los comentarios.</div>';
        }
      }

      async function poll() {
        if (busy || document.hidden) return;
        busy = true;
        try {
          const after = getAfterId() || 0;
          const wasNear = nearEnd(list);
          const url = `comentarios.php?id_solicitud=${encodeURIComponent(baseId)}&after_id=${after}`;
          const r = await fetch(url, {
            cache: 'no-store',
            credentials: 'same-origin'
          });
          const d = await r.json();
          if (d.ok && d.items && d.items.length) {
            d.items.forEach(it => {
              // evita duplicados si el backend devolviera alguno repetido
              if (!list.querySelector(`[data-id="${it.id}"]`)) {
                list.appendChild(renderItem(it));
              }
            });
            lastId = Number(d.last_id || getAfterId() || lastId);
            if (wasNear) scrollToBottom();
          }
        } catch (_) {} finally {
          busy = false;
        }
      }

      function startPolling(ms = 7000) {
        if (timer) clearInterval(timer);
        timer = setInterval(poll, ms);
      }
      document.addEventListener('visibilitychange', () => {
        if (!document.hidden) poll();
      });

      async function onSubmit(ev) {
        ev.preventDefault();
        if (!form) return;
        const fd = new FormData(form);
        const cuerpo = (fd.get('cuerpo') || '').trim();
        if (!cuerpo) {
          alert('Escribe un comentario.');
          return;
        }

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
          lastId = Math.max(lastId, Number(d.item?.id || 0));
          form.cuerpo.value = '';
          scrollToBottom();
        } catch (e) {
          alert('No se pudo publicar el comentario.');
        } finally {
          btn.disabled = false;
          btn.textContent = 'Publicar';
        }
      }

      // bootstrap
      (async () => {
        await loadInitial();
        startPolling(7000); // cada 7s (ajústalo)
      })();

      if (form) form.addEventListener('submit', onSubmit);
    })();
  </script>

  <!--Pruebas-->
  <script>
document.addEventListener('click', (e) => {
  const btn = e.target.closest('.js-preview');
  if (!btn) return;

  const type = btn.dataset.type;   // 'image' | 'pdf' | 'video' | 'audio' | 'file'
  const src  = btn.dataset.src;    // URL del recurso (para PDF ya viene con #view=FitH)
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
  /*title: name,
  html,
  showConfirmButton: false,
  showCloseButton: true,
  width: '80vw',
  customClass: {
    popup: 'ga-preview-popup',
    title: 'ga-preview-title' // 👈 le ponemos clase al título
  }]*/

   
  html: `
    <div style="margin-bottom:10px;font-size:14px;color:#fff;">
      ${name}
    </div>
    ${html} <!-- Aquí va la vista previa (imagen, pdf, video...) -->
    <div style="margin-top:15px;text-align:center;">
      <a href="${src}" download
         style="display:inline-block;padding:8px 16px;background:#ed6b1f;color:#fff;
                text-decoration:none;border-radius:4px;font-size:14px;">
        Descargar
      </a>
    </div>
  `,
  showConfirmButton: false,
  showCloseButton: true,
  width: '40vw',   // 👈 ocupa 90% del ancho de la pantalla
  customClass: {
    popup: 'ga-preview-popup'
  }
  

});


});
</script>

<style>
/* Opcional: pequeños ajustes de la ventana SweetAlert2 para previews */
.ga-preview-popup {
  max-width: 1200px;       /* límite máximo en pantallas grandes */
}
@media (max-width: 768px){
  .ga-preview-popup {
    width: 95vw !important;
  }
}
</style>

</body>

</html>
<?php


// —— Sesión segura y consistente —— //
ini_set('session.cookie_httponly', '1');
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
  ini_set('session.cookie_secure', '0'); // local
} else {
  ini_set('session.cookie_secure', '1'); // prod
}
session_name("GA");
session_start();
require_once __DIR__ . '/../guards/force_password_change.php';

require_once __DIR__ . '/../config.php'; // necesitas APP_URL para las imágenes
require_once __DIR__ . '/../app/session_boot.php';
require_login();




// ¿Está logueado?
$loggedin = $_SESSION['loggedin'] ?? false;
if (!$loggedin) {
  // fallback: si por alguna razón no pusiste 'loggedin' pero sí hay user_id
  $loggedin = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;
}

if (!$loggedin) {
  header("Location: ../index.php");
  exit;
}


?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="./Estilos/style_index.css?v=1.0">
<!--Icon-->
  <link rel="icon" href="..\assets\img\iconpestalla.png" type="image/x-icon">
    <!--Fuentes-->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="preload" as="image"
      href="<?= APP_URL ?>/assets/img/FondoInicio.webp"
      imagesrcset="<?= APP_URL ?>/assets/img/FondoInicio.webp,
                  
      imagesizes="100vw">

    <title>Document</title>

    <!-- Preload de iconos clave (cárgalos antes del resto) -->
<link rel="preload" as="image" href="<?= APP_URL ?>/assets/svg/DevolucionIcon.svg">
<link rel="preload" as="image" href="<?= APP_URL ?>/assets/svg/GarantiaIcon.svg">
<link rel="preload" as="image" href="<?= APP_URL ?>/assets/svg/ProtocolosIcon.svg">

<!-- (Opcional) Prioriza el CSS si tu archivo es pesado -->
<link rel="preload" as="style" href="./Estilos/style_index.css?v=1.0" onload="this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="./Estilos/style_index.css?v=1.0"></noscript>

<style>
  /* Acelera el primer paint y evita saltos */
  .client-portal { content-visibility: auto; contain-intrinsic-size: 900px; }

  /* Estados de carga para un reveal perceptible */
  .is-loading .portal-cards { opacity: 0; }
  .is-ready   .portal-cards { opacity: 1; transition: opacity .35s ease; }

  /* Skeleton simple bajo el hero mientras llegan iconos */
  .skeleton {
    height: 18px; border-radius: 10px; margin: 14px 0 26px;
    background: linear-gradient(100deg, #eee 40%, #f5f5f5 50%, #eee 60%);
    background-size: 200% 100%;
    animation: shimmer 1s infinite linear;
  }
  @keyframes shimmer { to { background-position: -200% 0; } }

  /* Respeta accesibilidad */
  @media (prefers-reduced-motion: reduce){
    .portal-cards { transition: none !important; }
    .skeleton { animation: none !important; }
  }

  /* Color de respaldo inmediato */
body{
  min-height: 100dvh;
  background: #0b1b34; /* fallback sólido mientras carga */
}

/* Capa del fondo que se desvanece */
body::before{
  content:"";
  position: fixed;         /* simula tu background-attachment: fixed */
  inset: 0;
  background: url("<?= APP_URL ?>/assets/img/FondoInicio.webp") center / cover no-repeat;
  opacity: 0;
  transition: opacity .45s ease;
  will-change: opacity;
  z-index: -1;             /* queda detrás del contenido */
}

/* Cuando la imagen está decodificada -> mostrar */
body.bg-ready::before{ opacity: 1; }



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
<body class="bg-cover" >

<?php require ("../Componentes/sidebar.php") ; ?>




<main class="client-portal is-loading" role="main" aria-label="Portal exclusivo para clientes">
  <section class="hero container">
    <img src="../assets/svg/logo.iconwhite.svg" alt="icono" class="icon-20">
    <h1>PORTAL <span class="outline">EXCLUSIVO</span><br>PARA CLIENTES</h1>
    <p class="subtitle">Un espacio pensado para ti, donde tus solicitudes se atienden rápido y sin vueltas.</p>

  </section>

  <section class="portal-cards container" aria-label="Accesos rápidos">
    <!-- DEVOLUCIONES -->
    <article class="portal-card card--blue">
      <div class="icon" aria-hidden="true">
        <!-- Caja -->
        <!--<svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 7l9 4 9-4M3 7l9-4 9 4M3 7v10l9 4 9-4V7" />
          <path d="M12 11v10" />
        </svg>-->
        <img src="<?= APP_URL ?>/assets/svg/DevolucionIcon.svg" alt="icono">
      </div>
      <h3>DEVOLUCIONES</h3>
      <p>Devuelve tu producto en pocos pasos, rápido y sin complicaciones.</p>
      <a class="btn" href="../Devoluciones\index.php">INICIAR DEVOLUCIÓN</a>
    </article>

    <!-- GARANTIAS -->
    <article class="portal-card card--navy">
      <div class="icon" aria-hidden="true">
        <!-- Escudo -->
        <!--<svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 3l7 2v6c0 5-3.5 8.5-7 10-3.5-1.5-7-5-7-10V5l7-2z" />
          <path d="M9 12l2.2 2.2L15.5 10" />-->
          <img class="iconIndex" src="<?= APP_URL ?>/assets/svg/GarantiaIcon.svg" alt="icono">
        </svg>
      </div>
      <h3>GARANTIAS</h3>
      <p>Registra tu equipo y recibe soporte inmediato.</p>
      <a class="btn" href="../garantias\index.php">SOLICITAR GARANTIA</a>
    </article>

    <!-- PROTOCOLOS / DOCUMENTOS -->
    <article class="portal-card card--orange">
      <div class="icon" aria-hidden="true">
        <!-- Certificado -->
        <!--<svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <rect x="5" y="2" width="10" height="14" rx="2" />
          <path d="M9 6h4M9 10h4" />
          <path d="M15 13l2 2 2-2 2 2-2 2 2 2-2 2-2-2-2 2-2-2 2-2-2-2 2-2z" />
        </svg>-->
        <img class="iconIndex" src="<?= APP_URL ?>/assets/svg/ProtocolosIcon.svg" alt="icono">
      </div>
      <h3>PROTOCOLOS CFE Y DOCUMENTOS</h3>
      <p>Solicita protocolos certificados sigla 03 de CFE o fichas técnicas con entrega rápida.</p>
      <a class="btn" href="../protocolos\index.php">SOLICITAR DOCUMENTOS</a>
    </article>
  </section>
</main>
<script>
(function(){
  const main = document.querySelector('.client-portal');
  if (!main) return;

  // Imágenes críticas: tus tres iconos del "above the fold"
  const criticalImgs = Array.from(main.querySelectorAll('.icon img'));

  const MIN_VISIBLE_MS = 400; // súbelo a 450–600 si quieres que se note más
  const start = performance.now();

  function whenReady(img){
    return new Promise(res=>{
      if (img.complete && img.naturalWidth) return res();
      img.addEventListener('load',  res, {once:true});
      img.addEventListener('error', res, {once:true}); // no bloquees por error
    });
  }

  Promise.all(criticalImgs.map(whenReady)).then(()=>{
    const elapsed = performance.now() - start;
    const wait = Math.max(0, MIN_VISIBLE_MS - elapsed);
    setTimeout(()=>{
      main.classList.remove('is-loading');
      main.classList.add('is-ready');

      // Pone lazy al resto de imágenes del documento (si tuvieras más abajo)
      document.querySelectorAll('img').forEach(img=>{
        if (!criticalImgs.includes(img)) {
          img.loading = 'lazy';
          img.decoding = 'async';
        }
      });
    }, wait);
  });
})();
</script>

    <script>
(function(){
  const url = "<?= APP_URL ?>/assets/img/FondoInicio.webp";
  const i = new Image();
  i.src = url;
  if (i.decode) {
    i.decode().catch(()=>{}).then(()=>document.body.classList.add('bg-ready'));
  } else {
    i.onload = ()=>document.body.classList.add('bg-ready');
    i.onerror = ()=>document.body.classList.add('bg-ready'); // no te quedes en negro si falla
  }
})();
</script>

</body>
</html>

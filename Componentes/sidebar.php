<?php
// Componentes/sidebar.php

// APP_URL + DB por si el include viene desde subcarpetas
$ROOT = dirname(__DIR__); // .../Portal_GA
if (!defined('APP_URL')) require_once $ROOT . '/config.php';
if (!isset($pdo))        require_once $ROOT . '/app/db.php';

// Helpers mínimos si no existen
if (!function_exists('h')) {
  function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
function active($needle, $path){ return (stripos($path, $needle) !== false) ? 'is-active' : ''; }
function hp($needle, $path){ return stripos($path, $needle) !== false ? 'high' : 'auto'; }

// Datos base de sesión
$uid         = $_SESSION['user_id'] ?? null;
$displayName = $_SESSION['username'] ?? ($_SESSION['Email'] ?? $_SESSION['email'] ?? 'Usuario');
$avatarFile  = $_SESSION['avatar']     ?? '';     // nombre del archivo (p.ej. 123.jpg)
$avatarVer   = $_SESSION['avatar_ver'] ?? null;   // versión estable (timestamp de updated_at)
$path        = $_SERVER['REQUEST_URI'] ?? '';
$HOME_URL    = APP_URL . '/Inicio';

// Si falta info del avatar en sesión, o no hay versionador, traemos de BD y cacheamos
if ($uid && ($avatarFile === '' || $avatarVer === null)) {
  try {
    $st = $pdo->prepare('SELECT username, avatar, updated_at FROM usuarios WHERE id=? LIMIT 1');
    $st->execute([$uid]);
    if ($u = $st->fetch(PDO::FETCH_ASSOC)) {
      if (!empty($u['username'])) {
        $displayName = $u['username'];
        $_SESSION['username'] = $u['username'];
      }
      if (!empty($u['avatar'])) {
        $avatarFile = $u['avatar'];
        $_SESSION['avatar'] = $u['avatar'];
      }
      $avatarVer = $u['updated_at'] ? strtotime($u['updated_at']) : null;
      $_SESSION['avatar_ver'] = $avatarVer;
    }
  } catch (Throwable $e) { /* no romper UI */ }
}

// Construye URL absoluta del avatar (si hay archivo) con ?v= estable para caché
$avatarUrl = '';
if ($avatarFile !== '') {
  $avatarUrl = rtrim(APP_URL, '/') . '/assets/avatars/' . rawurlencode($avatarFile);
  if (!empty($avatarVer)) $avatarUrl .= '?v=' . $avatarVer;
}

// Inicial (fallback mientras carga la foto)
$initial = mb_strtoupper(mb_substr($displayName, 0, 1, 'UTF-8'), 'UTF-8');

// CSRF logout
if (empty($_SESSION['csrf_logout'])) {
  $_SESSION['csrf_logout'] = bin2hex(random_bytes(16));
}

// ——— Preload de SVG del sidebar + avatar (una sola vez por página) ———
if (empty($GLOBALS['__sb_preloads_done'])) {
  $GLOBALS['__sb_preloads_done'] = true;
  echo '<link rel="preload" as="image" href="'.APP_URL.'/assets/svg/DevolucionIcon.svg">'."\n";
  echo '<link rel="preload" as="image" href="'.APP_URL.'/assets/svg/GarantiaIcon.svg">'."\n";
  echo '<link rel="preload" as="image" href="'.APP_URL.'/assets/svg/ProtocolosIcon.svg">'."\n";
  echo '<link rel="preload" as="image" href="'.APP_URL.'/assets/svg/ConfiguracionIcon.svg">'."\n";
  echo '<link rel="preload" as="image" href="'.APP_URL.'/assets/svg/CerrarSesionIcon.svg">'."\n";
  if (!empty($avatarUrl)) {
    echo '<link rel="preload" as="image" href="'.h($avatarUrl).'">'."\n";
  }
}
?>


<!-- Botón hamburguesa (solo móvil) -->
<button id="sbToggle" type="button" class="sb-hamburger"
  aria-label="Abrir menú" aria-controls="app-sidebar" aria-expanded="false">
  <img src="<?= APP_URL ?>/assets/svg/MenuHamIcon.svg" alt="icono">
</button>

<!-- Overlay para cerrar tocando fuera -->
<div id="sbOverlay" class="sb-overlay" hidden></div>

<nav id="app-sidebar" class="sb" aria-label="Menú lateral">
  <a class="sb__brand sb__brand-link" href="<?= $HOME_URL ?>" title="Ir al inicio">
    <div class="sb__avatar" aria-label="Usuario">
      <span class="sb__avatar-initial" aria-hidden="true"><?= h($initial) ?></span>
      <?php if ($avatarUrl): ?>
        <img class="sb__avatar-img"
             src="<?= $avatarUrl ?>"
             alt="Foto de perfil de <?= h($displayName) ?>"
             width="44" height="44"
             decoding="async" fetchpriority="high"
             onload="this.classList.add('is-ready'); this.parentElement.classList.add('has-photo')"
             onerror="this.remove()">
      <?php endif; ?>
    </div>
  </a>

  <ul class="sb__nav" role="list">
    <li>
      <a href="<?= APP_URL ?>/Devoluciones/index.php"
         class="nav-link <?= active('devoluciones', $path) ?>"
         data-transition data-tip="Devoluciones" aria-label="Devoluciones">
        <img class="iconOpc"
             src="<?= APP_URL ?>/assets/svg/DevolucionIcon.svg"
             alt="Devoluciones" width="28" height="28"
             decoding="async"
             fetchpriority="<?= hp('devoluciones', $path) ?>">
      </a>
    </li>

    <li>
      <a href="<?= APP_URL ?>/garantias/index.php"
         class="nav-link <?= active('garant', $path) ?>"
         data-transition data-tip="Garantías" aria-label="Garantías">
        <img class="iconOpc iconOpcBig"
             src="<?= APP_URL ?>/assets/svg/GarantiaIcon.svg"
             alt="Garantías" width="28" height="28"
             decoding="async"
             fetchpriority="<?= hp('garant', $path) ?>">
      </a>
    </li>

    <li>
      <a href="<?= APP_URL ?>/protocolos/index.php"
         class="nav-link <?= active('protocolo', $path) ?>"
         data-transition data-tip="Protocolos" aria-label="Protocolos">
        <img class="iconOpc iconOpcBig"
             src="<?= APP_URL ?>/assets/svg/ProtocolosIcon.svg"
             alt="Protocolos" width="28" height="28"
             decoding="async"
             fetchpriority="<?= hp('protocolo', $path) ?>">
      </a>
    </li>

    <li class="sb__spacer" aria-hidden="true"></li>

    <li>
      <a href="<?= APP_URL ?>/ajustes.php"
         class="<?= active('ajuste', $path) ?> iconmove" data-tip="Ajustes" aria-label="Ajustes">
        <img class="iconOpc"
             src="<?= APP_URL ?>/assets/svg/ConfiguracionIcon.svg"
             alt="Ajustes" width="28" height="28"
             decoding="async"
             fetchpriority="<?= hp('ajuste', $path) ?>">
      </a>
    </li>

    <li class="sb__logout">
      <form action="<?= APP_URL ?>/logout.php" method="post">
        <input type="hidden" name="t" value="<?= h($_SESSION['csrf_logout']) ?>">
        <button type="submit" class="sb__logout-btn" data-tip="Cerrar sesión" aria-label="Cerrar sesión">
          <img class="iconOpcDos"
               src="<?= APP_URL ?>/assets/svg/CerrarSesionIcon.svg"
               alt="Cerrar sesión" width="28" height="28"
               decoding="async"
               fetchpriority="auto">
        </button>
      </form>
    </li>
  </ul>
</nav>

<script>
  // Forzar activo vía <body data-section="..."> si lo usas
  const section = document.body.dataset.section;
  if (section) {
    document.querySelectorAll('.sb__nav a').forEach(a => {
      if (a.getAttribute('href')?.includes(section)) a.classList.add('is-active');
    });
  }
</script>

<script>
(function () {
  const btn     = document.getElementById('sbToggle');
  const aside   = document.getElementById('app-sidebar');
  const overlay = document.getElementById('sbOverlay');
  const mql     = window.matchMedia('(max-width: 1024px)');
  if (!btn || !aside || !overlay) return;

  function showHamburger(){ btn.classList.remove('is-hidden'); btn.classList.add('is-visible'); btn.setAttribute('aria-expanded','false'); }
  function hideHamburger(){ btn.classList.add('is-hidden'); btn.classList.remove('is-visible'); btn.setAttribute('aria-expanded','true'); }

  function openSB(){ aside.classList.add('is-open'); overlay.hidden = false; document.body.classList.add('body-nosrc'); hideHamburger(); }
  function closeSB(){ aside.classList.remove('is-open'); overlay.hidden = true; document.body.classList.remove('body-nosrc'); showHamburger(); }
  function toggleSB(){ aside.classList.contains('is-open') ? closeSB() : openSB(); }

  btn.addEventListener('click', toggleSB);
  overlay.addEventListener('click', (e)=>{ if (e.target === overlay) closeSB(); });
  document.addEventListener('keydown', (e)=>{ if (e.key === 'Escape' && aside.classList.contains('is-open')) closeSB(); });
  window.addEventListener('resize', ()=>{ if (!mql.matches) closeSB(); });
})();
</script>

<script>
(function(){
  // Transición corta al navegar
  const links = document.querySelectorAll('.sb__nav a[data-transition]');
  if (!links.length) return;
  const main = document.querySelector('main, #app, .client-portal');

  links.forEach(a => {
    a.addEventListener('click', (e) => {
      const url = a.getAttribute('href') || '';
      if (!url) return;
      document.querySelectorAll('.sb__nav a').forEach(x => x.classList.remove('is-active'));
      a.classList.add('is-active');

      if (main) {
        main.style.transition = 'opacity .18s ease, transform .18s ease';
        main.style.opacity = '0';
        main.style.transform = 'translateY(6px)';
      }
      e.preventDefault();
      setTimeout(()=>{ window.location.href = url; }, 160);
    }, {passive:false});
  });
})();
</script>

<script>
(function(){
  const html = document.documentElement;

  // Entrada suave: espera imágenes críticas si las marcas con [data-critical]
  const criticalImgs = Array.from(document.querySelectorAll('[data-critical]'));
  const MIN_VISIBLE_MS = 380;
  function whenReady(img){
    return new Promise(res=>{
      if (!img || (img.complete && img.naturalWidth)) return res();
      img.addEventListener('load', res, {once:true});
      img.addEventListener('error', res, {once:true});
    });
  }
  (async function reveal(){
    const start = performance.now();
    await Promise.all(criticalImgs.map(whenReady));
    const elapsed = performance.now() - start;
    const wait = Math.max(0, MIN_VISIBLE_MS - elapsed);
    setTimeout(()=>{
      html.classList.add('is-ready');
      document.querySelectorAll('img').forEach(img=>{
        if (!criticalImgs.includes(img)) { img.loading='lazy'; img.decoding='async'; }
      });
      document.querySelectorAll('.reveal-list').forEach(list=>{
        list.querySelectorAll(':scope > *').forEach((el,i)=>{ el.style.setProperty('--i', i); });
      });
    }, wait);
  })();

  // Soft-leave para enlaces internos (fade out rápido)
  const sameOrigin = (url)=>{
    try{ const u = new URL(url, location.href); return u.origin === location.origin; }catch{ return false; }
  };
  document.addEventListener('click', (e)=>{
    const a = e.target.closest('a'); if (!a) return;
    const href = a.getAttribute('href') || '';
    if (!href || href.startsWith('#') || a.hasAttribute('download') || a.target === '_blank' || !sameOrigin(href)) return;
    e.preventDefault();
    html.classList.add('is-leaving');
    setTimeout(()=>{ window.location.href = href; }, 160);
  }, {capture:true});

  // Prefetch al pasar el mouse
  const prefetchLinks = new Set();
  function prefetch(href){
    if (prefetchLinks.has(href)) return;
    prefetchLinks.add(href);
    const l = document.createElement('link'); l.rel='prefetch'; l.href=href; document.head.appendChild(l);
  }
  document.addEventListener('mouseover', (e)=>{
    const a = e.target.closest('a'); if (!a) return;
    const href = a.getAttribute('href') || '';
    if (sameOrigin(href) && !href.startsWith('#')) prefetch(href);
  });
})();
</script>


<style>
  :root {
    --sb-bg: #01143e;
    /* azul oscuro */
    --sb-text: #ffffff;
    /* texto claro */
    --sb-hover: #ed6b1f;
    --sb-active: #ED6C24;
    /* acento (naranja) */
    --sb-width: 84px;
  }

  body {
    padding-left: var(--sb-width);
  }

  /* deja espacio al sidebar fijo */

  .sb {
    position: fixed;
    inset: 0 auto 0 0;
    width: var(--sb-width);
    background: var(--sb-bg);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    box-shadow: 0 0 0 1px rgba(255, 255, 255, .04) inset;
    z-index: 1000;
  }

  .sb__brand {
    width: 100%;
    display: grid;
    place-items: center;
    padding: 16px 0 8px;
  }

  .sb__avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font: 600 18px/1 ui-sans-serif, system-ui, "Segoe UI", Roboto, Arial;
    color: #fff;
    background: radial-gradient(120% 120% at 30% 20%, #41392cff 0%, #574734ff 35%, #534d3dff 100%);
    box-shadow: 0 6px 20px rgba(0, 0, 0, .35), inset 0 0 0 2px rgba(255, 255, 255, .12);
    letter-spacing: .5px;
  }

  .sb__nav {
    list-style: none;
    margin: 8px 0 0;
    padding: 0;
    width: 100%;
    display: flex;
    flex-direction: column;
    place-items: center;
    gap: 6px;
  }

  .sb__nav a {
    width: 100%;
    height: 56px;
    display: grid;
    place-items: center;
    color: var(--sb-text);
    text-decoration: none;
    position: relative;
    transition: background .2s ease, color .2s ease, transform .06s ease;
    border-left: 0px solid transparent;
  }

  .sb__nav a:hover {
    background: var(--sb-hover);
    transform: translateY(-1px);
  }

  .sb__nav a.is-active {
    border-left-color: var(--sb-active);
    background: linear-gradient(90deg, rgba(237, 108, 36, .45), transparent 60%);
    color: #fff;
  }

  .sb__nav svg {
    width: 26px;
    height: 26px;
    fill: currentColor;
    opacity: .95;
  }

  .iconOpc {
    width: 60%;
    height: 60%;
  }

  .iconOpcDos {
    width: 60%;
    height: 60%;
  }

  .iconOpcBig {
    width: 65%;
    height: 65%;
  }

  /* Tooltip minimal usando data-tip */
  .sb__nav a::after {
    content: attr(data-tip);
    position: absolute;
    left: calc(100% + 10px);
    top: 50%;
    transform: translateY(-50%);
    background: #111827;
    color: #fff;
    font-size: 12px;
    padding: 6px 8px;
    border-radius: 8px;
    white-space: nowrap;
    pointer-events: none;
    opacity: 0;
    translate: 0 -2px;
    transition: .15s ease;
    box-shadow: 0 6px 20px rgba(0, 0, 0, .3);
  }

  .sb__nav a:hover::after {
    opacity: 1;
    translate: 0 0;
  }

  .sb__spacer {
    flex: 1;
  }

  /* Responsive: en pantallas pequeñas no dejes padding-left tan grande */
  @media (max-width: 680px) {
    :root {
      --sb-width: 72px;
    }

    .sb__avatar {
      width: 40px;
      height: 40px;
      font-size: 16px;
    }

    .sb__nav a {
      height: 52px;
    }

    body {
      padding-left: var(--sb-width);
    }
  }

  /* Estilo del botón de cerrar sesión igual a los <a> del menú */
  .sb__logout form {
    margin-bottom: 50px;
    display: flex;
    justify-content: center;
  }

  .sb__logout-btn {
    all: unset;
    width: 100%;
    height: 56px;
    display: grid;
    place-items: center;
    cursor: pointer;
    color: var(--sb-text);
    border-left: 0px solid transparent;
    transition: background .2s ease, color .2s ease, transform .06s ease;
  }

  .sb__logout-btn:hover {
    background: var(--sb-hover);
    transform: translateY(-1px);
  }

  .sb__logout-btn svg {
    width: 24px;
    height: 24px;
    fill: currentColor;
  }

  /* Tooltip para el botón, igual al de los <a> */
  .sb__logout-btn::after {
    content: attr(data-tip);
    position: absolute;
    left: calc(100% + 10px);
    top: 50%;
    transform: translateY(-50%);
    background: #111827;
    color: #fff;
    font-size: 12px;
    padding: 6px 8px;
    border-radius: 8px;
    white-space: nowrap;
    pointer-events: none;
    opacity: 0;
    translate: 0 -2px;
    transition: .15s ease;
    box-shadow: 0 6px 20px rgba(0, 0, 0, .3);
  }

  .sb__logout-btn:hover::after {
    opacity: 1;
    translate: 0 0;
  }

  /* Opcional: resaltar el botón de logout con rojo al hover */
  .sb__logout-btn:hover {
    color: #fff;
  }

  .sb__logout-btn:hover svg {
    filter: drop-shadow(0 0 0 rgba(0, 0, 0, 0));
  }

  .sb__avatar {
    position: relative;
    overflow: hidden;
  }

  .sb__avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }


  /* ===== Inputs consistentes y sin encimar ===== */
  .portal-card .form-2>.field {
    min-width: 0;
  }

  /* deja encoger columnas */
  .portal-card .field {
    min-width: 0;
  }

  .portal-card .field input {
    height: 44px;
    line-height: 44px;
    padding: 0 14px;
    border-radius: 10px;
    border: 1px solid rgba(255, 255, 255, .22);
    background: rgba(255, 255, 255, .09);
    color: #fff;
    appearance: none;
    -webkit-appearance: none;
    box-sizing: border-box;
  }

  .portal-card .field input:focus {
    border-color: #6aa8ff;
    box-shadow: 0 0 0 3px rgba(106, 168, 255, .22);
    background: rgba(255, 255, 255, .14);
  }

  .portal-card .actions {
    grid-column: 1/-1;
  }

  /* botones ocupan todo el ancho del grid */

  /* ===== Mejor contraste en la card naranja ===== */
  .card--orange {
    color: #1b120c;
  }

  .card--orange h3,
  .card--orange .muted,
  .card--orange label {
    color: #1b120c;
  }

  .card--orange strong {
    color: #07223f;
  }

  .card--orange .field input {
    background: rgba(255, 255, 255, .88);
    border: 1px solid rgba(0, 0, 0, .08);
    color: #0b1b34;
  }

  .card--orange .field input::placeholder {
    color: rgba(0, 0, 0, .55);
  }

  .card--orange .btn-primary {
    background: #ffffff;
    /* blanco para contraste */
    color: #0b1b34;
    box-shadow: 0 10px 28px rgba(0, 0, 0, .18);
  }

  .card--orange .btn-primary:hover {
    filter: brightness(0.98);
  }

  .card--orange .btn-ghost {
    border-color: rgba(0, 0, 0, .22);
    color: #0b1b34;
  }

  /* Badge más legible */
  .badge.ok {
    background: rgba(24, 199, 134, .20);
    color: #066a42;
  }

  .sb__brand-link {
    display: grid;
    place-items: center;
    text-decoration: none;
    outline: none;
  }

  .sb__brand-link:focus-visible .sb__avatar {
    box-shadow: 0 0 0 3px rgba(66, 153, 225, .6);
    /* anillo de foco accesible */
  }

  .sb__avatar {
    cursor: pointer;
  }

  .sb {
    height: 100vh;
    display: flex;
    flex-direction: column;
  }

  .sb__nav {
    flex: 1 1 auto;
    display: flex;
    flex-direction: column;
  }

  /* Opción A: empujar el logout con margin-top:auto */
  .sb__logout {
    margin-top: auto;
  }

  /* (Opcional) si prefieres usar el li.sb__spacer ya existente */
  .sb__spacer {
    flex: 1 1 auto;
  }

  /* Animación de entrada en carga */
  @keyframes fadeInUp {
    from {
      opacity: 0;
      transform: translateY(6px);
    }

    to {
      opacity: 1;
      transform: none;
    }
  }

  #app.page-enter {
    animation: fadeInUp .28s ease both;
  }

  /* Transición de salida al cambiar de sección */
  #app.page-leave {
    opacity: 0;
    transform: translateY(6px);
    transition: opacity .22s ease, transform .22s ease;
  }



  .hidden[hidden] {
    display: none;
    /* clase que oculta el botón */
  }



  /* Respeta usuarios con reducción de movimiento */
  @media (prefers-reduced-motion: reduce) {

    #app.page-enter,
    #app.page-leave {
      animation: none;
      transition: none;
    }



  }


  /**Toggle*/

  .is-hidden {
    display: none !important;
  }

  .is-nothidden {
    display: inline-flex;
  }

  /* ===== Botón hamburguesa (oculto en desktop) ===== */
  .sb-hamburger {
    position: fixed;
    top: 14px;
    left: 14px;
    width: 42px;
    height: 42px;
    display: none;
    /* se muestra en móvil */
    align-items: center;
    justify-content: center;
    gap: 5px;
    border: 0;
    border-radius: 10px;
    background: rgba(0, 0, 0, .35);
    backdrop-filter: blur(4px);
    z-index: 1100;
    cursor: pointer;
  }

  .sb-hamburger .sb-bar {
    display: block;
    width: 15%;
    height: 2px;
    background: #fff;
    border-radius: 2px;
  }

  /* Overlay del drawer */
  .sb-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, .45);
    z-index: 1040;
  }

  /* Estado del body sin scroll cuando el drawer está abierto */
  .body-nosrc {
    overflow: hidden !important;
  }

  /* ===== Desktop: tu sidebar fijo como siempre ===== */
  @media (min-width: 1025px) {
    .sb-hamburger {
      display: none;
    }

    /* nada cambia: tu .sb sigue fijo y body mantiene padding-left */
  }

  /* ===== Móvil/Tablet: sidebar como drawer ===== */
   @media (max-width: 1300px) {
   .sb__logout form {
      margin-bottom: 100px;
    }
   }

  @media (max-width: 1000px) {
    .sb-hamburger {
      display: inline-flex;
    }

    .iconOpcBig {
      width: 60%;
      height: 60%;
    }

    .iconmove {
      margin-bottom: 10px;
    }

    /* El body NO deja espacio al costado en móvil */
    body {
      padding-left: 0 !important;

    }

    .sb__logout form {
      margin-bottom: 150px;
    }

    /* El nav.sb pasa a off-canvas */
    .sb {
      position: fixed;
      top: 0;
      left: 0;
      bottom: 0;
      width: min(80vw, 13%);
      height: 100vh;
      transform: translateX(-100%);
      transition: transform .28s cubic-bezier(.4, 0, .2, 1);
      z-index: 1050;
      overflow: auto;
    }

    .sb.is-open {
      transform: translateX(0);
    }

    /* Ocultar tooltips en móvil (mejor UX) */
    .sb__nav a::after,
    .sb__logout-btn::after {
      display: none !important;
    }


  }

  @media (max-width: 700px) {
    .sb {
      position: fixed;
      top: 0;
      left: 0;
      bottom: 0;
      width: min(80vw, 18%);
      height: 100vh;
      transform: translateX(-100%);
      transition: transform .28s cubic-bezier(.4, 0, .2, 1);
      z-index: 1050;
    }

    .sb__logout form {
      margin-bottom: 120px;
    }
  }




  .sb {
    content-visibility: auto;
    contain-intrinsic-size: 100vh 84px;
  }

  .sb__nav img {
    display: block;
  }

  /* evita baseline jiggle */
  #app,
  main.container,
  main {
    /* fallback si no tienes #app */
    opacity: 0;
    transform: translateY(6px);
    transition: opacity .32s ease, transform .32s ease;
  }

  .is-ready #app,
  .is-ready main.container,
  .is-ready main {
    opacity: 1;
    transform: none;
  }

  .is-leaving #app,
  .is-leaving main.container,
  .is-leaving main {
    opacity: 0;
    transform: translateY(6px);
  }

  /* Skeleton sencillo (úsalo donde quieras) */
  .sk-line {
    height: 16px;
    border-radius: 10px;
    margin: 10px 0 18px;
    background: linear-gradient(100deg, #eee 40%, #f5f5f5 50%, #eee 60%);
    background-size: 200% 100%;
    animation: sk 1s linear infinite;
  }

  @keyframes sk {
    to {
      background-position: -200% 0;
    }
  }

  /* Revelado escalonado de filas/tarjetas (lista/tables) */
  .reveal-list>* {
    opacity: 0;
    transform: translateY(6px);
    animation: fadeUp .34s ease forwards;
    animation-delay: calc(var(--stagger, 40ms) * var(--i, 0));
  }

  @keyframes fadeUp {
    to {
      opacity: 1;
      transform: none;
    }
  }

  /* Imágenes sin “jiggle” de baseline */
  img {
    display: block;
  }

  /* Accesibilidad: sin animaciones si el usuario lo pide */
  @media (prefers-reduced-motion: reduce) {

    #app,
    main.container,
    main {
      transition: none !important;
      transform: none !important;
      opacity: 1 !important;
    }

    .reveal-list>* {
      animation: none !important;
      opacity: 1 !important;
      transform: none !important;
    }

    .sk-line {
      animation: none !important;
    }
  }

  .sb__avatar{
  position: relative;
  overflow: hidden;
}
.sb__avatar-initial{
  position:absolute; inset:0;
  display:grid; place-items:center;
  font: 700 18px/1 ui-sans-serif, system-ui, "Segoe UI", Roboto, Arial;
  color:#fff;
  /* tu fondo bonito ya existe en .sb__avatar */
}
.sb__avatar-img{
  position:absolute; inset:0;
  width:100%; height:100%;
  object-fit: cover; display:block;
  opacity:0; transition: opacity .22s ease;
}
.sb__avatar-img.is-ready{ opacity:1; }

</style>
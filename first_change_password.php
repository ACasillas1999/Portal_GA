<?php
// C:\xampp\htdocs\Portal_GA\first_change_password.php
declare(strict_types=1);

const BASE_URL = '';

// Inicia sesión SOLO si no está activa
if (session_status() === PHP_SESSION_NONE) {
  session_name('GA');
  session_start();
}

require_once __DIR__ . '/app/db.php';

// Debe estar logueado
if (!($_SESSION['loggedin'] ?? false)) { header('Location: ' . BASE_URL . '/'); exit; }
$id = (int)($_SESSION['user_id'] ?? 0);

// Si ya cambió la contraseña, manda al panel
$st = $pdo->prepare('SELECT password_changed_at FROM usuarios WHERE id = :id');
$st->execute([':id' => $id]);
if ($st->fetchColumn()) { header('Location: ' . BASE_URL . '/Inicio/'); exit; }

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $p1 = (string)($_POST['p1'] ?? '');
  $p2 = (string)($_POST['p2'] ?? '');
  if ($p1 !== $p2) {
    $msg = 'Las contraseñas no coinciden.';
  } elseif (!preg_match('/^(?=.*[A-Z])(?=.*\d).{8,}$/', $p1)) {
    $msg = 'Mínimo 8 caracteres, con al menos 1 mayúscula y 1 número.';
  } else {
    $hash = password_hash($p1, PASSWORD_DEFAULT);
    $up = $pdo->prepare('UPDATE usuarios SET password = :h, password_changed_at = NOW(), updated_at = NOW() WHERE id = :id');
    $up->execute([':h' => $hash, ':id' => $id]);
    header('Location: ' . BASE_URL . '/Inicio/'); exit;
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Definir nueva contraseña</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="/portal_ga/assets/img/iconpestalla.png" type="image/x-icon">
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet" />
  <style>
    :root{
      --ink:#e5e7eb;
      --muted:#94a3b8;
      --primary:#3b82f6;
      --primary-600:#2563eb;
      --ok:#22c55e;
      --warn:#f59e0b;
      --bad:#ef4444;
      --card:rgba(17, 24, 39, .72);
      --stroke:rgba(255,255,255,.12);
      --radius:18px;
      --shadow:0 16px 48px rgba(2,12,32,.30);
      --focus:0 0 0 3px rgba(59,130,246,.35);
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{
      margin:0;
      font-family:Montserrat,system-ui,-apple-system,"Segoe UI",Roboto,Arial,"Noto Sans","Helvetica Neue",sans-serif;
      color:var(--ink);
      background:#0b1020;
      /* fondo degradado animado */
      background-image:
        radial-gradient(1200px 600px at 20% -10%, rgba(59,130,246,.28), transparent),
        radial-gradient(900px 600px at 110% 20%, rgba(34,197,94,.18), transparent),
        radial-gradient(700px 500px at 30% 120%, rgba(236,72,153,.15), transparent);
      overflow:auto;
    }
    .grid{
      min-height:100%;
      display:grid;
      place-items:center;
      padding:32px 16px;
    }
    .card{
      width:min(540px, 92vw);
      background:var(--card);
      border:1px solid var(--stroke);
      border-radius:var(--radius);
      box-shadow:var(--shadow);
      backdrop-filter: blur(12px);
      padding:26px 22px 22px;
      position:relative;
      overflow:hidden;
    }
    .brand{
      display:flex; align-items:center; gap:10px; margin-bottom:8px;
    }
    .brand img{height:40px}
    .tag{
      display:inline-block;
      font-size:.78rem; letter-spacing:.02em;
      color:#cbd5e1; background:rgba(148,163,184,.14);
      border:1px dashed rgba(148,163,184,.35);
      padding:6px 10px; border-radius:999px; margin-bottom:8px;
    }
    h1{margin:4px 0 6px; font-size:1.35rem}
    .hint{margin:0 0 16px; color:var(--muted); line-height:1.5}
    .err{
      background:linear-gradient(0deg, rgba(239,68,68,.14), rgba(239,68,68,.14));
      border:1px solid rgba(239,68,68,.35);
      color:#fecaca;
      padding:10px 12px; border-radius:12px; margin:8px 0 14px;
    }
    .field{
      margin-top:14px;
    }
    .label{
      display:flex; justify-content:space-between; align-items:center;
      font-size:.9rem; color:#cbd5e1; margin-bottom:8px;
    }
    .control{
      position:relative;
    }
    .control input{
      width:100%;
      padding:12px 44px 12px 42px;
      border-radius:14px;
      border:1px solid rgba(255,255,255,.14);
      background:rgba(7,11,24,.65);
      color:var(--ink);
      outline:none;
      transition:border .2s, box-shadow .2s, transform .06s;
    }
    .control input:focus{
      border-color:rgba(59,130,246,.55);
      box-shadow:var(--focus);
    }
    .icon-left, .icon-right{
      position:absolute; top:50%; transform:translateY(-50%);
      width:22px; height:22px; opacity:.9;
      display:grid; place-items:center;
    }
    .icon-left{ left:12px; }
    .icon-right{ right:12px; cursor:pointer; }
    .reqs{
      display:grid; grid-template-columns:1fr 1fr; gap:8px 14px;
      margin-top:12px; font-size:.86rem; color:#cbd5e1;
    }
    .req{ display:flex; align-items:center; gap:8px; opacity:.85}
    .req svg{ width:16px; height:16px }
    .req.ok{ color:var(--ok); opacity:1 }
    .meter{
      margin-top:14px;
      height:8px; background:rgba(148,163,184,.18);
      border:1px solid rgba(148,163,184,.28);
      border-radius:999px; overflow:hidden;
    }
    .bar{ height:100%; width:0%; background:linear-gradient(90deg, #ef4444, #f59e0b, #22c55e); transition:width .25s }
    .actions{ margin-top:18px }
    .btn{
      width:100%; padding:12px 14px; border:0; border-radius:14px;
      background:linear-gradient(180deg, var(--primary), var(--primary-600));
      color:white; font-weight:700; letter-spacing:.02em; cursor:pointer;
      box-shadow:0 8px 24px rgba(37,99,235,.35);
      transition: transform .06s ease;
    }
    .btn:active{ transform: translateY(1px) }
    .tip{
      margin-top:12px; text-align:center; color:#a5b4fc; font-size:.9rem;
      opacity:.9;
    }
    .water{
      position:absolute; inset:auto -20% -35% -20%;
      height:240px; background:radial-gradient(60% 120% at 50% 0%, rgba(59,130,246,.12), transparent);
      filter:blur(24px); pointer-events:none;
    }

    /* responsive */
    @media (max-width:480px){
      .reqs{ grid-template-columns:1fr; }
    }
  </style>

  <!-- Microsoft Clarity (igual que lo tienes) -->
  <script type="text/javascript">
    (function(c,l,a,r,i,t,y){
      c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
      t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
      y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
    })(window, document, "clarity", "script", "tm0znm12k6");
  </script>

  <?php
  // datos mínimos para identify (si ya hiciste session_start arriba, no lo repitas)
  $clarity_id   = $_SESSION['user_id']  ?? '';
  $clarity_name = $_SESSION['username'] ?? ($_SESSION['Email'] ?? '');
  ?>
  <script>
    (function(){
      if (!window.__clarityIdentDone) {
        window.__clarityIdentDone = true;
        window.clarity = window.clarity || function(){(window.clarity.q=window.clarity.q||[]).push(arguments);};
        <?php if (!empty($clarity_id) || !empty($clarity_name)): ?>
          clarity("identify", "<?= htmlspecialchars($clarity_name ?: $clarity_id, ENT_QUOTES, 'UTF-8') ?>");
        <?php endif; ?>
      }
    })();
  </script>
</head>

<body>
  <div class="grid">
    <div class="card">
      <span class="tag">Seguridad de la cuenta</span>
      
      <h1>Definir nueva contraseña</h1>
      <p class="hint">Por seguridad, establece tu contraseña personal antes de continuar.</p>

      <?php if ($msg): ?>
        <div class="err"><?= htmlspecialchars($msg) ?></div>
      <?php endif; ?>

      <form method="post" autocomplete="new-password" id="form-pw">
        <div class="field">
          <div class="label">Nueva contraseña</div>
          <div class="control">
            <span class="icon-left" aria-hidden="true">
              <!-- candado -->
              <svg viewBox="0 0 24 24" fill="none"><path d="M7 10V8a5 5 0 0 1 10 0v2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><rect x="4.5" y="10" width="15" height="10" rx="2.2" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="15" r="1.6" fill="currentColor"/></svg>
            </span>
            <input type="password" name="p1" id="p1" required minlength="8" autofocus>
            <span class="icon-right" id="toggle1" title="Mostrar/ocultar">
              <svg id="eye1" viewBox="0 0 24 24" fill="none"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6"/></svg>
            </span>
          </div>
        </div>

        <div class="field">
          <div class="label">Repite la contraseña</div>
          <div class="control">
            <span class="icon-left" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none"><path d="M7 10V8a5 5 0 0 1 10 0v2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><rect x="4.5" y="10" width="15" height="10" rx="2.2" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="15" r="1.6" fill="currentColor"/></svg>
            </span>
            <input type="password" name="p2" id="p2" required minlength="8">
            <span class="icon-right" id="toggle2" title="Mostrar/ocultar">
              <svg id="eye2" viewBox="0 0 24 24" fill="none"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6"/></svg>
            </span>
          </div>
        </div>

        <!-- checklist en vivo -->
        <div class="reqs" id="reqs">
          <div class="req" data-rule="len"><svg viewBox="0 0 24 24" fill="none"><path d="M5 12l4 4L19 6" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg> Al menos 8 caracteres</div>
          <div class="req" data-rule="upper"><svg viewBox="0 0 24 24" fill="none"><path d="M5 12l4 4L19 6" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg> 1 mayúscula (A-Z)</div>
          <div class="req" data-rule="num"><svg viewBox="0 0 24 24" fill="none"><path d="M5 12l4 4L19 6" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg> 1 número (0-9)</div>
          <div class="req" data-rule="match"><svg viewBox="0 0 24 24" fill="none"><path d="M5 12l4 4L19 6" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg> Coinciden</div>
        </div>

        <div class="meter" aria-hidden="true"><div class="bar" id="bar"></div></div>

        <div class="actions">
          <button class="btn" type="submit">Guardar y continuar</button>
        </div>
        <div class="tip">Consejo: usa una frase fácil de recordar y difícil de adivinar.</div>
      </form>

      <div class="water"></div>
    </div>
  </div>

  <script>
    const p1 = document.getElementById('p1');
    const p2 = document.getElementById('p2');
    const bar = document.getElementById('bar');
    const reqs = {
      len:   document.querySelector('.req[data-rule="len"]'),
      upper: document.querySelector('.req[data-rule="upper"]'),
      num:   document.querySelector('.req[data-rule="num"]'),
      match: document.querySelector('.req[data-rule="match"]')
    }

    function score(s){
      let sc = 0;
      if (s.length >= 8) sc += 30;
      if (/[A-Z]/.test(s)) sc += 30;
      if (/\d/.test(s))    sc += 30;
      if (/[^\w]/.test(s)) sc += 10; // bonus símbolo
      return Math.min(sc, 100);
    }
    function refresh(){
      const v1 = p1.value, v2 = p2.value;
      reqs.len.classList.toggle('ok', v1.length >= 8);
      reqs.upper.classList.toggle('ok', /[A-Z]/.test(v1));
      reqs.num.classList.toggle('ok', /\d/.test(v1));
      reqs.match.classList.toggle('ok', v1 !== '' && v1 === v2);

      const s = score(v1);
      bar.style.width = s + '%';
    }
    p1.addEventListener('input', refresh);
    p2.addEventListener('input', refresh);
    refresh();

    // toggles mostrar/ocultar
    function toggle(el){
      const inp = el.previousElementSibling; // input
      if (!inp) return;
      inp.type = inp.type === 'password' ? 'text' : 'password';
    }
    document.getElementById('toggle1').addEventListener('click', ()=>toggle(document.getElementById('toggle1')));
    document.getElementById('toggle2').addEventListener('click', ()=>toggle(document.getElementById('toggle2')));

    // validación final en submit (refuerza el patrón que ya tienes en PHP)
    document.getElementById('form-pw').addEventListener('submit', function(e){
      const v1 = p1.value, v2 = p2.value;
      const ok = v1.length>=8 && /[A-Z]/.test(v1) && /\d/.test(v1) && v1===v2;
      if (!ok){
        e.preventDefault();
        // feedback mínimo
        p1.focus();
        p1.scrollIntoView({behavior:'smooth', block:'center'});
      }
    });
  </script>
</body>
</html>

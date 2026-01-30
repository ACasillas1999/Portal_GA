<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/db.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$valid = false; $msg = '';
$email = $_GET['email'] ?? '';
$token = $_GET['token'] ?? '';

try {
  if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !$token) throw new Exception('Enlace inválido.');
  $st = $pdo->prepare('SELECT id, reset_token_hash, reset_expires FROM usuarios WHERE email = ? LIMIT 1');
  $st->execute([$email]);
  $u = $st->fetch();
  if (!$u) throw new Exception('Enlace inválido.');

  if (empty($u['reset_expires']) || strtotime($u['reset_expires']) < time()) {
    throw new Exception('El enlace ha expirado. Vuelve a solicitarlo.');
  }

  $hashBin = hash('sha256', $token, true);
  if (!is_string($u['reset_token_hash']) || !hash_equals($u['reset_token_hash'], $hashBin)) {
    throw new Exception('El enlace no es válido.');
  }

  $valid = true;
} catch (Throwable $e) {
  $msg = $e->getMessage();
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Restablecer contraseña</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<style>
  :root{
    /* PALETA AZUL + NARANJA */
    --blue-900:#0b1430;
    --blue-800:#0e1f45;
    --blue-700:#123061;
    --blue-500:#2563eb;  /* enlaces y detalles azules */
    --glass: rgba(255,255,255,.07);
    --muted:#c9d3ee;
    --stroke:#1e3a8a;    /* borde azulado */
    --brand:#ff8f3c;     /* NARANJA principal (botón) */
    --brand-2:#ffb26e;   /* naranjita claro para hover/links secundarios */
    --ok:#28e096;
    --err:#ff7b7b;
  }
  *{box-sizing:border-box}
  body{
    margin:0; min-height:100dvh; display:grid; place-items:center;
    font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
    color:#eaf0ff;
    background:
      radial-gradient(800px 480px at 20% -10%, #163a7a 0%, transparent 60%),
      radial-gradient(900px 520px at 85% 110%, #0f2d66 0%, transparent 60%),
      linear-gradient(180deg, var(--blue-800), var(--blue-900));
  }
  .wrap{ width:min(96%, 900px); padding:20px}
  .card{
    position:relative; overflow:hidden;
    margin:0 auto; width:min(100%, 520px);
    padding:26px 24px 22px;
    border-radius:20px; background:var(--glass);
    box-shadow: 0 24px 60px rgba(0,0,0,.45), inset 0 0 0 1px rgba(255,255,255,.05);
    backdrop-filter: blur(8px);
  }
  .brand{
    display:flex; align-items:center; gap:10px; margin-bottom:8px;
  }
  .brand img{ height:42px; width:auto; display:block; }
  .brand span{
    letter-spacing:.3px; font-weight:800; opacity:.95;
    background: linear-gradient(90deg, var(--brand) 0%, var(--blue-500) 100%);
    -webkit-background-clip:text; background-clip:text; color:transparent;
  }
  h1{ margin:10px 0 8px; font-size:22px }
  p.muted{ color:var(--muted); margin:0 0 14px }
  .hint{ font-size:12px; color:var(--muted); margin-top:8px }

  .field{ position:relative; margin:12px 0 }
  .field input{
    width:100%; padding:12px 44px 12px 44px;
    border-radius:12px; border:1px solid var(--stroke);
    background:rgba(8,18,45,.7); color:#eaf0ff; outline:none;
    transition:border .15s ease, box-shadow .15s ease;
    box-shadow: inset 0 0 0 1px rgba(255,255,255,.02);
  }
  .field input::placeholder{ color:#9fb2e3 }
  .field input:focus{
    border-color:#3a66ff; box-shadow: 0 0 0 3px rgba(37,99,235,.25);
    background:rgba(13,29,73,.75);
  }
  .icon{
    position:absolute; left:12px; top:50%; transform:translateY(-50%); opacity:.8; color:#9fb2e3;
  }
  .toggle{
    position:absolute; right:10px; top:50%; transform:translateY(-50%);
    padding:6px; border-radius:8px; cursor:pointer; opacity:.9; color:#cfe0ff;
  }
  .toggle:hover{ background:rgba(255,255,255,.08); }

  button.btn{
    width:100%; margin-top:12px; border:0; cursor:pointer;
    background:var(--brand); color:#111; font-weight:800; letter-spacing:.2px;
    padding:12px 16px; border-radius:12px; transition:transform .08s ease, filter .2s ease, box-shadow .2s ease;
    box-shadow: 0 6px 24px rgba(255,143,60,.35);
  }
  button.btn:hover{ filter:brightness(1.02) }
  button.btn:active{ transform:scale(.99) }
  button.btn[disabled]{ filter:saturate(.3) brightness(.8); cursor:not-allowed; box-shadow:none }

  /* Meter (azul) */
  .meter{ display:grid; grid-template-columns:repeat(4,1fr); gap:8px; margin:10px 0 4px }
  .bar{ height:8px; border-radius:6px; background:rgba(25,49,104,.8); overflow:hidden; border:1px solid rgba(255,255,255,.05) }
  .bar.fill{ background:linear-gradient(90deg, #5b86ff, #2ea0ff) }
  .meter-text{ font-size:12px; color:var(--muted); min-height:16px }

  /* Links (azul / naranja suave) */
  .links{ display:flex; justify-content:space-between; margin-top:14px; font-size:13px }
  .links a{ color:var(--brand-2); text-decoration:none }
  .links a:hover{ color:#ffd7b1; text-decoration:underline }
  .links a.primary{ color:var(--blue-500) }
  .links a.primary:hover{ color:#7aa6ff }

  /* Estado error */
  .state-err h1{ color:var(--err) }
  .state-err .card{ box-shadow: 0 24px 60px rgba(255, 85, 85, .2), inset 0 0 0 1px rgba(255,255,255,.05); }

  /* Modo claro básico */
  @media (prefers-color-scheme: light){
    body{ color:#14203a }
    .card{ background:rgba(255,255,255,.88); color:#14203a }
    .field input{ background:#fff; color:#0f1422 }
  }
</style>
</head>
<body>
  <div class="wrap">
    <div class="card <?= $valid ? '' : 'state-err' ?>">
      <div class="brand">
        <img src="<?= h(APP_URL) ?>/assets/img/LogoColor.GrupoAscencio.svg" alt="Grupo Ascencio">
        <span>Portal GA</span>
      </div>

      <?php if (!$valid): ?>
        <h1>No se puede restablecer</h1>
        <p class="muted"><?= h($msg ?: 'Link inválido o expirado.') ?></p>
        <div class="links">
          <a class="primary" href="<?= h(APP_URL) ?>/">← Volver al inicio</a>
          <a href="<?= h(APP_URL) ?>/">Solicitar un nuevo enlace</a>
        </div>
      <?php else: ?>
        <h1>Restablecer contraseña</h1>
        <p class="muted">Crea una nueva contraseña para <strong><?= h($email) ?></strong>.</p>

        <form id="reset-form" onsubmit="return false;">
          <input type="hidden" id="email" value="<?= h($email) ?>">
          <input type="hidden" id="token" value="<?= h($token) ?>">

          <label for="pass1" class="muted">Nueva contraseña</label>
          <div class="field">
            <span class="icon" aria-hidden="true">
              <!-- candado -->
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M7 10V8a5 5 0 1 1 10 0v2" stroke="currentColor" stroke-width="1.6"/><rect x="5" y="10" width="14" height="10" rx="2" stroke="currentColor" stroke-width="1.6"/></svg>
            </span>
            <input id="pass1" type="password" minlength="6" autocomplete="new-password" required placeholder="••••••••">
            <span class="toggle" data-target="pass1" title="Mostrar/ocultar">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z" stroke="currentColor"/><circle cx="12" cy="12" r="3.5" stroke="currentColor"/></svg>
            </span>
          </div>

          <div class="meter" aria-hidden="true">
            <div class="bar" id="m1"></div>
            <div class="bar" id="m2"></div>
            <div class="bar" id="m3"></div>
            <div class="bar" id="m4"></div>
          </div>
          <div class="meter-text" id="meterText">Consejo: usa 8+ caracteres con letras, números y símbolo.</div>

          <label for="pass2" class="muted">Confirmar contraseña</label>
          <div class="field">
            <span class="icon" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M7 10V8a5 5 0 1 1 10 0v2" stroke="currentColor" stroke-width="1.6"/><rect x="5" y="10" width="14" height="10" rx="2" stroke="currentColor" stroke-width="1.6"/></svg>
            </span>
            <input id="pass2" type="password" minlength="6" autocomplete="new-password" required placeholder="••••••••">
            <span class="toggle" data-target="pass2" title="Mostrar/ocultar">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z" stroke="currentColor"/><circle cx="12" cy="12" r="3.5" stroke="currentColor"/></svg>
            </span>
          </div>

          <button id="btnReset" class="btn" disabled>Guardar contraseña</button>
          <div class="hint">El enlace vence en 1 hora. Si no fuiste tú, ignora este mensaje.</div>

          <div class="links">
            <a class="primary" href="<?= h(APP_URL) ?>/">← Volver al inicio</a>
            <a href="<?= h(APP_URL) ?>/">Ayuda</a>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>

<?php if ($valid): ?>
<script>
(function(){
  const $p1 = $("#pass1"), $p2 = $("#pass2"), $btn = $("#btnReset");
  const bars = [$("#m1"),$("#m2"),$("#m3"),$("#m4")], $meterText = $("#meterText");

  function score(p){
    let s = 0;
    if(p.length >= 6) s++;
    if(/[A-ZÁÉÍÓÚÑ]/.test(p) && /[a-záéíóúñ]/.test(p)) s++;
    if(/\d/.test(p)) s++;
    if(/[^\w\s]/.test(p)) s++;
    return s; // 0..4
  }
  function paintMeter(n){
    bars.forEach((b,i)=> b.toggleClass("fill", i < n));
    const txt = ["Muy débil","Débil","Aceptable","Fuerte","Muy fuerte"][n] || "Muy débil";
    $meterText.text("Fuerza: " + txt + (n<3 ? " · Usa letras, números y símbolo." : ""));
  }
  function canSubmit(){
    const okLen = $p1.val().length >= 6;
    const okMatch = $p1.val() && $p1.val() === $p2.val();
    return okLen && okMatch;
  }
  $p1.on("input", () => { paintMeter(score($p1.val())); $btn.prop("disabled", !canSubmit()); });
  $p2.on("input", () => $btn.prop("disabled", !canSubmit()));
  $(".toggle").on("click", function(){
    const id = $(this).data("target"); const el = document.getElementById(id);
    el.type = el.type === "password" ? "text" : "password";
  });

  $("#btnReset").on("click", function(){
    const p1 = $p1.val(), p2 = $p2.val();
    if(p1.length < 6){ Swal.fire('Ups','La contraseña debe tener al menos 6 caracteres.','info'); return; }
    if(p1 !== p2){ Swal.fire('Ups','Las contraseñas no coinciden.','info'); return; }

    const original = $btn.text(); $btn.prop("disabled", true).text("Guardando…");

    $.ajax({
      url: 'reset_password_api.php',
      method: 'POST',
      contentType: 'application/json; charset=utf-8',
      dataType: 'json',
      data: JSON.stringify({ email: $("#email").val(), token: $("#token").val(), password: p1 })
    }).done(res => {
      Swal.fire({ icon: res.ok ? 'success' : 'error', title: res.ok ? 'Listo' : 'No se pudo', text: res.msg || '' })
        .then(()=>{ if(res.ok) window.location.href = "<?= h(APP_URL) ?>/"; });
    }).fail(() => Swal.fire('Error','No se pudo restablecer.','error'))
      .always(()=> $btn.prop("disabled", false).text(original));
  });
})();
</script>
<?php endif; ?>
</body>
</html>

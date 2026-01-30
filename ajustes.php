<?php
// ajustes.php
session_name("GA");
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/session_boot.php';
require_login(); 
if (empty($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/');
    exit;
}

$st = $pdo->prepare('SELECT id, username, email, email_verified_at, RFC, Telefono, created_at, password_changed_at, avatar FROM usuarios WHERE id=? LIMIT 1');
$st->execute([$_SESSION['user_id']]);
$user = $st->fetch();
if (!$user) {
    header('Location: ' . APP_URL . '/');
    exit;
}

function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" href="/portal_ga/assets/img/iconpestalla.png" type="image/x-icon">
    <title>Ajustes — Portal GA</title>

    <!-- Hoja principal del portal -->
    <link rel="stylesheet" href="<?= APP_URL ?>/Estilos/style_index.css?v=1.1">

    <!--Fuentes Externas-->
    <!--Fuentes-->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Mapeo y acomodo (compat con sidebar) + fallback de tarjetas -->
    <style>
        :root {
            --sidebar-w: var(--sb-width, 88px);
            --font-sans: 'Montserrat', system-ui, -apple-system, 'Segoe UI',Roboto, 'Helvetica Neue', Arial, 'Noto Sans', sans-serif;

            /*Pruebas*/
            
        }

        body{
            background-color:#f2f2f2;
        }
      
    .client-portal {
    display: flex;
    flex-direction: column; /* Mantiene el orden vertical */
    align-items: center;   /* Centra horizontalmente */
    justify-content: center; /* Centra verticalmente */
    min-height: 100vh; /* Hace que ocupe toda la pantalla */
    margin-left: 0; /* Mantén esta línea si es necesaria */
}

            

    


        /* une variables sidebar/layout */
        .client-portal {
            margin-left: 0;
        }

        /* evita doble sangría */

        /* Layout base del portal (fallback si no cargara style_index.css) */
        .client-portal .container {
            width: min(calc(100% - 40px), 1200px);
            margin-inline: auto;
        }

        .hero {
            text-align: center;
            padding: 72px 0 12px;
        }

        .hero h1 {
            font-family: var(--font-sans);
            margin: 0 0 10px;
            line-height: 1.05;
            font-weight: 900;
            font-size: clamp(36px, 4.4vw, 95px);
            color:#88888a;
            letter-spacing: .2px;
            text-align:left;
        }

        /*.hero .outline {
            color: transparent;
            -webkit-text-stroke: 2px #4475d1;
        }*/

        .hero .subtitle {
            font-family: var(--font-sans);
            font-weight: 600;
            color: #061937;
            margin: 6px auto 0;
            max-width: 860px;
             font-size: clamp(16px, 1.6vw, 50px);
        }

        .portal-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 28px;
            padding: 26px 0 80px;
            align-items: stretch;
        }

        @media (max-width:1024px) {
            .portal-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width:720px) {
            .portal-cards {
                grid-template-columns: 1fr;
            }
        }

        .portal-card {
            display: inline-grid;
            flex-direction: column;
            justify-content: flex-start;
            place-items: center;
            align-items: center;
            min-height: 330px;
            border-radius: 18px;
            padding: 26px;
            box-shadow: 0 14px 40px rgba(0, 0, 0, .25);
            outline: 1px solid rgba(255, 255, 255, .06);
            color: #fff;
            transition: transform .25s ease, box-shadow .25s ease, outline-color .25s ease;
        }

        .portal-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 18px 48px rgba(0, 0, 0, .35);
            outline-color: rgba(255, 255, 255, .16);
        }

        .portal-card .icon {
            width: 65px;
            height: 90px;
            display: grid;
            place-items: center;
            border-radius: 18px;
            /*background: rgba(255, 255, 255, .08);*/
            margin-bottom: 8px;
        }

        .portal-card h3 {
            font-family: var(--font-sans);
            font-weight: 900;
            color: #595959;
            margin: 0 0 6px;
            font-size: 25px;
            letter-spacing: .6px;
        }

        .portal-card p {
            font-family: var(--font-sans);
            font-weight: 500;
            margin: 0 0 16px;
           color: #595959;
            min-height: 48px;
            text-align: center
        }

        .card--blue {
            /*background: linear-gradient(180deg, #0a3f77, #005996);*/
            
            background-color:#ffffff;
        }

        .card--navy {
           /* background: linear-gradient(180deg, #0f2246, #061937);*/
            background-color:#ffffff;
        }

        .card--orange {
           /*background: linear-gradient(180deg, #ff7f3a, #ED6C24);*/
           background-color:#ffffff;
        }

        /* Formularios compactos */
        .form-grid {
            display: grid;
            gap: 8px;
            font-family: var(--font-sans);
            color: #595959;

        }

        .form-2 {
            grid-template-columns: 1fr 1fr;
        }

        .form-3 {
            display: flex
;
    flex-direction: column;
            width: 100%;
        }

        @media (max-width:720px) {
            .form-2 {
                grid-template-columns: 1fr;
            }

            article.portal-card.card--navy {
                flex-direction: column;
            grid-template-columns: 1fr;
            
            }

            article.portal-card.card--blue {
                flex-direction: column;
            grid-template-columns: 1fr;
            
            }


           #formPass{
            width: 100%;
           }
           #formPerfil{
            width: 100%;
           }
           #formEmail{
            grid-template-columns: 1fr;
            width: 100%;
           }
           
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
           
        }

        .field label {
            font-size: 13px;
            color: #595959;
        }
        
        

        .field input {
            width: 100%;
            padding: 11px 12px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, .18);
            background: rgba(255, 255, 255, .08);
            color: #595959;
            outline: none;
            transition: border-color .2s, box-shadow .2s, background .2s;
        }

        .field input::placeholder {
            color: #595959;
        }

        .field input:focus {
            border-color: #6aa8ff;
            box-shadow: 0 0 0 3px rgba(106, 168, 255, .22);
            background: rgba(255, 255, 255, .12);
        }

        /* Botonera pegada al fondo de la card */
        .actions {
            margin-top: auto;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .btn-primary {
            appearance: none;
            border: 0;
            cursor: pointer;
            background: #ED6C24 !important;
            color: #ffffffff !important;
            font-weight: 900;
            padding: 12px 16px;
            border-radius: 8px;
            margin-top:15px;
            /*box-shadow: 0 8px 26px rgba(237, 108, 36, .35);
            transition: transform .06s, filter .2s, box-shadow .2s;*/
        }

        .btn-correo{
            width: 100% !important;
            margin-top:3px;
        }

        .btn-contrasena {
           display: flex;
           justify-content: center;  /* Centra horizontalmente */
           align-items: center;      
           width: 100%;
        }

        .btn-primary:active {
            transform: translateY(1px);
        }

        .btn-ghost {
            display: flex;
            margin-left:auto;
            appearance: none;
            border: 1px solid rgba(255, 255, 255, .28);
            color: #ffffffff;
            background:#88888a;
            padding: 12px 16px;
            border-radius: 8px;
            font-weight: 900;
            margin-top:15px;
            cursor: pointer;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
           
        }

        .badge.ok {
           
            background: rgba(24, 199, 134, .16);
            color: #9ff0cc;
              vertical-align: middle;
        }

        .badge.warn {
            background: rgba(237, 108, 36, .18);
            color: #ffd3b7
        }

        /* Botón de avatar en la card */
.avatar-wrap{ display:grid; place-items:center; background:rgba(255,255,255,.08); border-radius:18px; }
.avatar-btn{
  width:78px; height:78px; border:0; border-radius:50%;
  display:grid; place-items:center; cursor:pointer; background:#0b1b2f; overflow:hidden;
  box-shadow: inset 0 0 0 2px rgba(255,255,255,.08);
}
.avatar-btn img{ width:100%; height:100%; object-fit:cover; display:block; }
.avatar-initial{
  font:700 28px/1 ui-sans-serif,system-ui,"Segoe UI",Roboto,Arial;
  color:#fff;
}

/* Picker de avatares (SweetAlert2) */
.ava-grid{
  display:grid; grid-template-columns: repeat(5, 1fr);
  gap:12px; margin-top:6px;
}
.ava{
  border:0; background:transparent; padding:8px; border-radius:12px; cursor:pointer;
  outline:1px solid rgba(0,0,0,.12);
  background: rgba(0,0,0,.04);
  transition: transform .06s ease, box-shadow .2s ease, outline-color .2s ease;
}
.ava:hover{ transform: translateY(-1px); }
.ava.selected{ outline:2px solid #ED6C24; box-shadow:0 0 0 3px rgba(237,108,36,.25); }
.ava img{ width:72px; height:72px; display:block; border-radius:50%; }
@media (max-width:640px){ .ava-grid{ grid-template-columns: repeat(3, 1fr); } }
/* deja que las columnas del grid se encojan y que la fila de botones ocupe el ancho */
.portal-card .form-2 > .field{ min-width:0; }
.portal-card .field{ min-width:0; font-family: var(--font-sans) !important; font-size:13px;}
.portal-card .actions{ 
    grid-column: 1 / -1; 
    justify-items: center !important;
    align-items: center !important; 
}
.btn-primary, .btn-ghost{
    width: 100%;
    align-items: center !important; 
    justify-content: center;    /* centra horizontal */
  align-items: center; 
}
.portal-card .field input{
            
             color: #595959 !important;
             border: 1px solid #595959 !important;
        }

.portal-card h3, p{
             color: #595959 !important;

             
        }
        .field-correo{
            width: 100% !important;
        }

        .swal2-confirm {
  background-color: #ed6b1f !important; /* naranja corporativo */
  color: #fff !important;
  border-radius: 4px !important;
}
.swal2-cancel {
  background-color: #88888a !important; /* azul corporativo */
  color: #fff !important;
  border-radius: 4px !important;
}


/*Pruebas*/
.portal-card.card--orange{
  display: grid !important;         /* seguimos en grid */
  grid-template-columns: 1fr;
  grid-auto-rows: max-content;      /* que cada fila mida su contenido */
  row-gap: 12px;
  justify-items: stretch;           /* hijos ocupan 100% del ancho */
  align-content: center;            /* bloque centrado verticalmente */
  text-align: center;
}

/* El icono no se estira, solo se centra */
.portal-card.card--orange .icon{
  justify-self: center;             /* centra horizontalmente el contenedor del icono */
  width: 65px;                      /* conserva tamaño */
  height: 90px;
  display: grid;
  place-items: center;
}
.portal-card.card--orange .icon img{
  display: block;
  max-width: 100%;
  height: auto;                     /* evita deformación */
}

/* Título, texto y bloques de formulario: a 100% */
.portal-card.card--orange h3,
.portal-card.card--orange p,
.portal-card.card--orange .form-grid,
.portal-card.card--orange .form-3,
.portal-card.card--orange .field,
.portal-card.card--orange .actions{
  width: 100%;
}

/* La fila "Correo actual" centrada y al 100% */
.portal-card.card--orange .field > div{
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  flex-wrap: wrap;
  width: 100%;
}
.portal-card.card--orange .field label{
  text-align: left !important;
}


/** */
    </style>

    <script>
        const APP_URL = "<?= APP_URL ?>";
    </script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Microsoft Clarity -->
<script type="text/javascript">
  (function(c,l,a,r,i,t,y){
    c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
    t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
    y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
  })(window, document, "clarity", "script", "TU_ID_DE_CLARITY");
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

    <?php require __DIR__ . '/Componentes/sidebar.php'; ?>

    <main class="client-portal" role="main" aria-label="Ajustes de cuenta">
        <!-- HERO -->
        <section class="hero container">
            <h1>EDITAR PERFIL</h1>
           <!-- <p class="subtitle">Gestiona tu perfil, correo y seguridad desde un solo lugar.</p>-->
        </section>

        <!-- CARDS -->
        <section class="portal-cards container" aria-label="Ajustes">
            <!-- PERFIL -->
            <article class="portal-card card--blue">
              <div class="icon avatar-wrap" aria-hidden="true">
  <button type="button" id="btnAvatar" class="avatar-btn" title="Cambiar avatar">
    <?php if (!empty($user['avatar'])): ?>
<!-- CORRECTO -->
<img src="<?= h(APP_URL.'/assets/avatars/'.$user['avatar']) ?>" alt="Avatar">
    <?php else:
      $ini = mb_strtoupper(mb_substr(($user['username'] ?: $user['email']), 0, 1, 'UTF-8'), 'UTF-8');
    ?>
      <span class="avatar-initial"><?= h($ini) ?></span>
    <?php endif; ?>
  </button>
</div>

                <h3>PERFIL</h3>
                <p>Actualiza tu información básica.</p>

                <form id="formPerfil" onsubmit="return false;">
                    <div class="form-grid form-2">
                        <div class="field">
                            <label for="username">Usuario</label>
                            <input id="username" value="<?= h($user['username']) ?>" minlength="3" required>
                        </div>
                        <div class="field">
                            <label for="telefono">Teléfono</label>
                            <input id="telefono" value="<?= h($user['Telefono']) ?>" maxlength="10" inputmode="numeric" placeholder="10 dígitos" required>
                        </div>
                        <div class="field">
                            <label for="rfc">RFC</label>
                            <input id="rfc" value="<?= h($user['RFC']) ?>" maxlength="13" placeholder="ABCD001122XXX" required>
                        </div>
                        <div class="actions">
                            <button class="btn-primary" id="btnSavePerfil" type="button">GUARDAR CAMBIOS</button>
                            <button class="btn-ghost" id="btnResetPerfil" type="button">DESCARTAR</button>
                        </div>
                    </div>
                </form>
            </article>

            <!-- SEGURIDAD / CONTRASEÑA -->
            <article class="portal-card card--navy">
                <div class="icon" aria-hidden="true">
                    <!--<svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="4" y="11" width="16" height="10" rx="2" />
                        <path d="M8 11V8a4 4 0 1 1 8 0v3" />
                    </svg>-->
                    <img src="<?= APP_URL ?>/assets/svg/CandadoIcon.svg" alt="icono">
                </div>
                <h3>SEGURIDAD</h3>
                <p class="muted">Último cambio: <?= $user['password_changed_at'] ? h($user['password_changed_at']) : '—' ?></p>

                <form id="formPass" onsubmit="return false;">
                    <div class="form-grid">
                        <div class="field">
                            <label for="passActual">Contraseña actual</label>
                            <input id="passActual" type="password" autocomplete="current-password" required>
                        </div>
                        <div class="form-2 form-grid">
                            <div class="field">
                                <label for="passNueva">Nueva</label>
                                <input id="passNueva" type="password" minlength="6" autocomplete="new-password" required>
                            </div>
                            <div class="field">
                                <label for="passConf">Confirmar</label>
                                <input id="passConf" type="password" minlength="6" autocomplete="new-password" required>
                            </div>
                        </div>
                        <div class="actions">
                            <button class="btn-primary btn-contrasena" id="btnSavePass" type="button">ACTUALIZAR CONTRASEÑA</button>
                        </div>
                    </div>
                </form>
            </article>

            <!-- CORREO / VERIFICACIÓN -->
            <article class="portal-card card--orange">
                <div class="icon" aria-hidden="true">
                    <!--<svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 6h16v12H4z" />
                        <path d="m4 7 8 6 8-6" />
                    </svg>-->
                    <img src="<?= APP_URL ?>/assets/svg/CorreoIcon.svg" alt="icono">
                </div>
                <h3>CORREO</h3>
                <p class="muted">Gestiona tu correo y verificación.</p>

                <div class="field">
                    <label>Correo actual</label>
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                        <strong><?= h($user['email']) ?></strong>
                        <?php if ($user['email_verified_at']): ?>
                            <span class="badge ok">Verificado</span>
                        <?php else: ?>
                            <span class="badge warn">Pendiente</span>
                            <button class="btn-ghost" id="btnReenviar" type="button">Reenviar verificación</button>
                        <?php endif; ?>
                    </div>
                </div>

                <form id="formEmail" class="form-3" onsubmit="return false;" style="margin-top:10px">
                    <div class="form-grid">
                        <div class="field">
                            <label for="emailNuevo">Nuevo correo</label>
                            <input id="emailNuevo" class="field-correo" type="email" placeholder="nuevo@correo.com">
                        </div>
                        <div class="actions">
                            <button class="btn-primary btn-correo" id="btnSaveEmail" type="button">CAMBIAR CORREO</button>
                        </div>
                    </div>
                </form>
            </article>
        </section>
    </main>

    <script>
      const Toast = Swal.mixin({ toast:true, position:'top-end', showConfirmButton:false, timer:2200, timerProgressBar:true });


        // Normalizadores
        $("#rfc").on("input", function() {
            this.value = this.value.toUpperCase().replace(/\s+/g, "");
        });
        $("#telefono").on("input", function() {
            this.value = this.value.replace(/\D+/g, "").slice(0, 10);
        });

        // PERFIL
        $("#btnSavePerfil").on("click", function() {
            const username = $("#username").val().trim();
const RFC      = $("#rfc").val().trim().toUpperCase();
const Telefono = $("#telefono").val().trim();

if (username.length < 3) return Swal.fire('Ups','Usuario muy corto','info');

// ✅ una sola barra invertida
if (!/^([A-ZÑ&]{3,4})\d{6}([A-Z0-9]{2,3})$/.test(RFC))
  return Swal.fire('Ups','RFC inválido','info');

// ✅ una sola barra invertida
if (!/^\d{10}$/.test(Telefono))
  return Swal.fire('Ups','Teléfono inválido','info');
            const $btn = $(this).prop("disabled", true).text("Guardando…");

            $.ajax({
                    url: APP_URL + '/cuenta/update_profile_api.php',
                    method: 'POST',
                    contentType: 'application/json; charset=utf-8',
                    dataType: 'json',
                    data: JSON.stringify({
                        username,
                        RFC,
                        Telefono
                    })
                })
                .done(res => {
                    res.ok ? Toast.fire({
                        icon: 'success',
                        title: 'Perfil actualizado'
                    }) : Swal.fire('No se pudo', res.msg || 'Intenta de nuevo', 'error');
                })
                .fail(jq => {
                    let msg = 'Servidor no disponible';
                    try {
                        const r = jq.responseJSON || JSON.parse(jq.responseText || '{}');
                        if (r.msg) msg = r.msg;
                    } catch (_) {}
                    Swal.fire('Error', msg, 'error');
                })
                .always(() => $btn.prop("disabled", false).text("Guardar cambios"));
        });

        $("#btnResetPerfil").on("click", () => window.location.reload());

        // CONTRASEÑA
        $("#btnSavePass").on("click", function() {
            const actual = $("#passActual").val();
            const nueva = $("#passNueva").val();
            const conf = $("#passConf").val();
            if (nueva.length < 6) return Swal.fire('Ups', 'Mínimo 6 caracteres', 'info');
            if (nueva !== conf) return Swal.fire('Ups', 'No coincide la confirmación', 'info');

            const $btn = $(this).prop("disabled", true).text("Actualizando…");

            $.ajax({
                    url: APP_URL + '/cuenta/change_password_api.php',
                    method: 'POST',
                    contentType: 'application/json; charset=utf-8',
                    dataType: 'json',
                    data: JSON.stringify({
                        current_password: actual,
                        new_password: nueva
                    })
                })
                .done(res => {
                    if (res.ok) {
                        Swal.fire('Listo', 'Tu contraseña se actualizó', 'success');
                        $("#passActual,#passNueva,#passConf").val('');
                    } else {
                        Swal.fire('No se pudo', res.msg || 'Intenta de nuevo', 'error');
                    }
                })
                .fail(jq => {
                    let msg = 'Servidor no disponible';
                    try {
                        const r = jq.responseJSON || JSON.parse(jq.responseText || '{}');
                        if (r.msg) msg = r.msg;
                    } catch (_) {}
                    Swal.fire('Error', msg, 'error');
                })
                .always(() => $btn.prop("disabled", false).text("Actualizar contraseña"));
        });

        // CORREO
        $("#btnSaveEmail").on("click", function() {
            const email = $("#emailNuevo").val().trim().toLowerCase();
            if (!email) return Swal.fire('Ups', 'Escribe el nuevo correo', 'info');

            const $btn = $(this).prop("disabled", true).text("Enviando…");
            $.ajax({
                    url: APP_URL + '/cuenta/change_email_api.php',
                    method: 'POST',
                    contentType: 'application/json; charset=utf-8',
                    dataType: 'json',
                    data: JSON.stringify({
                        email
                    })
                })
                .done(res => {
                    if (res.ok) {
                        Swal.fire('Revisa tu correo', res.msg || 'Te enviamos un enlace de verificación.', 'success').then(() => window.location.reload());
                    } else {
                        Swal.fire('No se pudo', res.msg || 'Intenta de nuevo', 'error');
                    }
                })
                .fail(jq => {
                    let msg = 'Servidor no disponible';
                    try {
                        const r = jq.responseJSON || JSON.parse(jq.responseText || '{}');
                        if (r.msg) msg = r.msg;
                    } catch (_) {}
                    Swal.fire('Error', msg, 'error');
                })
                .always(() => $btn.prop("disabled", false).text("Cambiar correo"));
        });

        // REENVIAR VERIFICACIÓN
        $("#btnReenviar").on("click", function() {
            const emailActual = <?= json_encode($user['email']) ?>;
            const $btn = $(this).prop("disabled", true).text("Reenviando…");
            $.ajax({
                    url: APP_URL + '/auth/reenviar_verificacion_api.php',
                    method: 'POST',
                    contentType: 'application/json; charset=utf-8',
                    dataType: 'json',
                    data: JSON.stringify({
                        email: emailActual
                    })
                })
                .done(res => {
                    Swal.fire(res.ok ? 'Listo' : 'Ups', res.msg || '', res.ok ? 'success' : 'error');
                })
                .fail(jq => {
                    let msg = 'Servidor no disponible';
                    try {
                        const r = jq.responseJSON || JSON.parse(jq.responseText || '{}');
                        if (r.msg) msg = r.msg;
                    } catch (_) {}
                    Swal.fire('Error', msg, 'error');
                })
                .always(() => $btn.prop("disabled", false).text("Reenviar verificación"));
        });

        
    </script>

    <script>

// Lista exacta de archivos
const AVATAR_FILES = [
  'iconAvatar1.png',
  'iconAvatar2.png',
  'iconAvatar3.png',
  'iconAvatar4.png',
  'iconAvatar5.png',
];

$('#btnAvatar').on('click', function(){
  const current = <?= json_encode($user['avatar'] ?? '') ?>;

  const grid = `<div class="ava-grid">` + AVATAR_FILES.map(fn => `
    <button type="button" class="ava ${fn===current?'selected':''}" data-file="${fn}">
      <img src="${APP_URL}/assets/avatars/${fn}" alt="${fn}">
    </button>
  `).join('') + `</div>`;

  Swal.fire({
    title: 'Elige tu avatar',
    html: grid,
    width: 560,
    focusConfirm: false,
    showCancelButton: true,
    confirmButtonText: 'Guardar',
    cancelButtonText: 'Cancelar',
    didOpen: () => {
      const box = Swal.getHtmlContainer().querySelector('.ava-grid');
      box.addEventListener('click', (e) => {
        const btn = e.target.closest('.ava');
        if (!btn) return;
        box.querySelectorAll('.ava.selected').forEach(b=>b.classList.remove('selected'));
        btn.classList.add('selected');
      });
    },
    preConfirm: () => {
      const pick = Swal.getHtmlContainer().querySelector('.ava.selected');
      if (!pick) { Swal.showValidationMessage('Selecciona un avatar'); return false; }
      return pick.dataset.file; // devolvemos el archivo elegido
    }
  }).then(r => {
    if (!r.isConfirmed) return;

    $.ajax({
      url: APP_URL + '/cuenta/change_avatar_api.php',
      method: 'POST',
      contentType: 'application/json; charset=utf-8',
      dataType: 'json',
      data: JSON.stringify({ avatar_file: r.value })
    })
    .done(res => {
      if (res.ok){
        // Actualiza el botón del avatar
        const img = $('#btnAvatar img');
        if (img.length) img.attr('src', res.url + '?v=' + Date.now());
        else $('#btnAvatar').html(`<img src="${res.url}" alt="Avatar">`);
        Toast.fire({icon:'success', title:'Avatar actualizado'});
      } else {
        Swal.fire('No se pudo', res.msg || '', 'error');
      }
    })
    .fail(() => Swal.fire('Error','No se pudo actualizar el avatar.','error'));
  });
});
</script>

</body>

</html>

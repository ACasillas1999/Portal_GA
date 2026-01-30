<!DOCTYPE html>
<html lang="es">
<head>
    
  <script type="text/javascript">
  (function(c,l,a,r,i,t,y){
    c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
    t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
    y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
  })(window, document, "clarity", "script", "tm0znm12k6"); // tu ID real de Clarity
</script>

<?php if (isset($_SESSION['user_id'])): ?>
<script>
  clarity("identify", "<?php echo $_SESSION['user_id']; ?>");
</script>
<?php endif; ?>
  
  
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="icon" href="..\assets\img\iconpestalla.png" type="image/x-icon">
  <title>Login / Signup</title>
  <link rel="stylesheet" href="style.css?v=1.8" />

  <style>
    html, body { height: 100%; margin: 0; }
    #back { position: fixed; inset: 0; overflow: hidden; }
    .canvas-back { width: 100%; height: 100%; display: block; }
    .btn-success-burst { animation: burst 600ms ease-out forwards; }
    @keyframes burst {
      0% { transform: scale(1); background:#4caf50; color:#fff; }
      50% { transform: scale(1.06); box-shadow:0 0 0 8px rgba(76,175,80,.25); }
      100% { transform: scale(1); box-shadow:0 0 0 0 rgba(76,175,80,0); }
    }
    .shake { animation: shake .45s ease-in-out; }
    @keyframes shake {
      10%,90% { transform: translateX(-2px); }
      20%,80% { transform: translateX(4px); }
      30%,50%,70% { transform: translateX(-6px); }
      40%,60% { transform: translateX(6px); }
    }
    .modal { display:none; position:fixed; z-index:9999; inset:0; background:rgba(0,0,0,.6); overflow-y:auto; }
    .modal-content { background:#fff; color:#000; max-width:700px; margin:60px auto; padding:20px 30px; border-radius:8px; position:relative; }
    .modal .close { position:absolute; top:10px; right:15px; font-size:1.5rem; font-weight:bold; color:#333; cursor:pointer; }
  </style>
</head>
<body>

  <div id="back">
    <!-- <canvas id="canvas" class="canvas-back" resize></canvas> -->
    <div class="backRight">
      <div class="img-title">
        <img src="assets/img/title.img.loginAcceso.png" alt="Descripción de la imagen" class="imgTitle">
      </div>
    </div>
    <div class="backLeft"></div>
  </div>

  <div id="slideBox">
    <div class="topLayer">
      <div class="left">
        <div class="content">
          <img src="assets/img/LogoColor.GrupoAscencio.svg" alt="Icono" class="grupo-logo">
          <h2>Solicitar acceso</h2>
          <p class="helper-text" style="margin:10px 0 18px; line-height:1.5;">
            Para solicitar acceso, comunícate con tu <b>asesor</b>.
          </p>
          <div class="form-element form-submit">
            <button id="goLeft" class="signup off" type="button">Iniciar Sesión</button>
          </div>
        </div>
      </div>

      <div class="right">
        <div class="content">
          <img src="assets/img/LogoColor.GrupoAscencio.svg" alt="Icono" class="grupo-logo">
          <h2>Inicio de Sesión</h2>

          <form id="form-login" method="post" onsubmit="return false;" autocomplete="on">
            <div class="form-element form-stack">
              <label for="email-login" class="form-label">Correo electrónico</label>
              <input id="email-login" type="email" name="email" required autocomplete="email">
            </div>
            <div class="form-element form-stack">
              <label for="password-login" class="form-label">Contraseña</label>
              <input id="password-login" type="password" name="password" required autocomplete="current-password">
            </div>
            <div class="form-element form-submit">
              <button id="logIn" class="login" type="submit" name="login">Iniciar Sesión</button>
              <button id="goRight" class="login off" type="button">Solicitar acceso</button>
            </div>
<br></br>

            <!-- Turnstile visible -->
            <div class="cf-turnstile"
                 data-sitekey="0x4AAAAAAB4wQT_c4sm61ULt"
                 data-theme="light"
                 data-size="normal"
                 data-action="login"></div>
                 
                 
          </form>

          <a href="#" id="forgot-link" class="forgot" style="font-size:1rem">¿Olvidaste tu contraseña?</a>
        </div>
      </div>
    </div>
  </div>

  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <!-- Librerías -->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://unpkg.com/paper@0.12.17/dist/paper-full.min.js"></script>

  <script>
    const Toast = Swal.mixin({
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 2400,
      timerProgressBar: true
    });
  </script>

  <!-- App scripts -->
  <script>
    const PANEL_URL = "/Inicio/";
    $(function() {
      $("#goRight").on("click", function() {
        $("#slideBox").animate({ marginLeft: "0" });
        $(".topLayer").animate({ marginLeft: "100%" });
      });
      $("#goLeft").on("click", function() {
        $("#slideBox").animate({ marginLeft: (window.innerWidth > 769) ? "50%" : "20%" });
        $(".topLayer").animate({ marginLeft: "0" });
      });
    });

    // Paper.js opcional (solo si hay canvas)
    const canvasEl = document.getElementById("canvas");
    if (canvasEl) {
      paper.setup(canvasEl);
    } else {
      console.warn("Canvas no presente, Paper.js está desactivado");
    }
    var canvasWidth, canvasHeight, canvasMiddleX, canvasMiddleY;
    var shapeGroup = new paper.Group();
    var positionArray = [];
    function getCanvasBounds() {
      canvasWidth = paper.view.size.width;
      canvasHeight = paper.view.size.height;
      canvasMiddleX = canvasWidth / 2;
      canvasMiddleY = canvasHeight / 2;
      var position1 = { x: canvasMiddleX / 2 + 100, y: 100 };
      var position2 = { x: 200, y: canvasMiddleY };
      var position3 = { x: canvasMiddleX - 50 + canvasMiddleX / 2, y: 150 };
      var position4 = { x: 0, y: canvasMiddleY + 100 };
      var position5 = { x: canvasWidth - 130, y: canvasHeight - 75 };
      var position6 = { x: canvasMiddleX + 80, y: canvasHeight - 50 };
      var position7 = { x: canvasWidth + 60, y: canvasMiddleY - 50 };
      var position8 = { x: canvasMiddleX + 100, y: canvasMiddleY + 100 };
      positionArray = [position3, position2, position5, position4, position1, position6, position7, position8];
    }
    function initializeShapes() {
      getCanvasBounds();
      var shapePathData = [
        "M231,352l445-156L600,0L452,54L331,3L0,48L231,352",
        "M0,0l64,219L29,343l535,30L478,37l-133,4L0,0z",
        "M0,65l16,138l96,107l270-2L470,0L337,4L0,65z",
        "M333,0L0,94l64,219L29,437l570-151l-196-42L333,0",
        "M331.9,3.6l-331,45l231,304l445-156l-76-196l-148,54L331.9,3.6z",
        "M389,352l92-113l195-43l0,0l0,0L445,48l-80,1L122.7,0L0,275.2L162,297L389,352",
        "M 50 100 L 300 150 L 550 50 L 750 300 L 500 250 L 300 450 L 50 100",
        "M 700 350 L 500 350 L 700 500 L 400 400 L 200 450 L 250 350 L 100 300 L 150 50 L 350 100 L 250 150 L 450 150 L 400 50 L 550 150 L 350 250 L 650 150 L 650 50 L 700 150 L 600 250 L 750 250 L 650 300 L 700 350 "
      ];
      for (var i = 0; i < shapePathData.length; i++) {
        var headerShape = new paper.Path({
          strokeColor: new paper.Color(1, 1, 1, 0.5),
          strokeWidth: 2,
          parent: shapeGroup
        });
        headerShape.pathData = shapePathData[i];
        headerShape.scale(2);
        headerShape.position = positionArray[i];
      }
    }
    initializeShapes();
    paper.view.onFrame = function(event) {
      if (event.count % 4 === 0) {
        for (var i = 0; i < shapeGroup.children.length; i++) {
          (i % 2 === 0) ? shapeGroup.children[i].rotate(-0.1) : shapeGroup.children[i].rotate(0.1);
        }
      }
    };
    paper.view.onResize = function() {
      getCanvasBounds();
      for (var i = 0; i < shapeGroup.children.length; i++) {
        shapeGroup.children[i].position = positionArray[i];
      }
      if (paper.view.size.width < 700) {
        if (shapeGroup.children[3]) shapeGroup.children[3].opacity = 0;
        if (shapeGroup.children[2]) shapeGroup.children[2].opacity = 0;
        if (shapeGroup.children[5]) shapeGroup.children[5].opacity = 0;
      } else {
        if (shapeGroup.children[3]) shapeGroup.children[3].opacity = 1;
        if (shapeGroup.children[2]) shapeGroup.children[2].opacity = 1;
        if (shapeGroup.children[5]) shapeGroup.children[5].opacity = 1;
      }
    };
  </script>

 <!-- ====== LOGIN: handler simple leyendo el token visible ====== -->
<script>
  $("#form-login").on("submit", function (e) {
    e.preventDefault();

    const $btn = $("#logIn");
    if ($btn.prop("disabled")) return;
    $btn.prop("disabled", true).text("Validando…");

    // Token que el widget visible agrega como input oculto
    const token = document.querySelector('input[name="cf-turnstile-response"]')?.value || '';

    if (!token) {
      Swal.fire({icon:'error', title:'Falta verificación', text:'Por favor completa el captcha.'});
      $btn.prop("disabled", false).text("Iniciar Sesión");
      return;
    }

    const payload = {
      email: $("#email-login").val().trim().toLowerCase(),
      password: $("#password-login").val(),
      cf_turnstile_token: token
    };

    $.ajax({
      url: "login_usuario.php",
      method: "POST",
      contentType: "application/json; charset=utf-8",
      dataType: "json",
      data: JSON.stringify(payload)
    })
    .done(res => {
      if (res?.ok) {
        Swal.fire({
          title:`¡Bienvenido, ${res.user?.username||'usuario'}!`,
          text:"Inicio de sesión exitoso",
          icon:"success",
          timer:2800,
          showConfirmButton:false,
          timerProgressBar:true
        }).then(()=>{ window.location.href="./Inicio/"; });

        setTimeout(()=>{ try{ window.location.assign(PANEL_URL); }catch{} },3200);

      } else if (res?.needVerify) {
        Swal.fire({
          title: 'Verifica tu correo',
          text: res?.msg || 'Tu cuenta aún no está verificada.',
          icon: 'info',
          showCancelButton: true,
          confirmButtonText: 'Enviar verificacion',
          cancelButtonText: 'Cerrar'
        }).then((r) => {
          if (r.isConfirmed) {
            $.ajax({
              url: 'auth/reenviar_verificacion_api.php',
              method: 'POST',
              contentType: 'application/json; charset=utf-8',
              dataType: 'json',
              data: JSON.stringify({ email: $("#email-login").val().trim().toLowerCase() })
            })
            .done(rr => {
              Swal.fire({
                icon: rr.ok ? 'success' : 'error',
                title: rr.ok ? 'Listo' : 'Ups',
                text: rr.msg || ''
              });
            })
            .fail((jqXHR) => {
              let msg = 'Enviado';
              try {
                const r = jqXHR.responseJSON || JSON.parse(jqXHR.responseText || '{}');
                if (r && r.msg) msg = r.msg;
              } catch (_) {}
              Swal.fire({ icon: 'success', title: 'Revisa la bandeja de entrada', text: msg });
            });
          }
        });

      } else {
        const msg = res?.msg || "No se pudo iniciar sesión.";
        Swal.fire({icon:"error", title:"Ups", text:msg});

        // Si el backend indicó problema con el captcha, reinicia de inmediato
        if (/captcha/i.test(msg)) {
          try { turnstile.reset(); } catch (_) {}
        }

        const form = $("#form-login")[0];
        form.classList.add("shake");
        setTimeout(()=>form.classList.remove("shake"),500);
      }
    })
    .fail(jqXHR => {
      const r = jqXHR.responseJSON || {};
      Swal.fire({icon:"error", title:"Error del servidor", text:r.msg || `Error de red (HTTP ${jqXHR.status})`});
      const form = $("#form-login")[0];
      form.classList.add("shake");
      setTimeout(()=>form.classList.remove("shake"),500);
    })
    .always(() => {
      $("#logIn").prop("disabled", false).text("Iniciar Sesión");
      // Re-render del widget para forzar un nuevo token en el siguiente intento
      try { turnstile.reset(); } catch (_) {}
    });
  }); // <-- ESTE cerraba el handler del submit (te faltaba)
</script>


  <script>
    function openModal(id){ document.getElementById(id).style.display = 'block'; }
    function closeModal(id){ document.getElementById(id).style.display = 'none'; }
    window.addEventListener('click', function(e){
      if (e.target.classList.contains('modal')) { e.target.style.display = 'none'; }
    });
  </script>

  <!-- Modales (si los usas) -->
  <div id="modal-terminos" class="modal">
    <div class="modal-content">
      <span class="close" onclick="closeModal('modal-terminos')">&times;</span>
      <h2>Términos y Condiciones</h2>
      <p>Contenido…</p>
    </div>
  </div>

  <div id="modal-privacidad" class="modal">
    <div class="modal-content">
      <span class="close" onclick="closeModal('modal-privacidad')">&times;</span>
      <h2>Aviso de Privacidad</h2>
      <p>Contenido…</p>
    </div>
  </div>

  <!-- Solo la librería del widget visible -->
  <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>

</body>
</html>

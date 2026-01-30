<?php


// ===== Sesión consistente =====
ini_set('session.cookie_httponly', '1');
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') { ini_set('session.cookie_secure','0'); } else { ini_set('session.cookie_secure','1'); }
session_name('GA');

session_start();

date_default_timezone_set('America/Mexico_City');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/db.php'; // $pdo
require_once __DIR__ . '/../app/session_boot.php';
require_login(); 
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
// ===== Labels/Badges visibles para el CLIENTE (Garantías) =====
function status_label_cliente_g(int $code): string {
  if ($code === 0) return 'Enviado';
  if (in_array($code, [1,2,3,4,5], true)) return 'En proceso';
  if ($code === 51) return 'Nota de crédito';
  if ($code === 52) return 'Reemplazo';
  if (in_array($code, [10,20,30,40,50], true)) return 'Cancelado';
  return '—';
}

function estatusBadgeCliente_g(int $code): string {
  $label = status_label_cliente_g($code);
  if ($code === 0)                        return '<span class="status enviado"><span class="dot"></span>'.h($label).'</span>';
  if (in_array($code, [1,2,3,4,5], true)) return '<span class="status proceso"><span class="dot"></span>'.h($label).'</span>';
  if ($code === 51 || $code === 52)       return '<span class="status nota"><span class="dot"></span>'.h($label).'</span>';
  if (in_array($code, [10,20,30,40,50], true)) return '<span class="status cancelado"><span class="dot"></span>'.h($label).'</span>';
  return '<span class="status"><span class="dot"></span>'.h($label).'</span>';
}

$uid = $_SESSION['user_id']
    ?? $_SESSION['ID']
    ?? $_SESSION['idUsuario']
    ?? $_SESSION['usuario_id']
    ?? null;

$rol       = $_SESSION['Rol'] ?? '';
$emailSess = $_SESSION['Email'] ?? ($_SESSION['email'] ?? $_SESSION['Correo'] ?? null);

$nombrePref = $_SESSION['username'] ?? '';
$emailPref  = $emailSess ?: '';
$telPref    = preg_replace('/\D+/', '', $_SESSION['Telefono'] ?? '');

// ===== Listado =====
if ($rol === 'Admin') {
  $st = $pdo->query("
    SELECT id, folio, producto, estado, created_at
    FROM garantia_solicitudes
    ORDER BY created_at DESC
    LIMIT 300
  ");
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
} else {
  $rows   = [];
  $conds  = [];
  $params = [];
  if ($uid)       { $conds[] = 'id_usuario = ?'; $params[] = $uid; }
  if ($emailSess) { $conds[] = 'email = ?';      $params[] = $emailSess; }

  if ($conds) {
    $sql = "
      SELECT id, folio, producto, estado, created_at
      FROM garantia_solicitudes
      WHERE (".implode(' OR ', $conds).")
      ORDER BY created_at DESC
      LIMIT 300
    ";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" href="..\assets\img\iconpestalla.png" type="image/x-icon">
  <title>Garantías</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link rel="stylesheet" href="./Estilos/style.css?v=1.0">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

  <style>
    .form-table{width:100%;border-collapse:collapse;border:1px solid #e5e7eb;background:#fff;border-radius:12px;overflow:hidden}
    .form-table th{width:280px;text-align:left;background:#f8fafc;color:#111827;padding:10px 12px;border-bottom:1px solid #eef2f7}
    .form-table td{padding:8px 12px;border-bottom:1px solid #f1f5f9}
    .form-table input,.form-table textarea,.form-table select{
      width:100%;padding:9px 10px;border:1px solid #e5e7eb;border-radius:10px;outline:none;font:500 14px ui-sans-serif,system-ui;box-sizing:border-box
    }
    .form-table textarea{min-height:100px;resize:vertical}
    .legend{margin:12px 0 0;color:#0b607a;background:#e6fffb;padding:10px 12px;border-left:4px solid #0ea5b7;border-radius:8px}
    .note{margin:10px 0;color:#334155}
    .modal__body{max-height:72vh;overflow:auto}
    
    /* Badges de estatus (cliente) */
.status{display:inline-flex;align-items:center;gap:6px;font-weight:600}
.status .dot{width:8px;height:8px;border-radius:50%;display:inline-block}

.status.enviado  { background:#ecf3ff; color:#0b5fa3; border:1px solid #cfe1ff; padding:4px 8px; border-radius:999px }
.status.enviado .dot{ background:#0d6efd }

.status.proceso  { background:#fff7ed; color:#b45309; border:1px solid #fde68a; padding:4px 8px; border-radius:999px }
.status.proceso .dot{ background:#f59e0b }

.status.nota     { background:#ecfdf5; color:#047857; border:1px solid #a7f3d0; padding:4px 8px; border-radius:999px }
.status.nota .dot{ background:#20c997 }

.status.cancelado{ background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; padding:4px 8px; border-radius:999px }
.status.cancelado .dot{ background:#dc3545 }
:root {

  --font-sans: "Montserrat", system-ui, -apple-system, "Segoe UI", Roboto,
    "Helvetica Neue", Arial, "Noto Sans", sans-serif;
}
* {
  font-family: var(--font-sans);
  box-sizing: border-box;
}
  .swal2-popup.swal2-rounded{ border-radius:18px!important; }

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

<?php require __DIR__ . '/../Componentes/sidebar.php'; ?>

<main id="app" class="container">
  <!-- HERO -->
  <section class="hero" aria-labelledby="garantias-title">
    <div class="hero-inner">
      <div class="title-row">
        <div class="logo-box" aria-hidden="true">
          <!--<svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#0b5fa3" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 7l9 4 9-4M3 7l9-4 9 4M3 7v10l9 4 9-4V7" />
            <path d="M12 11v10" />
          </svg>-->
          <img class="iconSize" src="<?= APP_URL ?>/assets/svg/GarantiaIconAzulFuerte.svg" alt="icono">
        </div>
        <h1 id="garantias-title">GARANTÍAS</h1>
        <h2 class="bntregresar">
            <a class="btn-sm" href="<?= defined('APP_URL') ? h(APP_URL) . '/Inicio' : '/portal' ?>">VOLVER AL PORTAL</a>
        </h2>
      </div>

      
<section class="tc-devolucion" aria-labelledby="titulo-devolucion">
  <h2 id="titulo-devolucion">Términos y Condiciones</h2>
  <ul>
    <li>La garantía aplica <strong>únicamente </strong> a productos adquiridos en nuestra empresa.</li>
    <li>El <strong>plazo de garantía</strong> es el especificado por cada fabricante.</li>
    <li>Es indispensable presentar <strong>factura o comprobante de compra</strong>.</li>
    <li><strong>No cubre daños</strong> por mal uso, instalación incorrecta o accidentes.</li>
    <li>La revisión técnica será realizada por personal autorizado, esto de acuerdo de lo dictado por el fabricante..</li>
    <li>Si el producto es reparable, se devolverá en condiciones óptimas de uso.</li>
    <li><strong>En caso de reemplazo</strong>, se entregará un producto igual o equivalente.</li>
    <li>La empresa se reserva el derecho de <strong>aceptar o rechazar la garantía tras evaluación</strong>.</li>
  </ul>
</section>


      <a class="btn" id="btnNueva" href="#" role="button">INICIAR GARANTÍA</a>
    </div>

    <!-- Marca de agua -->
    <!--<svg class="watermark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M3 7l9 4 9-4M3 7l9-4 9 4M3 7v10l9 4 9-4V7" />
      <path d="M12 11v10" />
    </svg>-->
  </section>

  <!-- HISTORIAL -->
  <section class="panel" aria-labelledby="historial-title">
    <h2 id="historial-title">Historial</h2>
    <div class="table-wrap">
      <table role="table" aria-describedby="historial-title" id="tabla">
        <thead>
          <tr>
            <th>Folio</th>
            <th>Producto</th>
            <th>Fecha de registro</th>
            <th>Estatus</th>
            <th class="actions">Detalles</th>
          </tr>
        </thead>
        <tbody id="tbody">
          <?php if (!$rows): ?>
            <tr><td colspan="5" class="empty">Aún no tienes garantías. Da clic en <b>INICIAR GARANTÍA</b>.</td></tr>
          <?php else: foreach ($rows as $r):
           $fecha = date('d/m/y g:iA', strtotime($r['created_at']));
$code  = (int)$r['estado'];               // la BD trae el código numérico
$estadoHtml = estatusBadgeCliente_g($code);

          ?>
            <tr>
              <td class="folio"><?= h($r['folio']) ?></td>
              <td><?= h($r['producto']) ?></td>
              <td class="date"><?= h($fecha) ?></td>
              <td><?= $estadoHtml ?></td>
              <td class="actions">
                <a class="btn-sm" href="ver.php?id=<?= (int)$r['id'] ?>">VER DETALLES</a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </section>

</main>

<!-- ===== Modal Nueva Garantía ===== -->
<div class="modal" id="modalNuevo" hidden>
  <div class="modal__backdrop" data-close></div>
  <div class="modal__dialog" role="dialog" aria-modal="true" aria-labelledby="md-title">
    <header class="modal__hd">
      <h3 id="md-title">Nueva garantía</h3>
      <button class="modal__close" type="button" title="Cerrar" aria-label="Cerrar" data-close>×</button>
    </header>

    <form id="frmGarantia" class="modal__body" enctype="multipart/form-data">
  <div class="grid">
    <label class="fld">
      <span>Correo</span>
      <input type="email" name="email" required
             value="<?= htmlspecialchars($_SESSION['Email'] ?? $_SESSION['Correo'] ?? '') ?>"
             readonly>
    </label>

    <label class="fld">
      <span>Teléfono</span>
      <input type="tel" name="telefono" required pattern="\d{10}"
             value="<?= htmlspecialchars(preg_replace('/\D+/', '', $_SESSION['Telefono'] ?? '')) ?>"
             placeholder="10 dígitos" <?= empty($_SESSION['Telefono'])?'':'readonly' ?>>
    </label>

    <label class="fld">
      <span>Nombre del producto</span>
      <input type="text" name="producto" required maxlength="200">
    </label>

    <label class="fld">
      <span>No. de serie (si aplica)</span>
      <input type="text" name="no_serie" maxlength="120">
    </label>

    <label class="fld">
      <span>Código en factura</span>
      <input type="text" name="codigo_factura" maxlength="120">
    </label>

    <label class="fld">
      <span>No. de factura</span>
      <input type="text" name="no_factura" maxlength="120">
    </label>

    <label class="fld">
      <span>Fecha de factura</span>
      <input type="date" name="fecha_factura">
    </label>

    <label class="fld">
      <span>Marca</span>
      <input type="text" name="marca" maxlength="120">
    </label>

   <label class="fld">
  <span>Sucursal</span>
  <select class="sucursal-style" name="sucursal" id="selSucursalG" required>
    <option value="" disabled selected>Selecciona sucursal…</option>
    <option value="deasa">DEASA</option>
    <option value="tapatia">EITSA (Tapatía)</option>
    <option value="dimegsa">DIMEGSA</option>
    <option value="iluminacion">ELEITSA (Iluminación)</option>
    <option value="segsa">SEGSA</option>
    <option value="fesa">FESA</option>
    <option value="codi">CODI</option>
    <option value="vallarta">Vallarta</option>
    <option value="queretaro">Querétaro</option>
    <option value="gabsa">GABSA</option>
    <option value="aiesa">AIESA</option>
  </select>
</label>

<!-- NUEVO: Vendedor que lo atendió (dependiente de sucursal) -->
<label class="fld" id="wrapVendedorG" style="display:none">
  <span>Vendedor que lo atendió</span>
  <select class="sucursal-style" name="vendedor" id="selVendedorG"></select>
  <small style="color:#6b7280">Se llena según la sucursal seleccionada.</small>
</label>

    <label class="fld fld--full">
      <span>Descripción de la falla</span>
      <textarea name="descripcion_falla" rows="4" required></textarea>
    </label>

    <label class="fld fld--full">
      <span>Fotos / Video (múltiples)</span>
<input type="file" id="adjG" name="adjuntos[]" accept="image/*,video/*,application/pdf,application/zip" multiple>
    </label>

    <label class="fld fld--full">
      <span>Nombre de contacto</span>
      <input type="text" name="nombre_contacto" required maxlength="200"
             value="<?= htmlspecialchars($_SESSION['username'] ?? '') ?>">
    </label>
  </div>

  <footer class="modal__ft">
    <button type="button" class="btn-sec" data-close>Cancelar</button>
    <button type="submit" class="btn btnEnviar" id="btnEnviarG">Enviar solicitud</button>
  </footer>
</form>



  </div>
</div>

<!-- Modal OK -->
<div class="modal" id="modalOK" hidden>
  <div class="modal__backdrop" data-close></div>
  <div class="modal__dialog" role="dialog" aria-modal="true">
    <header class="modal__hd">
      <h3>Garantía registrada</h3>
      <button class="modal__close" type="button" title="Cerrar" aria-label="Cerrar" data-close>×</button>
    </header>
    <div class="modal__body">
      <p id="okMsg">¡Gracias! Hemos registrado tu garantía.</p>
    </div>
    <footer class="modal__ft">
      <button class="btn" data-close>Cerrar</button>
    </footer>
  </div>
</div>

<script>
  // ----- abrir/cerrar modal -----
  const mNuevo = document.getElementById('modalNuevo');
  document.getElementById('btnNueva').addEventListener('click', e=>{
    e.preventDefault(); mNuevo.hidden=false; document.body.style.overflow='hidden';
  });
  mNuevo.querySelectorAll('[data-close]').forEach(el=>el.addEventListener('click', ()=>{
    mNuevo.hidden=true; document.body.style.overflow='';
  }));
  window.addEventListener('keydown', e=>{
    if(e.key==='Escape'){ mNuevo.hidden=true; document.body.style.overflow=''; }
  });

  // ----- SweetAlert bonito -----
  function alertaGarantiaOK(folio, id='') {
    Swal.fire({
      title: '¡Garantía registrada!',
      html: `<div style="font-size:15px;line-height:1.5">
               <b>Folio:</b> <code id="folioTxt">${folio}</code>
             </div>`,
      icon: 'success',
      showCancelButton: true,
      confirmButtonText: (id ? 'Ver detalles' : 'Aceptar'),
      cancelButtonText: 'Cerrar',
      showDenyButton: true,
      denyButtonText: 'Copiar folio',
      customClass: { popup: 'swal2-rounded' }
    }).then(res => {
      if (res.isConfirmed && id) {
        window.location.href = `ver.php?id=${encodeURIComponent(id)}`;
      } else if (res.isDenied) {
        const f = document.getElementById('folioTxt').textContent.trim();
        navigator.clipboard.writeText(f).then(()=>{
          Swal.fire({ icon:'success', title:'Folio copiado', timer:1400, showConfirmButton:false });
        });
      } else {
        location.reload(); // refresca historial
      }
    });
  }
// ====== Validación de tamaño para adjuntos (GARANTÍAS) ======
const MAX_MB_G = 40;                  // límite por archivo (ajusta si quieres)
const MAX_B_G  = MAX_MB_G * 1024 * 1024;
const inpAdjG  = document.getElementById('adjG');

if (inpAdjG) {
  inpAdjG.addEventListener('change', () => {
    const grandes = [];
    for (const f of inpAdjG.files) {
      if (f.size > MAX_B_G) {
        grandes.push(`${f.name} (${(f.size/1024/1024).toFixed(1)} MB)`);
      }
    }
    if (grandes.length) {
      Swal.fire({
        icon: 'warning',
        title: 'Archivo demasiado pesado',
        html: `
          <p>Estos archivos exceden ${MAX_MB_G} MB:</p>
          <ul style="text-align:left;margin-top:8px;">
            ${grandes.map(x => `<li>${x}</li>`).join('')}
          </ul>
          <p style="margin-top:10px;">Selecciona archivos más pequeños, por favor.</p>
        `,
        confirmButtonText: 'Entendido'
      });
      // Limpia la selección para que el usuario elija otros
      inpAdjG.value = '';
    }
  });
}

  // ----- Envío AJAX del form -----
  const frmG = document.getElementById('frmGarantia');
  const btnG = document.getElementById('btnEnviarG');

  frmG.addEventListener('submit', async (e) => {
    e.preventDefault();
    btnG.disabled = true; btnG.textContent = 'Enviando…';
    try{
      const fd = new FormData(frmG);
      const r  = await fetch('guardar_garantia.php', { method:'POST', body: fd });
      const j  = await r.json().catch(()=>({ok:false,msg:'Respuesta inválida del servidor'}));

      if (j.ok){
        mNuevo.hidden = true; document.body.style.overflow='';
        alertaGarantiaOK(j.folio, j.id || ''); // si devuelves id, habilita "Ver detalles"
        frmG.reset();
      } else {
        Swal.fire({ icon:'error', title:'No se pudo guardar', text: j.msg || 'Intenta de nuevo' });
      }
    } catch(err){
      Swal.fire({ icon:'error', title:'Error', text: err.message });
    } finally {
      btnG.disabled = false; btnG.textContent = 'Enviar solicitud';
    }
  });
</script>

<script>
(function(){
  const selSucursal  = document.getElementById('selSucursalG');
  const wrapVendedor = document.getElementById('wrapVendedorG');
  const selVendedor  = document.getElementById('selVendedorG');

  function fillSelect(select, items, placeholder='Selecciona…') {
    select.innerHTML = '';
    const opt0 = document.createElement('option');
    opt0.value = ''; opt0.textContent = placeholder; opt0.disabled = true; opt0.selected = true;
    select.appendChild(opt0);
    for (const it of (items || [])) {
      const op = document.createElement('option');
      op.value = it.id;
      op.textContent = it.label;
      select.appendChild(op);
    }
  }

  async function loadVendedoresBySucursal(suc) {
    // Mostrar/ocultar y marcar requerido según haya lista
    wrapVendedor.style.display = '';
    selVendedor.required = false;
    fillSelect(selVendedor, [], 'Cargando…');

    if (!suc) {
      fillSelect(selVendedor, [], 'Selecciona una sucursal primero');
      return;
    }
    try {
      // Usa ruta absoluta basada en la ubicación actual para evitar problemas con directorios duplicados
      const basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
      const r = await fetch(`${basePath}/api_vendedores.php?sucursal=${encodeURIComponent(suc)}`, { credentials:'same-origin' });
      const j = await r.json();
      if (j.ok && Array.isArray(j.data) && j.data.length) {
        fillSelect(selVendedor, j.data, 'Selecciona un vendedor…');
        selVendedor.required = true;               // obligatorio si hay
        wrapVendedor.style.display = '';
      } else {
        wrapVendedor.style.display = 'none';       // oculto si no hay
        selVendedor.required = false;
        fillSelect(selVendedor, [], 'Sin vendedores');
      }
    } catch (e) {
      console.error('API vendedores', e);
      wrapVendedor.style.display = 'none';
      selVendedor.required = false;
      fillSelect(selVendedor, [], 'Error al cargar');
    }
  }

  selSucursal?.addEventListener('change', e => loadVendedoresBySucursal(e.target.value));
  if (selSucursal && selSucursal.value) loadVendedoresBySucursal(selSucursal.value);
})();
</script>

</body>
</html>

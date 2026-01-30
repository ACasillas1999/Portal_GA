<?php
// ——— Devoluciones (PDO) ———
date_default_timezone_set('America/Mexico_City');

session_name('GA');
session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/session_boot.php';
require_login();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// Identidad y rol
$uid = $_SESSION['user_id'] ?? $_SESSION['ID'] ?? $_SESSION['idUsuario'] ?? null;
$rol = $_SESSION['Rol'] ?? '';
$esAdmin = ($rol === 'Admin');
$isLogged = !empty($uid);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Texto reducido para el CLIENTE
function status_label_cliente(int $code): string {
  if ($code === 0) return 'Enviado';
  if (in_array($code, [1,3], true)) return 'En proceso';
  if ($code === 2) return 'Pase a tienda';
  if ($code === 4) return 'Aprobado';
  if (in_array($code, [10,20,30], true)) return 'Cancelado';
  return '—';
}

// Badge visual para el CLIENTE
function estatusBadgeCliente(int $code): string {
  $label = status_label_cliente($code);
  if ($code === 0)                    return '<span class="status enviado"><span class="dot"></span>'.h($label).'</span>';
  if (in_array($code, [1,2,3], true)) return '<span class="status proceso"><span class="dot"></span>'.h($label).'</span>';
  if ($code === 4)                    return '<span class="status nota"><span class="dot"></span>'.h($label).'</span>';
  if (in_array($code, [10,20,30], true)) return '<span class="status cancelado"><span class="dot"></span>'.h($label).'</span>';
  return '<span class="status"><span class="dot"></span>'.h($label).'</span>';
}

// Búsqueda opcional ?q=
$term = isset($_GET['q']) ? trim($_GET['q']) : '';
$rows = [];
if ($term === '') {
  $sql = "
    SELECT d.id, d.folio, d.nombre_producto, d.estatus, d.created_at, d.updated_at,
           u.username AS usuario,
           COALESCE(a.cnt,0) AS adjuntos
    FROM devoluciones d
    LEFT JOIN usuarios u ON u.id = d.user_id
    LEFT JOIN (
      SELECT devolucion_id, COUNT(*) AS cnt
      FROM devolucion_adjuntos
      GROUP BY devolucion_id
    ) a ON a.devolucion_id = d.id
  ";
  $params = [];
  if (!$esAdmin) {
    if (!$uid) {
      $rows = []; /* no logueado */
    } else {
      $sql .= " WHERE d.user_id = :uid ";
      $params[':uid'] = (int)$uid;
    }
  }
  $sql .= " ORDER BY d.created_at DESC LIMIT 300 ";
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll();
} else {
  $like = "%{$term}%";
  $sql = "
    SELECT d.id, d.folio, d.nombre_producto, d.estatus, d.created_at, d.updated_at,
           u.username AS usuario,
           COALESCE(a.cnt,0) AS adjuntos
    FROM devoluciones d
    LEFT JOIN usuarios u ON u.id = d.user_id
    LEFT JOIN (
      SELECT devolucion_id, COUNT(*) AS cnt
      FROM devolucion_adjuntos
      GROUP BY devolucion_id
    ) a ON a.devolucion_id = d.id
    WHERE (d.folio LIKE :q OR d.nombre_producto LIKE :q OR d.estatus LIKE :q OR u.username LIKE :q)
  ";
  $params = [':q' => $like];

  if (!$esAdmin) {
    if (!$uid) {
      $rows = [];
    } else {
      $sql .= " AND d.user_id = :uid ";
      $params[':uid'] = (int)$uid;
    }
  }
  $sql .= " ORDER BY d.created_at DESC LIMIT 300 ";
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" href="..\assets\img\iconpestalla.png" type="image/x-icon">
  <title>Devoluciones</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>.swal2-popup.swal2-rounded{border-radius:18px!important}</style>
  <link rel="stylesheet" href="./Estilos/styles_index.css?v=1.1">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

  <style>
    .cell-desc { max-width: 520px; color: #4b5563 }
    .empty { padding: 18px; color: #6b7280 }
    .note { margin-top: 10px; font-size: .95rem; color: #374151 }
    .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px }
    .fld { display: flex; flex-direction: column; gap: 6px }
    .fld--full { grid-column: 1 / -1 }
    .modal__body .grid input,
    .modal__body .grid textarea,
    .modal__body .grid select { width: 100% }

    /* Badges */
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
  if (!window.__clarityIdentDone) {
    window.__clarityIdentDone = true;
    window.clarity = window.clarity || function(){(window.clarity.q=window.clarity.q||[]).push(arguments);};
    <?php if (!empty($clarity_id) || !empty($clarity_name)): ?>
      clarity("identify", "<?= htmlspecialchars($clarity_name ?: $clarity_id, ENT_QUOTES, 'UTF-8') ?>");
      clarity("set", "rol",      "<?= htmlspecialchars($clarity_rol, ENT_QUOTES, 'UTF-8') ?>");
      clarity("set", "sucursal", "<?= htmlspecialchars($clarity_suc, ENT_QUOTES, 'UTF-8') ?>");
    <?php endif; ?>
  }
})();
</script>
</head>

<body>
  <?php require __DIR__ . "/../Componentes/sidebar.php"; ?>

  <main id="app" class="container">
    <!-- HERO -->
    <section class="hero" aria-labelledby="devoluciones-title">
      <div class="hero-inner">
        <div class="title-row">
          <div class="logo-box" aria-hidden="true">
            <img class="iconSize" src="<?= APP_URL ?>/assets/svg/DevoIconAzul.svg" alt="icono">
          </div>
          <h1 id="devoluciones-title">DEVOLUCIONES</h1>
          <h2 class="bntregresar">
            <a class="btn-sm" href="<?= defined('APP_URL') ? h(APP_URL) . '/inicio' : '/portal' ?>">VOLVER AL PORTAL</a>
          </h2>
        </div>

        <section class="tc-devolucion" aria-labelledby="titulo-devolucion">
          <h2 id="titulo-devolucion">Términos y Condiciones</h2>
          <ul>
            <li>Las devoluciones se aceptan dentro de los <strong>7 días hábiles</strong> posteriores a la entrega.</li>
            <li>El producto debe estar en <strong>perfecto estado</strong>, sin uso, con empaque original, manuales y accesorios completos.</li>
            <li>Es indispensable presentar la <strong>factura o ticket de compra</strong>.</li>
            <li>No aplican devoluciones en <strong>productos usados</strong>, <strong>cables a la medida</strong>, <strong>material en liquidación</strong>, <strong>productos instalados</strong> o <strong>pedido especial</strong>.</li>
            <li>Todo producto será sujeto a <strong>revisión técnica</strong> antes de autorizar la devolución.</li>
            <li>El reembolso se realizará mediante <strong>nota de crédito</strong> para compras futuras.</li>
          </ul>
        </section>

        <div class="toolbar">
          <?php if ($isLogged): ?>
            <a class="btn" id="btnNuevaDevolucion" href="#" role="button">INICIAR DEVOLUCIÓN</a>
          <?php else: ?>
            <a class="btn" href="<?= h(APP_URL) ?>/">INICIAR SESIÓN</a>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <!-- HISTORIAL -->
    <section class="panel" aria-labelledby="historial-title">
      <div class="titulohistoria">
        <h2 id="historial-title">Historial</h2>
        <div class="search-box">
          <span class="search-icon">
            <img src="<?= APP_URL ?>/assets/svg/LupaIcon.svg" alt="Buscar">
          </span>
          <input type="search" id="q" class="search-input" placeholder="Buscar" value="<?= h($term) ?>">
        </div>
        <div class="Prueba">
          <div class="table-wrap">
            <table role="table" aria-describedby="historial-title" id="tabla">
              <thead>
                <tr>
                  <th>Folio</th>
                  <th>Nombre de producto</th>
                  <th>Fecha de solicitud</th>
                  <th>Estatus</th>
                  <th class="actions">Detalles</th>
                </tr>
              </thead>
              <tbody id="tbody">
                <?php if (!$rows): ?>
                  <tr><td colspan="5" class="empty">
                    <?= $isLogged
                      ? 'No hay devoluciones registradas. Da clic en <b>INICIAR DEVOLUCIÓN</b>.'
                      : 'Inicia sesión para ver tus devoluciones.' ?>
                  </td></tr>
                <?php else: foreach ($rows as $r):
                  $folio = $r['folio'];
                  $prod  = trim((string)$r['nombre_producto']);
                  if (mb_strlen($prod) > 160) $prod = mb_substr($prod, 0, 160) . '…';
                  $fecha     = date('d/m/y g:iA', strtotime($r['created_at']));
                  $code      = (int)$r['estatus'];
                  $badge     = estatusBadgeCliente($code);
                  $usuario   = $r['usuario'] ?? '—';
                  $estatusTx = status_label_cliente($code);
                  $textIndex = strtolower($folio.' '.$estatusTx.' '.$prod.' '.$usuario);
                ?>
                  <tr data-text="<?= h($textIndex) ?>">
                    <td data-label="Folio" class="folio"><?= h($folio) ?></td>
                    <td class="cell-desc">
                      <?= h($prod) ?>
                      <?php if ((int)$r['adjuntos'] > 0): ?>
                        <small style="color:#6b7280"> · <?= (int)$r['adjuntos'] ?> adjunto(s)</small>
                      <?php endif; ?>
                      <div style="color:#6b7280;font-size:.9em">por <?= h($usuario) ?></div>
                    </td>
                    <td data-label="Fecha" class="date"><?= h($fecha) ?></td>
                    <td data-label="Estatus"><?= $badge ?></td>
                    <td data-label="Acciones" class="actionstwo">
                      <a class="btn-sm" href="ver_devolucion.php?id=<?= (int)$r['id'] ?>">VER DETALLES</a>
                    </td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
    </section>
  </main>

  <!-- ===== Modal Nueva Devolución ===== -->
  <div class="modal" id="modalDevolucion" hidden>
    <div class="modal__backdrop" data-close></div>
    <div class="modal__dialog" role="dialog" aria-modal="true" aria-labelledby="md-title">
      <header class="modal__hd">
        <h3 id="md-title">Registro de pre-devolución</h3>
        <button class="modal__close" type="button" title="Cerrar" aria-label="Cerrar" data-close>×</button>
      </header>

      <form id="frmDevol" class="modal__body" enctype="multipart/form-data">
        <div class="grid">

          <label class="fld">
            <span>Nombre del producto</span>
            <input type="text" name="nombre_producto" required maxlength="200">
          </label>

          <label class="fld">
            <span>No. de serie</span>
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
            <span>Marca</span>
            <input type="text" name="marca" maxlength="120">
          </label>

          <label class="fld">
            <span>Sucursal</span>
            <select class="sucursal-style" name="sucursal" id="selSucursal" required>
              <option value="" disabled selected>Selecciona una sucursal…</option>
              <option value="deasa">DEASA</option>
              <option value="tapatia">TAPATÍA</option>
              <option value="dimegsa">DIMEGSA</option>
              <option value="iluminacion">ILUMINACIÓN</option>
              <option value="segsa">SEGSA</option>
              <option value="fesa">FESA</option>
              <option value="codi">CODI</option>
              <option value="vallarta">VALLARTA</option>
              <option value="queretaro">QUERÉTARO</option>
              <option value="gabsa">GABSA</option>
              <option value="aiesa">AIESA</option>
            </select>
          </label>

          <!-- NUEVO: vendedor dependiente de sucursal -->
          <label class="fld" id="wrapVendedor" style="display:none">
            <span>Vendedor que lo atendió</span>
            <select  class="sucursal-style" name="vendedor" id="selVendedor"></select>
            <small style="color:#6b7280">Se llena según la sucursal seleccionada.</small>
          </label>

          <label class="fld fld--full">
            <span>Motivo de la devolución</span>
            <textarea name="motivo" rows="4" required></textarea>
          </label>

          <label class="fld fld--full">
            <span>Fotos / Video (múltiples)</span>
            <input type="file" id="adj" name="adjuntos[]" accept="image/*,video/*" multiple>
          </label>

          <label class="fld">
            <span>Nombre de contacto</span>
            <input type="text" name="contacto_nombre" required maxlength="200">
          </label>

          <label class="fld">
            <span>Teléfono de contacto</span>
            <input type="tel" name="contacto_tel" maxlength="30" placeholder="10 dígitos">
          </label>

          <label class="fld fld--full">
            <span>Correo de contacto</span>
            <input type="email" name="contacto_email" maxlength="190">
          </label>
        </div>

        <footer class="modal__ft">
          <button type="button" class="btn-sec" data-close>Cancelar</button>
          <button type="submit" class="btn" id="btnEnviar">Enviar solicitud</button>
        </footer>
      </form>
    </div>
  </div>

  <!-- Modal OK -->
  <div class="modal" id="modalOK" hidden>
    <div class="modal__backdrop" data-close></div>
    <div class="modal__dialog" role="dialog" aria-modal="true">
      <header class="modal__hd">
        <h3>Devolución enviada</h3>
        <button class="modal__close" type="button" title="Cerrar" aria-label="Cerrar" data-close>×</button>
      </header>
      <div class="modal__body">
        <p id="okMsg">¡Gracias! Hemos registrado tu devolución.</p>
      </div>
      <footer class="modal__ft">
        <button class="btn" data-close>Cerrar</button>
      </footer>
    </div>
  </div>

  <script>
  // ----- Modales -----
  const modalNuevo = document.getElementById('modalDevolucion');
  const modalOK    = document.getElementById('modalOK');
  const okMsg      = document.getElementById('okMsg');

  const openNuevo  = ()=>{ modalNuevo.hidden=false; document.body.style.overflow='hidden'; };
  const closeNuevo = ()=>{ modalNuevo.hidden=true;  document.body.style.overflow='';       };

  document.getElementById('btnNuevaDevolucion')?.addEventListener('click', e=>{
    e.preventDefault(); openNuevo();
  });
  modalNuevo.querySelectorAll('[data-close]').forEach(el=>el.addEventListener('click', closeNuevo));
  window.addEventListener('keydown', e=>{ if(e.key==='Escape') closeNuevo(); });

  // ----- Buscador client-side -----
  const q = document.getElementById('q');
  const tbody = document.getElementById('tbody');
  const trs = Array.from(tbody.querySelectorAll('tr')).filter(tr => tr.dataset.text !== undefined);
  (function presetFilter(){
    const term = (q.value || '').toLowerCase().trim();
    if (term) trs.forEach(tr => tr.style.display = tr.dataset.text.includes(term) ? '' : 'none');
  })();
  q.addEventListener('input', ()=>{
    const term = (q.value || '').toLowerCase().trim();
    trs.forEach(tr => tr.style.display = (term === '' || tr.dataset.text.includes(term)) ? '' : 'none');
  });

  // ----- SweetAlert OK -----
  function alertaDevolucionOK(folio, id='') {
    Swal.fire({
      title: '¡Devolución registrada!',
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
        window.location.href = `ver_devolucion.php?id=${encodeURIComponent(id)}`;
      } else if (res.isDenied) {
        const f = document.getElementById('folioTxt').textContent.trim();
        navigator.clipboard.writeText(f).then(()=>{
          Swal.fire({ icon:'success', title:'Folio copiado', timer:1400, showConfirmButton:false });
        });
      } else {
        location.reload();
      }
    });
  }

  // ===== Validación de tamaños en front =====
  document.addEventListener('DOMContentLoaded', () => {
    const MAX_MB_PER_FILE = 20;
    const MAX_MB_TOTAL    = 80;
    const MAX_B_PER_FILE  = MAX_MB_PER_FILE * 1024 * 1024;
    const MAX_B_TOTAL     = MAX_MB_TOTAL    * 1024 * 1024;

    document.querySelectorAll('input[type="file"][name="adjuntos[]"]').forEach(inp => {
      inp.addEventListener('change', () => {
        const grandes = []; let total = 0;
        for (const f of inp.files) {
          total += f.size || 0;
          if ((f.size || 0) > MAX_B_PER_FILE) grandes.push(`${f.name} (${(f.size/1024/1024).toFixed(1)} MB)`);
        }
        if (grandes.length || total > MAX_B_TOTAL) {
          const totalMsg = total > MAX_B_TOTAL
            ? `<p>El total seleccionado es ${(total/1024/1024).toFixed(1)} MB y excede ${MAX_MB_TOTAL} MB.</p>` : '';
          Swal.fire({
            icon: 'warning',
            title: 'Adjuntos muy pesados',
            html: `
              ${grandes.length ? `<p>Estos archivos superan ${MAX_MB_PER_FILE} MB:</p>
              <ul style="text-align:left;margin-top:8px;">${grandes.map(x => `<li>${x}</li>`).join('')}</ul>` : ''}
              ${totalMsg}
              <p style="margin-top:10px;">Selecciona archivos más pequeños o menos archivos.</p>
            `,
            confirmButtonText: 'Entendido',
            customClass: { popup: 'swal2-rounded' }
          });
          inp.value = '';
        }
      });

      inp.closest('form')?.addEventListener('submit', (ev) => {
        let total = 0, excede = false;
        for (const f of inp.files) {
          total += f.size || 0;
          if ((f.size || 0) > MAX_B_PER_FILE) { excede = true; break; }
        }
        if (excede || total > MAX_B_TOTAL) {
          ev.preventDefault();
          Swal.fire({ icon:'warning', title:'Tamaño de archivo excedido', text:'Ajusta los archivos antes de enviar.', customClass:{popup:'swal2-rounded'}});
        }
      }, { capture: true });
    });
  });

  // ====== Sucursal -> Vendedores ======
  const selSucursal  = document.getElementById('selSucursal');
  const wrapVendedor = document.getElementById('wrapVendedor');
  const selVendedor  = document.getElementById('selVendedor');

  function fillSelect(select, items, placeholder='Selecciona…') {
    select.innerHTML = '';
    const opt0 = document.createElement('option');
    opt0.value = ''; opt0.textContent = placeholder; opt0.disabled = true; opt0.selected = true;
    select.appendChild(opt0);
    for (const it of items) {
      const op = document.createElement('option');
      op.value = it.id;
      op.textContent = it.label;
      select.appendChild(op);
    }
  }

  async function loadVendedoresBySucursal(suc) {
    wrapVendedor.style.display = 'none';
    selVendedor.required = false;
    fillSelect(selVendedor, []);
    if (!suc) return;

    try {
      const r = await fetch(`./api_vendedores.php?sucursal=${encodeURIComponent(suc)}`, { credentials: 'same-origin' });
      const j = await r.json();
      if (j.ok && Array.isArray(j.data) && j.data.length) {
        fillSelect(selVendedor, j.data, 'Selecciona un vendedor…');
        wrapVendedor.style.display = '';
        selVendedor.required = true; // obligatorio si hay lista
      } else {
        wrapVendedor.style.display = 'none';
        selVendedor.required = false;
      }
    } catch (e) {
      console.error(e);
      wrapVendedor.style.display = 'none';
      selVendedor.required = false;
    }
  }

  selSucursal?.addEventListener('change', e => {
    const val = (e.target.value || '').trim();
    loadVendedoresBySucursal(val);
  });
  if (selSucursal && selSucursal.value) loadVendedoresBySucursal(selSucursal.value);

  // ----- Envío AJAX -----
  const frm = document.getElementById('frmDevol');
  const btnEnviar = document.getElementById('btnEnviar');

  frm.addEventListener('submit', async (e) => {
    e.preventDefault();
    btnEnviar.disabled = true; btnEnviar.textContent = 'Enviando…';
    try{
      const fd = new FormData(frm);
      const r  = await fetch('./crear_devolucion.php', { method:'POST', body: fd, credentials:'same-origin' });
      const j  = await r.json().catch(()=>({ok:false,msg:'Respuesta inválida del servidor'}));
      if (j.ok){
        closeNuevo();
        alertaDevolucionOK(j.folio, j.id || '');
        frm.reset();
      } else {
        Swal.fire({ icon:'error', title:'No se pudo enviar', text: (j.msg || 'Intenta de nuevo') + (j.err ? `\n${j.err}` : '') });
      }
    } catch(err){
      Swal.fire({ icon:'error', title:'Error', text: err.message });
    } finally {
      btnEnviar.disabled = false; btnEnviar.textContent = 'Enviar solicitud';
    }
  });
  </script>
</body>
</html>

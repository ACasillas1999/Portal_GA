<?php


ini_set('session.cookie_httponly', true);
/* En local (HTTP) no pongas cookie_secure en true */
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
  ini_set('session.cookie_secure', '0');
} else {
  ini_set('session.cookie_secure', '1');
}
session_name("GA");
session_start();


date_default_timezone_set('America/Mexico_City');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/db.php'; // $pdo (PDO)
require_once __DIR__ . '/../app/session_boot.php';
require_login(); 

$term = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

$uid = $_SESSION['user_id']
  ?? $_SESSION['ID']
  ?? $_SESSION['idUsuario']
  ?? $_SESSION['usuario_id']
  ?? null;
$rol   = $_SESSION['Rol']     ?? '';
$email = $_SESSION['Email']
  ?? $_SESSION['email']
  ?? $_SESSION['Correo']
  ?? null;
$nombrePref = $_SESSION['username'] ?? '';
$emailPref  = $_SESSION['Email'] ?? ($_SESSION['Correo'] ?? '');
$telPref    = preg_replace('/\D+/', '', $_SESSION['Telefono'] ?? '');
$rfcPref    = $_SESSION['RFC'] ?? '';
$empPref    = $_SESSION['Empresa'] ?? '';

function h($s)
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// ===== Cargar solicitudes =====
// Admin ve todas; usuarios ven las suyas (por id_usuario o por email de sesión).
if ($rol === 'Admin') {
  $st = $pdo->query("
   SELECT id, folio, descripcion, estado, created_at, updated_at
FROM protocolo_solicitudes
ORDER BY created_at DESC
LIMIT 300
  ");
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
} else {
  $rows = [];
  $conds  = [];
  $params = [];

  if ($uid) {
    $conds[] = 'id_usuario = ?';
    $params[] = $uid;
  }
  if ($email) {
    $conds[] = 'email = ?';
    $params[] = $email;
  }

  if ($conds) {
    $sql = "
  SELECT id, folio, descripcion, estado, created_at, updated_at
  FROM protocolo_solicitudes
  WHERE (" . implode(' OR ', $conds) . ")
  ORDER BY created_at DESC
  LIMIT 300
";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  }
}

// Mapeo visual de estado -> clase CSS
function estadoBadgeProt($estadoRaw)
{
  $txtMap = [
    'enviada'     => ['cls' => 'enviado',  'label' => 'Enviada'],
    'enviado'     => ['cls' => 'enviado',  'label' => 'Enviada'],
    'recibida'    => ['cls' => 'proceso',  'label' => 'En proceso'],
    'en revisión' => ['cls' => 'proceso',  'label' => 'En proceso'],
    'revision'    => ['cls' => 'proceso',  'label' => 'En proceso'],
    'procesando'  => ['cls' => 'proceso',  'label' => 'En proceso'],
    'aprobado'    => ['cls' => 'final',    'label' => 'Aprobado'],
    'aprobada'    => ['cls' => 'final',    'label' => 'Aprobado'],
    'entregado'   => ['cls' => 'final',    'label' => 'Aprobado'],
    'rechazado'   => ['cls' => 'cancelado', 'label' => 'Rechazado'],
    'rechazada'   => ['cls' => 'cancelado', 'label' => 'Rechazado'],
    'cancelado'   => ['cls' => 'cancelado', 'label' => 'Cancelado'],
    'cancelada'   => ['cls' => 'cancelado', 'label' => 'Cancelado'],
  ];

  // ¿numérico?
  if (is_numeric($estadoRaw)) {
    $code = (int)$estadoRaw;
    if ($code === 1)  return '<span class="status final"><span class="dot"></span>Aprobado</span>';
    if ($code === 11) return '<span class="status cancelado"><span class="dot"></span>Rechazado</span>';
    /* 0 u otros -> proceso */
    return '<span class="status proceso"><span class="dot"></span>En proceso</span>';
  }

  // Texto
  $e = mb_strtolower(trim((string)$estadoRaw));
  $m = $txtMap[$e] ?? ['cls' => 'proceso', 'label' => ($estadoRaw ?: 'En proceso')];

  return '<span class="status ' . $m['cls'] . '"><span class="dot"></span>' . h($m['label']) . '</span>';
}

?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="icon" href="..\assets\img\iconpestalla.png" type="image/x-icon">
  <title>Protocolos</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>.swal2-popup.swal2-rounded{border-radius:18px!important}</style>

  <link rel="stylesheet" href="./estilos/style.css?v=1.0">
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">


  <style>
    .cell-desc {
      max-width: 520px;
      color: #4b5563
    }

    .empty {
      padding: 18px;
      color: #6b7280
    }

    .note {
      margin-top: 10px;
      font-size: .95rem;
      color: #374151
    }

    
    .fld {
      display: flex;
      flex-direction: column;
      gap: 6px
    }

    .fld--full {
      grid-column: 1 / -1
    }

    .modal__body .grid input,
    .modal__body .grid textarea {
      width: 100%
    }

    .status {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 6px 10px;
      border-radius: 999px;
      font-weight: 700;
      font-size: 12px;
      border: 1px solid transparent;
    }

    .status .dot {
      width: 8px;
      height: 8px;
      border-radius: 999px;
      display: inline-block;
    }

    /* Enviado (azul suave) */
    .status.enviado {
      background: #ecf3ff;
      color: #0b5fa3;
      border-color: #cfe1ff;
    }

    .status.enviado .dot {
      background: #3b82f6;
    }

    /* En proceso (ámbar) */
    .status.proceso {
      background: #fff7ed;
      color: #b45309;
      border-color: #fde68a;
    }

    .status.proceso .dot {
      background: #f59e0b;
    }

    /* Aprobado / Final (verde) */
    .status.final {
      background: #ecfdf5;
      color: #047857;
      border-color: #a7f3d0;
    }

    .status.final .dot {
      background: #10b981;
    }

    /* Rechazado / Cancelado (rojo) */
    .status.cancelado {
      background: #fef2f2;
      color: #b91c1c;
      border-color: #fecaca;
    }

    .status.cancelado .dot {
      background: #ef4444;
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

  <?php require __DIR__ . '/../Componentes/sidebar.php'; ?>

  <main class="container">

    <!-- HERO -->
    <section class="hero" aria-labelledby="protocolos-title">
      <div class="hero-inner">
        <div class="title-row">
          <div class="logo-box" aria-hidden="true">
            <!--<svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#0b5fa3" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 7l9 4 9-4M3 7l9-4 9 4M3 7v10l9 4 9-4V7" />
                            <path d="M12 11v10" />
                        </svg>-->
            <img class="iconSize" src="<?= APP_URL ?>/assets/svg/ProtocoloIconOrange.svg" alt="icono">

          </div>
          <h1 id="protocolos-title">PROTOCOLOS</h1>
          <h2 class="bntregresar">
            <a class="btn-sm" href="<?= defined('APP_URL') ? h(APP_URL) . '/inicio' : '/portal' ?>">VOLVER AL PORTAL</a>
          </h2>
        </div>
        <section class="tc-devolucion" aria-labelledby="titulo-devolucion">
          <h2 id="titulo-devolucion">Términos y Condiciones</h2>
          <ul>
            <li>Es responsabilidad del cliente verificar la compatibilidad del producto con los protocolos exigidos.</li>
            <li>Se deberá presentar factura y comprobante de compra para cualquier trámite relacionado.</li>
            <li>La empresa <strong>no se hace responsable </strong>por retrasos derivados de inspecciones o aprobaciones de CFE.</li>
            <li>Cualquier modificación al producto anula automáticamente la garantía y protocolos asociados.</li>
            <li>El cliente contará con un plazo de <strong>doce (12) meses</strong>, contados a partir de la fecha de adquisición del producto, para solicitar el protocolo correspondiente.</li>
            <li>Debe contar con registro en el Portal de Sigla 03 de CFE ya que se asignará en automático a la razón social a la que se facturó. Cualquier condición diferente a la mencionada, favor de comentarlo en el <strong>siguiente campo.</strong></li>
          </ul>
        </section>


        <div class="toolbar">
          <a class="btn" href="#" id="btnNuevo">INICIAR SOLICITUD</a>
        </div>
      </div>

      <!-- Marca de agua -->
      <!-- <svg class="watermark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 7l9 4 9-4M3 7l9-4 9 4M3 7v10l9 4 9-4V7" />
                <path d="M12 11v10" />
            </svg>-->
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
                  <th>Descripción</th>
                  <th>Fecha de solicitud</th>
                  <th>Estatus</th>
                  <th class="actions">Detalles</th>
                </tr>
              </thead>
              <tbody id="tbody">
                <?php if (!$rows): ?>
                  <tr>
                    <td colspan="5" class="empty">Aún no tienes solicitudes. Da clic en <b>INICIAR SOLICITUD</b>.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($rows as $r):
                    $folio = $r['folio'];
                    $desc  = trim((string)$r['descripcion']);
                    if (mb_strlen($desc) > 160) {
                      $desc = mb_substr($desc, 0, 160) . '…';
                    }
                    $fecha = date('d/m/y g:iA', strtotime($r['created_at']));
                    $estadoHtml = estadoBadgeProt($r['estado']);
                    $textIndex = strtolower(($folio . ' ' . $r['estado'] . ' ' . $desc));
                  ?>
                    <tr data-text="<?= h($textIndex) ?>">
                      <td data-label="Folio" class="folio"><?= h($folio) ?></td>
                      <td class="cell-desc"><?= h($desc) ?></td>
                      <td data-label="Fecha" class="date"><?= h($fecha) ?></td>
                      <td data-label="Estatus"><?= $estadoHtml ?></td>
                      <td data-label="Acciones" class="actions">
                        <a class="btn-sm" href="ver.php?id=<?= (int)$r['id'] ?>">VER DETALLES</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>

            </table>
          </div>
        </div>
    </section>

  </main>

  

  <div class="modal" id="modalNuevo" hidden>
    <div class="modal__backdrop" data-close></div>
    <div class="modal__dialog" role="dialog" aria-modal="true" aria-labelledby="mdn-title">
      <header class="modal__hd">
        <h3 id="mdn-title">Nueva solicitud de protocolo</h3>
        <button class="modal__close" type="button" title="Cerrar" aria-label="Cerrar" data-close>×</button>
      </header>

      <form id="frmProt" class="modal__body" enctype="multipart/form-data">
        <div class="grid">
          <label class="fld">
            <span>Fecha del registro</span>
            <input type="text" name="fecha_registro_vista" value="<?= date('Y-m-d') ?>" readonly>
          </label>

          <label class="fld">
            <span>Correo</span>
            <input type="email" name="email" required value="<?= htmlspecialchars($emailPref) ?>">
          </label>

          <label class="fld">
            <span>Nombre de contacto</span>
            <input type="text" name="nombre_contacto" required value="<?= htmlspecialchars($nombrePref) ?>">
          </label>

          <label class="fld">
            <span>Empresa / Razón social</span>
            <input type="text" name="empresa" required placeholder="Tu razón social">
          </label>

          <label class="fld">
            <span>Sucursal</span>
            <select class="sucursal-style" name="sucursal" required>
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

          <label class="fld">
            <span>Teléfono</span>
            <input type="tel" name="telefono" required pattern="\d{10}" value="<?= htmlspecialchars($telPref) ?>" id="tel">
          </label>

          <label class="fld">
            <span>No. de serie (si aplica)</span>
            <input type="text" name="no_serie" placeholder="Opcional">
          </label>

          <label class="fld">
            <span>No. de factura</span>
            <input type="text" name="no_factura" placeholder="Ej. 54123">
          </label>

          <label class="fld">
            <span>Fecha de factura</span>
            <input type="date" name="fecha_factura">
          </label>

          <label class="fld fld--full">
            <span>Descripción del caso</span>
            <textarea name="descripcion" rows="5" required placeholder="Describe aplicación, capacidad, tensión, contexto…"></textarea>
          </label>

          <label class="fld fld--full">
  <span>Adjuntos (PDF/JPG/PNG/DOCX/XLSX/ZIP) — máx 20MB</span>
  <input
    type="file"
    id="adjProt"
    name="adjuntos[]"
    multiple
    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.zip,application/pdf,image/jpeg,image/png,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/zip"
  >
</label>

          <footer class="modal__ft">
            <button type="button" class="btn-sec" data-close>Cancelar</button>
            <button type="submit" class="btn btnEnviar" id="btnEnviar">Enviar solicitud</button>
          </footer>
      </form>






      <!-- Modal: Nueva solicitud de Protocolo -->
      <!-- <div class="modal" id="modalNuevo" hidden>
        <div class="modal__backdrop" data-close></div>
        <div class="modal__dialog" role="dialog" aria-modal="true" aria-labelledby="mdn-title">
            <header class="modal__hd">
                <h3 id="mdn-title">Nueva solicitud de Protocolo</h3>
                <button class="modal__close" type="button" title="Cerrar" aria-label="Cerrar" data-close>×</button>
            </header>

            <form id="frmProt" class="modal__body" enctype="multipart/form-data">-->
      <!-- DATOS DE CONTACTO / FACTURA -->
      <!--<table class="form-table" aria-label="Datos de contacto">
  <tbody>
    <tr>
      <th>Fecha del registro</th>
      <td><input type="text" name="fecha_registro_vista" value="<?= date('Y-m-d') ?>" readonly></td>
    </tr>
    <tr>
      <th>Correo</th>
      <td><input type="email" name="email" required value="<?= htmlspecialchars($emailPref) ?>"></td>
    </tr>
    <tr>
      <th>Nombre de contacto</th>
      <td><input type="text" name="nombre_contacto" required value="<?= htmlspecialchars($nombrePref) ?>"></td>
    </tr>
    <tr>
      <th>Empresa / Razón social</th>
      <td><input type="text" name="empresa" required placeholder="Tu razón social"></td>
    </tr>
    <tr>
      <th>RFC</th>
      <td><input type="text" name="rfc" required maxlength="13" style="text-transform:uppercase" placeholder="Ej. ABC123456XYZ"></td>
    </tr>
    <tr>
      <th>Sucursal</th>
      <td>  <label class="fld">
  <span>Sucursal</span>
  <select name="sucursal" required>
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
</label></td>
    </tr>
    <tr>
      <th>Teléfono</th>
      <td><input type="tel" name="telefono" required pattern="\d{10}" value="<?= htmlspecialchars($telPref) ?>" id="tel"></td>
    </tr>-->

      <!-- Datos de factura / serie (opcional) -->
      <!--<tr>
      <th>No. de serie (si aplica)</th>
      <td><input type="text" name="no_serie" placeholder="Opcional"></td>
    </tr>
    <tr>
      <th>No. de factura</th>
      <td><input type="text" name="no_factura" placeholder="Ej. 54123"></td>
    </tr>
    <tr>
      <th>Fecha de factura</th>
      <td><input type="date" name="fecha_factura"></td>
    </tr>
  </tbody>
</table>-->

      <!-- DETALLE DE LA SOLICITUD -->
      <!--<table class="form-table" aria-label="Detalle" style="margin-top:14px">
    <tbody>
      <tr>
        <th>Descripción del caso</th>
        <td>
          <textarea name="descripcion" rows="5" required placeholder="Describe aplicación, capacidad, tensión, contexto…"></textarea>
        </td>
      </tr>
      <tr>
        <th>Adjuntos (PDF/JPG/PNG/DOCX/XLSX/ZIP) — máx 20MB c/u</th>
        <td><input type="file" name="adjuntos[]" multiple></td>
      </tr>
    </tbody>
  </table>

  <footer class="modal__ft">
    <button type="button" class="btn-sec" data-close>Cancelar</button>
    <button type="submit" class="btn" id="btnEnviar">Enviar solicitud</button>
  </footer>
</form>


        </div>
    </div>-->


    </div>
  </div>

  <!-- Modal OK -->
  <div class="modal" id="modalOK" hidden>
    <div class="modal__backdrop" data-close></div>
    <div class="modal__dialog" role="dialog" aria-modal="true">
      <header class="modal__hd">
        <h3>Solicitud enviada</h3>
        <button class="modal__close" type="button" title="Cerrar" aria-label="Cerrar" data-close>×</button>
      </header>
      <div class="modal__body">
        <p id="okMsg">¡Gracias! Te enviaremos el protocolo al correo registrado.</p>
      </div>
      <footer class="modal__ft">
        <button class="btn" data-close>Cerrar</button>
      </footer>
    </div>
  </div>

  
   <script>
  // ===== Modales
  const modalNuevo = document.getElementById('modalNuevo');

  const openNuevo  = ()=>{ modalNuevo.hidden=false; document.body.style.overflow='hidden'; };
  const closeNuevo = ()=>{ modalNuevo.hidden=true;  document.body.style.overflow='';       };

  document.getElementById('btnNuevo').addEventListener('click', (e)=>{
    e.preventDefault(); openNuevo();
  });
  modalNuevo.querySelectorAll('[data-close]').forEach(el=>el.addEventListener('click', closeNuevo));
  window.addEventListener('keydown', (e)=>{ if(e.key==='Escape') closeNuevo(); });

  // ===== Buscador client-side
  const q     = document.getElementById('q');
  const tbody = document.getElementById('tbody');
  const rows  = Array.from(tbody.querySelectorAll('tr')).filter(tr => tr.dataset.text !== undefined);

  function filtra() {
    const term = (q.value || '').toLowerCase().trim();
    rows.forEach(tr => tr.style.display = (term==='' || tr.dataset.text.includes(term)) ? '' : 'none');
  }
  q.addEventListener('input', filtra);
  document.addEventListener('DOMContentLoaded', filtra); // aplica filtro inicial por ?q=

  // ===== SweetAlert bonito
  function alertaProtocoloOK(folio, id='') {
    Swal.fire({
      title: '¡Solicitud registrada!',
      html: `<div style="font-size:15px;line-height:1.5">
               <b>Folio:</b> <code id="folioTxt">${folio}</code>
             </div>`,
      icon: 'success',
      showCancelButton: true,
      confirmButtonText: (id ? 'Ver detalles' : 'Aceptar'),
      cancelButtonText: 'Cerrar',
      showDenyButton: true,
      denyButtonText: 'Copiar folio',
      customClass: { popup: 'swal2-rounded' },
      allowOutsideClick: false,
      allowEscapeKey: false
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
// ===== Validación de tamaño de archivos (PROTOCOLOS) =====
const MAX_MB_PROT = 20;                    // límite por archivo
const MAX_B_PROT  = MAX_MB_PROT * 1024 * 1024;
// (opcional) límite total del conjunto
const MAX_TOTAL_MB_PROT = 80;
const MAX_TOTAL_B_PROT  = MAX_TOTAL_MB_PROT * 1024 * 1024;

const inpAdjProt = document.getElementById('adjProt');

if (inpAdjProt) {
  inpAdjProt.addEventListener('change', () => {
    const muyGrandes = [];
    let total = 0;

    for (const f of inpAdjProt.files) {
      total += f.size;
      if (f.size > MAX_B_PROT) {
        muyGrandes.push(`${f.name} (${(f.size/1024/1024).toFixed(1)} MB)`);
      }
    }

    if (muyGrandes.length) {
      Swal.fire({
        icon: 'warning',
        title: 'Archivo demasiado pesado',
        html: `
          <p>Estos archivos superan ${MAX_MB_PROT} MB:</p>
          <ul style="text-align:left;margin-top:8px;">
            ${muyGrandes.map(x => `<li>${x}</li>`).join('')}
          </ul>
          <p style="margin-top:10px;">Selecciona archivos más pequeños, por favor.</p>
        `,
        confirmButtonText: 'Entendido',
        customClass: { popup: 'swal2-rounded' }
      });
      inpAdjProt.value = ''; // limpia selección
      return;
    }

    // (opcional) valida el total
    if (total > MAX_TOTAL_B_PROT) {
      Swal.fire({
        icon: 'warning',
        title: 'Adjuntos muy pesados',
        html: `
          <p>El total seleccionado es ${(total/1024/1024).toFixed(1)} MB y excede ${MAX_TOTAL_MB_PROT} MB.</p>
          <p>Sube menos archivos o redúcelos.</p>
        `,
        confirmButtonText: 'Entendido',
        customClass: { popup: 'swal2-rounded' }
      });
      inpAdjProt.value = '';
    }
  });
}

// (extra recomendado) Revalidar en submit por si el usuario cambió el input después del 'change'
const frmProt = document.getElementById('frmProt');
if (frmProt && inpAdjProt) {
  frmProt.addEventListener('submit', (ev) => {
    let total = 0, grande = false;
    for (const f of inpAdjProt.files) {
      total += f.size;
      if (f.size > MAX_B_PROT) { grande = true; break; }
    }
    if (grande || total > MAX_TOTAL_B_PROT) {
      ev.preventDefault();
      Swal.fire({
        icon: 'warning',
        title: 'Tamaño de archivo excedido',
        text: 'Ajusta los archivos antes de enviar.',
        customClass: { popup: 'swal2-rounded' }
      });
    }
  }, { capture: true });
}

  // ===== Envío AJAX
  const frm = document.getElementById('frmProt');
  const btnEnviar= document.getElementById('btnEnviar');

  frm.addEventListener('submit', async (e) => {
    e.preventDefault();
    btnEnviar.disabled = true; btnEnviar.textContent = 'Enviando…';
    try{
      const fd = new FormData(frm);
      const r  = await fetch('guardar_solicitud.php', { method:'POST', body: fd });
      const j  = await r.json().catch(()=>({ok:false,msg:'Respuesta inválida del servidor'}));

      if (j.ok){
        closeNuevo();
        alertaProtocoloOK(j.folio, j.id || ''); // si devuelves id, habilita "Ver detalles"
        frm.reset();
      } else {
        Swal.fire({ icon:'error', title:'No se pudo enviar', text: j.msg || 'Intenta de nuevo' });
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

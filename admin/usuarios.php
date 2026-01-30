<?php
declare(strict_types=1);
session_name('SEGAOC'); session_start();
// Seguridad mínima: exige rol ADMIN (ajusta a tu lógica)
$rol = strtoupper((string)($_SESSION['Rol'] ?? ''));
if (!in_array($rol, ['ADMIN'], true)) { http_response_code(403); exit('Acceso denegado'); }
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Administración de Usuarios</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../style.css?v=1.8">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
  body{font-family:system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif;margin:0;background:#0b0b10;color:#fff}
  header{display:flex;align-items:center;gap:16px;padding:16px 20px;border-bottom:1px solid rgba(255,255,255,.1)}
  header img{height:36px}
  main{padding:18px 20px;max-width:1100px;margin:0 auto}
  .toolbar{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px}
  .toolbar input,.toolbar select{padding:10px 12px;border-radius:8px;border:1px solid rgba(255,255,255,.15);background:#14161a;color:#fff}
  .btn{padding:10px 14px;border-radius:10px;border:1px solid rgba(255,255,255,.15);background:#1f2430;color:#fff;cursor:pointer}
  .btn.primary{background:#3b82f6;border-color:#3b82f6}
  .btn.warn{background:#ef4444;border-color:#ef4444}
  .btn.ghost{background:transparent}
  table{width:100%;border-collapse:collapse;border-radius:12px;overflow:hidden}
  th,td{padding:10px 12px;border-bottom:1px solid rgba(255,255,255,.08);text-align:left}
  th{background:#151925}
  tr:hover{background:#12151d}
  .badge{padding:3px 8px;border-radius:999px;font-size:.8rem}
  .ok{background:#133f2e;color:#22c55e}
  .off{background:#3a1212;color:#ef4444}
  .small{font-size:.9rem;opacity:.85}
  .grid{display:grid;gap:10px}
  .grid.two{grid-template-columns:1fr 1fr}
  .card{background:#101318;border:1px solid rgba(255,255,255,.08);border-radius:12px;padding:14px}
  .muted{opacity:.9}
</style>
</head>
<body>

<header>
  <img src="../assets/img/LogoColor.GrupoAscencio.svg" alt="GA">
  <h1 style="margin:0;font-size:1.25rem">Administración de Usuarios</h1>
  <div style="margin-left:auto" class="small muted">Rol: <?=htmlspecialchars($rol)?></div>
</header>

<main>
  <div class="toolbar">
    <input type="text" id="q" placeholder="Buscar: nombre, email, RFC, username">
    <select id="f_estado">
      <option value="">Todos</option>
      <option value="activo">Activos</option>
      <option value="suspendido">Suspendidos</option>
    </select>
    <button class="btn" id="btn-buscar">Buscar</button>
    <button class="btn ghost" id="btn-limpiar">Limpiar</button>
    <button class="btn primary" id="btn-nuevo">+ Nuevo usuario</button>
  </div>

  <div class="card" id="resumen" style="margin-bottom:12px"></div>

  <div class="card">
    <table id="tbl">
      <thead>
        <tr>
          <th>ID</th>
          <th>Nombre / Username</th>
          <th>Email</th>
          <th>RFC / Tel</th>
          <th>Rol</th>
          <th>Estado</th>
          <th>Creado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:10px">
      <button class="btn" id="prev">«</button>
      <span class="small muted" id="pageinfo"></span>
      <button class="btn" id="next">»</button>
    </div>
  </div>
</main>

<script>
const API = 'usuarios_api.php';
let page=1, per_page=12, lastTotal=0;

const Toast = Swal.mixin({toast:true, position:'top-end', timer:2200, showConfirmButton:false});

function fetchLista(){
  $.getJSON(API, { action:'list', q: $("#q").val().trim(), estado: $("#f_estado").val(), page, per_page })
   .done(r=>{
     const $tb = $("#tbl tbody").empty();
     lastTotal = r.total||0;
     r.items.forEach(u=>{
       const badge = u.estado==='activo' ? '<span class="badge ok">activo</span>' : '<span class="badge off">suspendido</span>';
       $tb.append(`
         <tr>
           <td>${u.id}</td>
           <td><b>${escapeHtml(u.nombre||'')}</b><br><span class="small muted">${escapeHtml(u.username||'')}</span></td>
           <td>${escapeHtml(u.email||'')}</td>
           <td>${escapeHtml(u.RFC||'')}<br><span class="small muted">${escapeHtml(u.Telefono||'')}</span></td>
           <td>${escapeHtml(u.Rol||'CLIENTE')}</td>
           <td>${badge}</td>
           <td><span class="small muted">${escapeHtml(u.creado_en||'')}</span></td>
           <td style="display:flex;gap:6px;flex-wrap:wrap">
             <button class="btn" onclick="editar(${u.id})">Editar</button>
             <button class="btn" onclick="resetPwd(${u.id})">Reset pass</button>
             ${u.estado==='activo'
               ? `<button class="btn warn" onclick="toggleEstado(${u.id},'suspendido')">Suspender</button>`
               : `<button class="btn" onclick="toggleEstado(${u.id},'activo')">Activar</button>`}
           </td>
         </tr>
       `);
     });
     const from = (r.page-1)*r.per_page + (r.items.length?1:0);
     const to = (r.page-1)*r.per_page + r.items.length;
     $("#pageinfo").text(`${from}-${to} de ${r.total}`);
     $("#resumen").html(`<b>${r.total}</b> usuarios encontrados. Activos: <b>${r.activos}</b> • Suspendidos: <b>${r.suspendidos}</b>`);
   })
   .fail(jq=> Swal.fire('Error','No se pudo cargar la lista','error'));
}

function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m])); }

$("#btn-buscar").on('click', ()=>{ page=1; fetchLista(); });
$("#btn-limpiar").on('click', ()=>{ $("#q").val(''); $("#f_estado").val(''); page=1; fetchLista(); });
$("#prev").on('click', ()=>{ if(page>1){ page--; fetchLista(); }});
$("#next").on('click', ()=>{ if(page*per_page<lastTotal){ page++; fetchLista(); }});

$("#btn-nuevo").on('click', ()=>{
  Swal.fire({
    title:'Nuevo usuario',
    html: `
      <div class="grid two" style="text-align:left">
        <label>Nombre<input id="f-nombre" class="swal2-input" style="width:100%"></label>
        <label>Email<input id="f-email" type="email" class="swal2-input" style="width:100%"></label>
        <label>Username<input id="f-username" class="swal2-input" style="width:100%"></label>
        <label>Rol
          <select id="f-rol" class="swal2-input" style="width:100%">
            <option value="CLIENTE">CLIENTE</option>
            <option value="ADMIN">ADMIN</option>
            <option value="TICKETS">TICKETS</option>
          </select>
        </label>
        <label>RFC<input id="f-rfc" class="swal2-input" style="width:100%" maxlength="13"></label>
        <label>Teléfono<input id="f-tel" class="swal2-input" style="width:100%" maxlength="10"></label>
      </div>
    `,
    focusConfirm:false,
    showCancelButton:true,
    confirmButtonText:'Crear',
    preConfirm: ()=>{
      const p = {
        nombre: $("#f-nombre").val().trim(),
        email: $("#f-email").val().trim().toLowerCase(),
        username: $("#f-username").val().trim(),
        Rol: $("#f-rol").val(),
        RFC: $("#f-rfc").val().trim().toUpperCase(),
        Telefono: $("#f-tel").val().replace(/\D+/g,'').slice(0,10)
      };
      if(!p.nombre || !p.email || !p.username) return Swal.showValidationMessage('Nombre, email y username son obligatorios');
      return p;
    }
  }).then(r=>{
    if(!r.isConfirmed) return;
    $.ajax({
      url: API, method:'POST', contentType:'application/json; charset=utf-8', dataType:'json',
      data: JSON.stringify({ action:'create', payload: r.value })
    }).done(res=>{
      if(res.ok){
        fetchLista();
        Swal.fire({
          icon:'success', title:'Usuario creado',
          html:`Usuario <b>${escapeHtml(res.user.username)}</b> creado.<br>
                Contraseña temporal: <code>${escapeHtml(res.temp_password)}</code>`,
        });
      }else{
        Swal.fire('No se pudo crear', res.msg||'','error');
      }
    }).fail(()=> Swal.fire('Error','No se pudo crear','error'));
  });
});

function editar(id){
  $.getJSON(API, { action:'get', id })
    .done(u=>{
      Swal.fire({
        title:`Editar usuario #${id}`,
        html: `
         <div class="grid two" style="text-align:left">
          <label>Nombre<input id="e-nombre" class="swal2-input" value="${escapeHtml(u.nombre||'')}" style="width:100%"></label>
          <label>Email<input id="e-email" type="email" class="swal2-input" value="${escapeHtml(u.email||'')}" style="width:100%"></label>
          <label>Username<input id="e-username" class="swal2-input" value="${escapeHtml(u.username||'')}" style="width:100%"></label>
          <label>Rol
            <select id="e-rol" class="swal2-input" style="width:100%">
              <option ${u.Rol==='CLIENTE'?'selected':''}>CLIENTE</option>
              <option ${u.Rol==='ADMIN'?'selected':''}>ADMIN</option>
              <option ${u.Rol==='TICKETS'?'selected':''}>TICKETS</option>
            </select>
          </label>
          <label>RFC<input id="e-rfc" class="swal2-input" value="${escapeHtml(u.RFC||'')}" maxlength="13" style="width:100%"></label>
          <label>Teléfono<input id="e-tel" class="swal2-input" value="${escapeHtml(u.Telefono||'')}" maxlength="10" style="width:100%"></label>
         </div>`,
        showCancelButton:true,
        confirmButtonText:'Guardar',
        preConfirm: ()=>{
          const p = {
            id, nombre: $("#e-nombre").val().trim(), email: $("#e-email").val().trim().toLowerCase(),
            username: $("#e-username").val().trim(), Rol: $("#e-rol").val(),
            RFC: $("#e-rfc").val().trim().toUpperCase(), Telefono: $("#e-tel").val().replace(/\D+/g,'').slice(0,10)
          };
          if(!p.nombre || !p.email || !p.username) return Swal.showValidationMessage('Nombre, email y username son obligatorios');
          return p;
        }
      }).then(r=>{
        if(!r.isConfirmed) return;
        $.ajax({ url:API, method:'POST', contentType:'application/json; charset=utf-8', dataType:'json',
                 data: JSON.stringify({ action:'update', payload:r.value }) })
          .done(res=>{ if(res.ok){ Toast.fire({icon:'success',title:'Guardado'}); fetchLista(); } else { Swal.fire('Error',res.msg||'','error'); } })
          .fail(()=> Swal.fire('Error','No se pudo guardar','error'));
      });
    })
    .fail(()=> Swal.fire('Error','No se pudo cargar el usuario','error'));
}

function resetPwd(id){
  Swal.fire({title:'Resetear contraseña', text:'Se generará una contraseña temporal y se forzará el cambio al iniciar sesión.', showCancelButton:true, confirmButtonText:'Resetear'})
    .then(r=>{
      if(!r.isConfirmed) return;
      $.ajax({ url:API, method:'POST', contentType:'application/json; charset=utf-8', dataType:'json',
               data: JSON.stringify({ action:'reset_password', id }) })
       .done(res=>{
         if(res.ok){
           Swal.fire({icon:'success', title:'Contraseña reseteada', html:`Nueva temporal: <code>${escapeHtml(res.temp_password)}</code>`});
         }else{
           Swal.fire('Error', res.msg||'', 'error');
         }
       }).fail(()=> Swal.fire('Error','No se pudo resetear','error'));
    });
}

function toggleEstado(id, estado){
  $.ajax({ url:API, method:'POST', contentType:'application/json; charset=utf-8', dataType:'json',
           data: JSON.stringify({ action:'toggle_estado', id, estado }) })
   .done(res=>{ if(res.ok){ fetchLista(); Toast.fire({icon:'success', title:'Actualizado'}); } else { Swal.fire('Error',res.msg||'','error'); } })
   .fail(()=> Swal.fire('Error','No se pudo actualizar','error'));
}

// Init
fetchLista();
</script>
</body>
</html>

<?php
// garantias/comentarios.php
ini_set('session.cookie_httponly','1');
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off') { ini_set('session.cookie_secure','0'); } else { ini_set('session.cookie_secure','1'); }
session_name('GA'); session_start();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/db.php';

function jexit($arr, $code=200){ http_response_code($code); echo json_encode($arr); exit; }

$uid   = $_SESSION['user_id'] ?? null;
$rol   = $_SESSION['Rol'] ?? '';
$email = $_SESSION['Email'] ?? ($_SESSION['Correo'] ?? null);

$method = $_SERVER['REQUEST_METHOD'];

/* =========================
   GET: listar comentarios
   Soporta:
   - id_solicitud    (obligatorio)
   - after_id        (opcional, int) -> solo trae nuevos > after_id
   - timeout         (opcional, int) -> long-polling en segundos (m���x 25)
   ========================= */
if ($method === 'GET') {
  $id_solicitud = (int)($_GET['id_solicitud'] ?? 0);
  $afterId      = (int)($_GET['after_id'] ?? 0);
  $timeout      = max(0, min(25, (int)($_GET['timeout'] ?? 0))); // 0 = sin long-poll
  if ($id_solicitud<=0) jexit(['ok'=>false,'error'=>'BAD_REQUEST'],400);

  // Permiso: Admin ve todo; usuario normal solo si es due���o
  if ($rol === 'Admin') {
    $st = $pdo->prepare("SELECT id FROM garantia_solicitudes WHERE id=:id LIMIT 1");
    $st->execute([':id'=>$id_solicitud]);
  } else {
    $st = $pdo->prepare("SELECT id FROM garantia_solicitudes
                         WHERE id=:id AND (id_usuario=:uid OR email=:email) LIMIT 1");
    $st->execute([':id'=>$id_solicitud, ':uid'=>$uid, ':email'=>$email]);
  }
  if (!$st->fetch()) jexit(['ok'=>false,'error'=>'FORBIDDEN'],403);

  // Funci���n para ejecutar la consulta (reutilizada por long-poll)
  $fetch = function(int $after) use ($pdo, $id_solicitud){
    $sql = "SELECT id, id_usuario, nombre, rol, cuerpo, created_at
            FROM garantia_comentarios
            WHERE id_solicitud=:id";
    $params = [':id'=>$id_solicitud];
    if ($after > 0) { $sql .= " AND id > :after"; $params[':after'] = $after; }
    $sql .= " ORDER BY id ASC LIMIT 200";
    $q = $pdo->prepare($sql);
    $q->execute($params);
    $items = $q->fetchAll(PDO::FETCH_ASSOC);
    $last  = $after;
    if ($items) { $last = (int)end($items)['id']; }
    return [$items, $last];
  };

  // Long-polling simple (opcional)
  if ($timeout > 0 && $afterId > 0) {
    $start = microtime(true);
    do {
      [$items, $last] = $fetch($afterId);
      if ($items) jexit(['ok'=>true,'items'=>$items,'last_id'=>$last]);
      usleep(500_000); // 500 ms
    } while ((microtime(true) - $start) < $timeout);
    // timeout alcanzado sin nuevos
    jexit(['ok'=>true,'items'=>[],'last_id'=>$afterId]);
  }

  // Consulta normal
  [$items, $last] = $fetch($afterId);
  jexit(['ok'=>true,'items'=>$items,'last_id'=>$last]);
}

/* =========================
   POST: crear comentario
   ========================= */
if ($method === 'POST') {
  // CSRF
  $csrf = $_POST['csrf'] ?? '';
  if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    jexit(['ok'=>false,'error'=>'CSRF'],419);
  }

  $id_solicitud = (int)($_POST['id_solicitud'] ?? 0);
  $cuerpo = trim((string)($_POST['cuerpo'] ?? ''));
  if ($id_solicitud<=0 || $cuerpo==='') jexit(['ok'=>false,'error'=>'BAD_REQUEST'],400);
  if (!$uid && !$email) jexit(['ok'=>false,'error'=>'UNAUTH'],401);

  // Permiso
  if ($rol === 'Admin') {
    $st = $pdo->prepare("SELECT id FROM garantia_solicitudes WHERE id=:id LIMIT 1");
    $st->execute([':id'=>$id_solicitud]);
  } else {
    $st = $pdo->prepare("SELECT id FROM garantia_solicitudes
                         WHERE id=:id AND (id_usuario=:uid OR email=:email) LIMIT 1");
    $st->execute([':id'=>$id_solicitud, ':uid'=>$uid, ':email'=>$email]);
  }
  if (!$st->fetch()) jexit(['ok'=>false,'error'=>'FORBIDDEN'],403);

  $autorNombre = $_SESSION['username'] ?? ($_SESSION['Nombre'] ?? ($_SESSION['Email'] ?? 'Usuario'));
  $autorRol    = $rol ?: 'Usuario';

  // Inserta y fija created_at con NOW() (evita divergencias con PHP)
  $ins = $pdo->prepare("INSERT INTO garantia_comentarios
                        (id_solicitud, id_usuario, nombre, rol, cuerpo, created_at)
                        VALUES (:id_solicitud, :id_usuario, :nombre, :rol, :cuerpo, NOW())");
  $ins->execute([
    ':id_solicitud'=>$id_solicitud,
    ':id_usuario'=>$uid,
    ':nombre'=>$autorNombre,
    ':rol'=>$autorRol,
    ':cuerpo'=>$cuerpo
  ]);

  $newId = (int)$pdo->lastInsertId();
  // Lee la fila para devolver el created_at real de la BD
  $row = $pdo->prepare("SELECT id, id_usuario, nombre, rol, cuerpo, created_at
                        FROM garantia_comentarios WHERE id=:id LIMIT 1");
  $row->execute([':id'=>$newId]);
  $item = $row->fetch(PDO::FETCH_ASSOC);

  jexit(['ok'=>true,'item'=>$item]);
}

jexit(['ok'=>false,'error'=>'METHOD_NOT_ALLOWED'],405);

<?php
// devoluciones/comentarios.php
ini_set('session.cookie_httponly','1');
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off') { ini_set('session.cookie_secure','0'); } else { ini_set('session.cookie_secure','1'); }
session_name('GA'); session_start();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/db.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

function jexit($arr, $code=200){ http_response_code($code); echo json_encode($arr); exit; }

$uid       = $_SESSION['user_id'] ?? null;
$rol       = $_SESSION['Rol'] ?? 'Cliente';
$username  = $_SESSION['username'] ?? ($_SESSION['Email'] ?? 'Usuario');
$emailSess = $_SESSION['Email'] ?? null;

$method = $_SERVER['REQUEST_METHOD'];

// Toma id de GET o POST
$devId = (int)($_GET['id_devolucion'] ?? $_POST['id_devolucion'] ?? 0);
if ($devId <= 0) jexit(['ok'=>false,'error'=>'ID'], 400);

// Autorización: Admin ve todo; cliente solo su devolución (por user_id o por email de contacto)
if (($rol ?? '') !== 'Admin') {
  $chk = $pdo->prepare("SELECT 1 FROM devoluciones WHERE id=:id AND (user_id=:uid OR contacto_email=:email)");
  $chk->execute([':id'=>$devId, ':uid'=>$uid, ':email'=>$emailSess]);
  if (!$chk->fetchColumn()) jexit(['ok'=>false,'error'=>'PERM'], 403);
}

/* =========================
   GET: listar comentarios
   ========================= */
if ($method === 'GET') {
  $afterId = (int)($_GET['after_id'] ?? 0);
  $timeout = max(0, min(25, (int)($_GET['timeout'] ?? 0))); // 0 = sin long-poll

  // 🔓 Libera el candado de sesión (ya no la usaremos en este request)
  if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
  }

  // (opcional) asegura que el script no corte antes
  if ($timeout > 0 && function_exists('set_time_limit')) {
    @set_time_limit($timeout + 5);
  }
  ignore_user_abort(true); // si el cliente se va, no truena el bucle

  // Función consulta (reutilizable)
  $fetch = function(int $after) use ($pdo, $devId){
    $sql = "SELECT id, id_usuario, nombre, rol, cuerpo, created_at
            FROM devolucion_comentarios
            WHERE id_devolucion = :id";
    $params = [':id'=>$devId];
    if ($after > 0) { $sql .= " AND id > :after"; $params[':after'] = $after; }
    $sql .= " ORDER BY id ASC LIMIT 200";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $items = $st->fetchAll();
    $last  = $after;
    if ($items) { $last = (int)end($items)['id']; }
    return [$items, $last];
  };

  // Long-polling opcional
  if ($timeout > 0 && $afterId > 0) {
    $start = microtime(true);
    do {
      [$items, $last] = $fetch($afterId);
      if ($items) jexit(['ok'=>true, 'items'=>$items, 'last_id'=>$last]);
      usleep(500_000); // 500ms
    } while ((microtime(true) - $start) < $timeout);

    jexit(['ok'=>true, 'items'=>[], 'last_id'=>$afterId]);
  }

  // Consulta normal
  [$items, $last] = $fetch($afterId);
  jexit(['ok'=>true, 'items'=>$items, 'last_id'=>$last]);
}


/* =========================
   POST: crear comentario
   ========================= */
if ($method === 'POST') {
  // CSRF (si existe token en la sesión)
  if (!empty($_SESSION['csrf_token'])) {
    $csrf = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf)) jexit(['ok'=>false,'error'=>'CSRF'], 419);
  }

  $cuerpo = trim((string)($_POST['cuerpo'] ?? ''));
  if ($cuerpo === '' || mb_strlen($cuerpo) > 3000) jexit(['ok'=>false,'error'=>'TEXTO'], 422);

  $ins = $pdo->prepare("INSERT INTO devolucion_comentarios
                        (id_devolucion, id_usuario, nombre, rol, cuerpo, created_at)
                        VALUES (:id, :uid, :n, :r, :c, NOW())");
  $ins->execute([
    ':id'  => $devId,
    ':uid' => $uid,
    ':n'   => $username ?: 'Usuario',
    ':r'   => $rol ?: 'Usuario',
    ':c'   => $cuerpo,
  ]);

  $newId = (int)$pdo->lastInsertId();
  // Leer la fila para devolver el created_at real de la BD
  $row = $pdo->prepare("SELECT id, id_usuario, nombre, rol, cuerpo, created_at
                        FROM devolucion_comentarios WHERE id=:id LIMIT 1");
  $row->execute([':id'=>$newId]);
  $item = $row->fetch();

  jexit(['ok'=>true, 'item'=>$item]);
}

jexit(['ok'=>false, 'error'=>'METHOD'], 405);

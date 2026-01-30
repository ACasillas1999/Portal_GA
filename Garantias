<?php
// api_vendedores.php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');

try {
  require_once __DIR__ . '/../config.php';
  require_once __DIR__ . '/../app/db.php';
  require_once __DIR__ . '/../app/session_boot.php';
  require_login();

  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

  $suc = isset($_GET['sucursal']) ? trim((string)$_GET['sucursal']) : '';
  if ($suc === '') { echo json_encode(['ok'=>true, 'data'=>[]]); exit; }

  // Normaliza: minúsculas del front → mayúsculas de BD
  $map = [
    'deasa'=>'DEASA','tapatia'=>'TAPATIA','dimegsa'=>'DIMEGSA','iluminacion'=>'ELEITSA',
    'segsa'=>'SEGSA','fesa'=>'FESA','codi'=>'CODI','vallarta'=>'VALLARTA',
    'queretaro'=>'QUERETARO','gabsa'=>'GABSA','aiesa'=>'AIESA','ovalo'=>'OVALO'
  ];
  $sucursal = $map[strtolower($suc)] ?? mb_strtoupper($suc, 'UTF-8');

  $sql = "
    SELECT 
      uc.id_usuario AS id,
      COALESCE(NULLIF(uc.nombre_factura,''), NULLIF(uc.nombre_completo,'')) AS label,
      uc.rol, uc.sucursal
    FROM usuarios_cache uc
    WHERE TRIM(UPPER(uc.sucursal)) = TRIM(UPPER(:suc))
      AND (
        (uc.nombre_factura  IS NOT NULL AND uc.nombre_factura  <> '')
        OR (uc.nombre_completo IS NOT NULL AND uc.nombre_completo <> '')
      )
    ORDER BY 
      (uc.nombre_factura IS NULL OR uc.nombre_factura=''), uc.nombre_factura,
      uc.nombre_completo
    LIMIT 500
  ";
  $st = $pdo->prepare($sql);
  $st->execute([':suc'=>$sucursal]);
  $rows = $st->fetchAll();

  $data = array_map(fn($r)=>[
    'id'    => (int)$r['id'],
    'label' => (string)$r['label'],
    'rol'   => (string)($r['rol'] ?? ''),
    'suc'   => (string)($r['sucursal'] ?? ''),
  ], $rows ?: []);

  echo json_encode(['ok'=>true,'data'=>$data], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}

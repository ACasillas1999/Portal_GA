<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../Conexiones/Conexion.php'; // db()
require_once __DIR__ . '/../config.php';

// PHPMailer (ajusta la ruta a tu carpeta)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';


function sendMail(string $to, string $toName, string $subject, string $html, ?string $replyTo=null): array {
  $mail = new PHPMailer(true);
  try {
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
    if ($replyTo) $mail->addReplyTo($replyTo);
    $mail->addAddress($to, $toName);

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $html;

    $mail->send();
    return ['ok'=>true];
  } catch (\Throwable $e) {
    return ['ok'=>false, 'error'=>$e->getMessage()];
  }
}

function sendAdmins(string $subject, string $html): array {
  $errors = [];
  $okAny  = false;
  $list = explode(';', ADMIN_NOTIFY_LIST);
  foreach ($list as $addr) {
    $addr = trim($addr);
    if ($addr === '') continue;
    $r = sendMail($addr, 'Admin Portal', $subject, $html);
    if ($r['ok']) $okAny = true; else $errors[] = $addr.': '.$r['error'];
  }
  return $okAny ? ['ok'=>true] : ['ok'=>false, 'error'=>implode(' | ', $errors)];
}

try {
  $pdo = db();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $raw = file_get_contents('php://input');
  $in  = json_decode($raw, true) ?: [];

  $nombre = trim((string)($in['nombre'] ?? ''));
  $email  = strtolower(trim((string)($in['email'] ?? '')));
  $rfc    = strtoupper(trim((string)($in['RFC'] ?? '')));
  $tel    = preg_replace('/\D+/', '', (string)($in['Telefono'] ?? ''));
  $coment = trim((string)($in['comentario'] ?? ''));

  if ($nombre==='' || !filter_var($email, FILTER_VALIDATE_EMAIL) ||
      !preg_match('/^([A-ZÑ&]{3,4})\d{6}([A-Z0-9]{2,3})$/', $rfc) ||
      !preg_match('/^\d{10}$/', $tel)) {
    echo json_encode(['ok'=>false,'msg'=>'Datos inválidos']); exit;
  }

  // Crea tabla si no existe
  $pdo->exec("CREATE TABLE IF NOT EXISTS solicitudes_acceso (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    rfc CHAR(13) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    comentario TEXT NULL,
    estatus ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atendido_por INT NULL,
    atendido_en DATETIME NULL,
    notas_admin TEXT NULL,
    INDEX idx_email(email), INDEX idx_rfc(rfc), INDEX idx_estatus(estatus), INDEX idx_fecha(creado_en)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci");

  // Evita duplicados recientes
  $st = $pdo->prepare("SELECT id FROM solicitudes_acceso
    WHERE email=? AND estatus='pendiente' AND creado_en > DATE_SUB(NOW(), INTERVAL 7 DAY) LIMIT 1");
  $st->execute([$email]);
  if ($st->fetch()) {
    // Aún así mandamos acuse al usuario (su solicitud ya existe)
    $ackHtml = "<p>Hola <b>".htmlspecialchars($nombre,ENT_QUOTES,'UTF-8')."</b>,</p>
                <p>Ya tenemos una solicitud reciente asociada a <b>$email</b>. Nuestro equipo la está revisando.</p>
                <p>Gracias por tu paciencia.</p>";
    @sendMail($email, $nombre, "Recibimos tu solicitud", $ackHtml);
    echo json_encode(['ok'=>true, 'msg'=>'Ya tenemos tu solicitud reciente.']); exit;
  }

  // Inserta
  $ins = $pdo->prepare("INSERT INTO solicitudes_acceso(nombre,email,rfc,telefono,comentario) VALUES (?,?,?,?,?)");
  $ins->execute([$nombre, $email, $rfc, $tel, $coment]);
  $idSolicitud = (int)$pdo->lastInsertId();

  // Correo a admins
  $adminSubject = "[Portal Clientes] Nueva solicitud #$idSolicitud – $rfc – $email";
  $adminHtml = "
    <h3>Nueva solicitud de acceso</h3>
    <table cellpadding='6' cellspacing='0' border='0' style='border-collapse:collapse'>
      <tr><td><b>Folio</b></td><td>#{$idSolicitud}</td></tr>
      <tr><td><b>Nombre</b></td><td>".htmlspecialchars($nombre,ENT_QUOTES,'UTF-8')."</td></tr>
      <tr><td><b>Email</b></td><td>{$email}</td></tr>
      <tr><td><b>RFC</b></td><td>{$rfc}</td></tr>
      <tr><td><b>Teléfono</b></td><td>{$tel}</td></tr>
      <tr><td><b>Comentario</b></td><td>".nl2br(htmlspecialchars($coment,ENT_QUOTES,'UTF-8'))."</td></tr>
      <tr><td><b>Recibida</b></td><td>".date('Y-m-d H:i:s')."</td></tr>
    </table>
    <p>Accede al panel de solicitudes para <b>aprobar / rechazar</b> y, en su caso, enviar invitación.</p>";
  $adm = sendAdmins($adminSubject, $adminHtml);

  // Acuse al usuario
  $ackSubject = "Recibimos tu solicitud de acceso (folio #$idSolicitud)";
  $ackHtml = "
    <p>Hola <b>".htmlspecialchars($nombre,ENT_QUOTES,'UTF-8')."</b>,</p>
    <p>Recibimos tu solicitud de acceso al portal (folio <b>#{$idSolicitud}</b>).</p>
    <p>Validaremos tus datos y, de proceder, te enviaremos una <b>invitación</b> con las instrucciones para activar tu cuenta.</p>
    <p>Si no hiciste esta solicitud, ignora este mensaje.</p>
    <p>— Equipo GA</p>";
  $ack = sendMail($email, $nombre, $ackSubject, $ackHtml);

  // Armamos respuesta
  $msg = 'Solicitud registrada correctamente.';
  if (!$adm['ok']) $msg .= ' (Aviso a admins no se pudo enviar por correo)';
  if (!$ack['ok']) $msg .= ' (No pudimos enviar acuse a tu correo)';

  echo json_encode(['ok'=>true, 'msg'=>$msg, 'folio'=>$idSolicitud]);
} catch (\Throwable $e) {
  echo json_encode(['ok'=>false, 'msg'=>'Error del servidor', 'detail'=>$e->getMessage()]);
}

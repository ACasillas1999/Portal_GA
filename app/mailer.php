<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';


function plantillaResetHTML(string $nombre, string $resetUrl): string {
  $logoUrl = APP_URL . '/assets/img/LogoColor.GrupoAscencio.svg';
  return <<<HTML
<!doctype html><html lang="es"><head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>Restablecer contraseña</title>
</head>
<body style="margin:0;padding:0;background:#f5f7fb;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#24292e;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f5f7fb;padding:24px 12px;">
    <tr><td align="center">
      <table role="presentation" width="620" cellspacing="0" cellpadding="0" style="max-width:620px;width:100%;">
        <tr><td align="center" style="padding:6px 0 16px;">
          <img src="{$logoUrl}" width="210" alt="Grupo Ascencio" style="display:block;border:0;max-width:210px;">
        </td></tr>
        <tr><td style="background:#ffffff;border-radius:14px;padding:28px 24px;box-shadow:0 2px 14px rgba(0,0,0,0.06);">
          <h1 style="margin:0 0 8px;font-size:22px">Restablecer contraseña</h1>
          <p style="margin:0 0 16px">Hola <strong>{$nombre}</strong>,</p>
          <p style="margin:0 18px 18px 0">Recibimos una solicitud para restablecer tu contraseña. Haz clic en el botón para continuar (expira en 1 hora):</p>
          <p><a href="{$resetUrl}" style="display:inline-block;background:#ff8f3c;color:#111;text-decoration:none;font-weight:700;padding:12px 18px;border-radius:10px;">Crear nueva contraseña</a></p>
          <p style="margin:18px 0 8px;font-size:12px">Si el botón no funciona, copia este enlace en tu navegador:</p>
          <p style="word-break:break-all;font-size:12px"><a href="{$resetUrl}" style="color:#2563eb;text-decoration:none">{$resetUrl}</a></p>
          <p style="margin:18px 0 0;font-size:12px;color:#5a6473">Si no solicitaste este cambio, ignora este correo.</p>
        </td></tr>
        <tr><td align="center" style="padding:14px 8px;color:#8a94a6;font-size:12px;">
          © {date('Y')} Grupo Ascencio
        </td></tr>
      </table>
    </td></tr>
  </table>
</body></html>
HTML;
}

function enviarCorreoReset(string $to, string $name, string $resetUrl): array {
  try {
    $mail = new PHPMailer(true);
    $mail->SMTPDebug = 2;     // ← Ponlo aquí también

    $mail->CharSet  = 'UTF-8';
    $mail->Encoding = 'base64';

    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->Port       = SMTP_PORT;
    if (strtolower(SMTP_SECURE) === 'ssl') {
      $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } else {
      $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }
    $smtpOpts = ['ssl' => ['verify_peer'=>true,'verify_peer_name'=>true,'allow_self_signed'=>false]];
    if (is_file(CAFILE_PATH)) { $smtpOpts['ssl']['cafile'] = CAFILE_PATH; }
    $mail->SMTPOptions = $smtpOpts;

    $mail->setFrom(SMTP_FROM_EMAIL, 'Portal GA · Grupo Ascencio');
    $mail->addAddress($to, $name);

    $mail->Subject = 'Restablece tu contraseña — Grupo Ascencio';
    $mail->isHTML(true);
    $mail->Body    = plantillaResetHTML($name, $resetUrl);
    $mail->AltBody = "Hola {$name},\n\nPara restablecer tu contraseña usa este enlace (expira en 1 hora):\n{$resetUrl}\n\nSi no fuiste tú, ignora este mensaje.";

    $mail->send();
    return ['ok'=>true];
  } catch (\Throwable $e) {
    return ['ok'=>false,'error'=>$e->getMessage()];
  }
}


/* --------- plantilla HTML bonita --------- */
function plantillaVerificacionHTML(string $nombre, string $verifyUrl): string {
  $preheader = 'Confirma tu correo para activar tu cuenta.';
$logoUrl = APP_URL . '/assets/img/logo_colores.png';  // https://.../portal_ga/assets/img/logo_colores.png
  
  return <<<HTML
<!doctype html>
<html lang="es">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<title>Verifica tu cuenta</title>
<style>
@media (prefers-color-scheme: dark) {
  .card { background:#121621 !important; color:#f3f5f9 !important; }
  .muted { color:#c6cbd2 !important; }
  .btn   { background:#ff8f3c !important; color:#111 !important; }
  a, a:hover { color:#ffb26e !important; }
}
</style>
</head>
<body style="margin:0;padding:0;background:#f5f7fb;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#24292e;">
  <div style="display:none;max-height:0;overflow:hidden;opacity:0;">{$preheader} &#847; &#847; &#847;</div>
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f5f7fb;padding:24px 12px;">
    <tr><td align="center">
      <table role="presentation" width="620" cellspacing="0" cellpadding="0" style="max-width:620px;width:100%;">
        <tr>
          <td align="center" style="padding:6px 0 16px;">
            <img src="{$logoUrl}" width="210" alt="Grupo Ascencio" style="display:block;border:0;max-width:210px;">
          </td>
        </tr>
        <tr><td class="card" style="background:#ffffff;border-radius:14px;padding:28px 24px;box-shadow:0 2px 14px rgba(0,0,0,0.06);">
          <h1 style="margin:0 0 8px;font-size:22px;line-height:1.25;">Verifica tu correo</h1>
          <p class="muted" style="margin:0 0 16px;color:#5a6473;font-size:15px;line-height:1.55;">
            Hola <strong>{$nombre}</strong>,
          </p>
          <p style="margin:0 0 18px;font-size:15px;line-height:1.6;">
            Para activar tu cuenta en <strong>Grupo Ascencio</strong>, confirma tu correo dando clic en el botón:
          </p>
          <table role="presentation" cellspacing="0" cellpadding="0" style="margin:18px 0 24px;"><tr><td>
            <a class="btn" href="{$verifyUrl}" style="display:inline-block;background:#ff8f3c;color:#111;text-decoration:none;font-weight:700;padding:12px 18px;border-radius:10px;">
              Verificar correo
            </a>
          </td></tr></table>
          <p class="muted" style="margin:0 0 12px;color:#5a6473;font-size:13px;line-height:1.5;">
            El enlace expira en <strong>24 horas</strong>. Si tú no solicitaste esta cuenta, puedes ignorar este correo.
          </p>
          <hr style="border:none;border-top:1px solid #e6e9f0;margin:18px 0;">
          <p style="margin:0 0 8px;font-size:13px;line-height:1.6;">Si el botón no funciona, copia y pega este enlace en tu navegador:</p>
          <p style="word-break:break-all;font-size:12px;line-height:1.5;">
            <a href="{$verifyUrl}" style="color:#2563eb;text-decoration:none;">{$verifyUrl}</a>
          </p>
          <p class="muted" style="margin:18px 0 0;color:#5a6473;font-size:12px;line-height:1.5;">
            ¿Necesitas ayuda? Responde a este correo o contáctanos en soporte.
          </p>
        </td></tr>
        <tr><td align="center" style="padding:14px 8px;color:#8a94a6;font-size:12px;">
          ©Grupo Ascencio. Todos los derechos reservados.
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
}

/* --------- envío --------- */
function enviarCorreoVerificacion(string $to, string $name, string $verifyUrl): array {
  try {
    $mail = new PHPMailer(true);             // 1) CREA el objeto
        $mail->SMTPDebug = 2;  // <------------------- AQUI

    $mail->CharSet  = 'UTF-8';               // 2) LUEGO asigna propiedades
    $mail->Encoding = 'base64';

    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->Port       = SMTP_PORT;

    if (strtolower(SMTP_SECURE) === 'ssl') {
      $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;    // 465
    } else {
      $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // 587
    }

    // Verificación TLS
    $smtpOpts = [
      'ssl' => [
        'verify_peer'       => true,
        'verify_peer_name'  => true,
        'allow_self_signed' => false,
      ],
    ];
    if (is_file(CAFILE_PATH)) {
      $smtpOpts['ssl']['cafile'] = CAFILE_PATH;
    }
    $mail->SMTPOptions = $smtpOpts;

    // Remitente y destinatario
    $mail->setFrom(SMTP_FROM_EMAIL, 'Portal GA · Grupo Ascencio');
    $mail->addAddress($to, $name);

    // Contenido
    $mail->Subject = 'Verifica tu cuenta — Grupo Ascencio';
    $mail->isHTML(true);
    $mail->Body    = plantillaVerificacionHTML($name, $verifyUrl);
    $mail->AltBody = "Hola {$name},\n\nPara activar tu cuenta, verifica tu correo con este enlace (expira en 24h):\n{$verifyUrl}\n\nSi no fuiste tú, ignora este mensaje.";

    $mail->send();
    return ['ok' => true];

  } catch (\Throwable $e) {
    return ['ok' => false, 'error' => $e->getMessage()];
  }
}

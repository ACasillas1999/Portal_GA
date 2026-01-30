<?php
require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    $mail->SMTPDebug = 2; 
    $mail->Debugoutput = 'html';

   $mail->isSMTP();
$mail->Host       = 'vps.facturacionascencio.com.mx'; // o el host que diga cPanel
$mail->SMTPAuth   = true;
$mail->AuthType   = 'LOGIN'; // opcional para forzar LOGIN
$mail->Username   = 'j.rubio@grupoascencio.com.mx';
$mail->Password   = 'Julioo13hweg*';
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // ✅ correcto
$mail->Port       = 587;
$mail->setFrom('j.rubio@grupoascencio.com.mx', 'Portal GA'); // debe coincidir

    $mail->addAddress('rubio.leonel1@gmail.com');

    $mail->isHTML(true);
    $mail->Subject = 'Prueba de PHPMailer';
    $mail->Body    = '<h1>¡Funciona PHPMailer!</h1><p>Este es un correo de prueba.</p>';
    $mail->AltBody = '¡Funciona PHPMailer!';
$mail->SMTPOptions = [
  'ssl' => [
    'verify_peer'       => false,
    'verify_peer_name'  => false,
    'allow_self_signed' => true,
  ],
];
    $mail->send();
    echo '✅ Correo enviado correctamente';
} catch (Exception $e) {
    echo "❌ No se pudo enviar. Error: {$mail->ErrorInfo}";
}

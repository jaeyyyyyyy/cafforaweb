<?php
declare(strict_types=1);

require_once __DIR__  . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendOtpMail(string $toEmail, string $toName, string $otp): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'cafforaproject@gmail.com';
        $mail->Password   = 'fncaktuvkugxsorz';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // 🔽 ini dua baris bikin gak terlalu lama nunggu
        $mail->Timeout = 5;           // detik
        $mail->SMTPKeepAlive = false;

        $mail->setFrom('cafforaproject@gmail.com', 'Caffora');
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = 'Kode OTP Verifikasi Caffora';
        $mail->Body = "
            <p>Halo <strong>".htmlspecialchars($toName)."</strong>,</p>
            <p>Kode OTP Anda:</p>
            <h2 style='letter-spacing:3px;'>".htmlspecialchars($otp)."</h2>
            <p>Berlaku 5 menit. Jangan bagikan ke siapa pun.</p>
        ";
        $mail->AltBody = "Kode OTP Anda: {$otp} (berlaku 5 menit)";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mailer Error: ' . $mail->ErrorInfo);
        return false;
    }
}

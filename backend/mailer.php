<?php
// backend/mailer.php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Kirim OTP via Gmail SMTP (App Password).
 * - From, Sender, Reply-To semuanya @gmail.com (tidak pakai domain lain).
 * - TIDAK membuat Message-ID custom (biarkan Gmail yang terbitkan).
 * - Tambah header transaksional agar tidak dianggap promosi/auto-reply loop.
 * - HTML minimal + plain text, tanpa tautan/attachment.
 */
function sendOtpMail(string $toEmail, string $toName, string $otp): bool {
    $mail = new PHPMailer(true);
    try {
        /* ===== SMTP GMAIL ===== */
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->Port       = 587;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->SMTPAuth   = true;

        // Kredensial Gmail (pakai App Password)
        $mail->Username   = 'cafforaproject@gmail.com';
        $mail->Password   = getenv('GMAIL_APP_PASSWORD') ?: 'fncaktuvkugxsorz'; // ganti ke ENV di server

        // Koneksi
        $mail->Timeout       = 12;   // jangan terlalu kecil agar tidak timeout → dianggap gagal/resent
        $mail->SMTPKeepAlive = false;
        $mail->SMTPDebug     = 0;    // 0 = produksi

        /* ===== IDENTITAS (semua @gmail.com) ===== */
        // Envelope-From (Return-Path) & From sebaiknya sama → bantu SPF/DMARC alignment
        $mail->Sender  = 'cafforaproject@gmail.com';
        $mail->setFrom('cafforaproject@gmail.com', 'Caffora');
        $mail->addReplyTo('cafforaproject@gmail.com', 'Caffora');

        // Penerima
        $mail->addAddress($toEmail, $toName);

        // Charset & encoding
        $mail->CharSet  = 'UTF-8';
        $mail->Encoding = 'base64';

        // ❌ JANGAN set $mail->MessageID sendiri → biarkan Gmail yang buat
        // ❌ Jangan pakai domain lain di header mana pun

        // Header transaksional ringan (beberapa filter menghargai ini)
        $mail->addCustomHeader('Auto-Submitted', 'auto-generated');
        $mail->addCustomHeader('X-Auto-Response-Suppress', 'All');

        /* ===== KONTEN ===== */
        $subject = 'Kode OTP Verifikasi Caffora';
        $safeName = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');
        $safeOtp  = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');

        $preheader = 'Kode OTP Anda untuk verifikasi akun Caffora. Berlaku 5 menit.';

        $html = <<<HTML
<!doctype html>
<html>
  <body style="margin:0;padding:0;background:#ffffff;font-family:Arial,Roboto,Segoe UI,Helvetica,sans-serif;color:#222;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">{$preheader}</div>
    <div style="max-width:560px;margin:24px auto;padding:24px;border:1px solid #f2e7b3;border-radius:12px">
      <h2 style="margin:0 0 8px 0;color:#333;">Verifikasi Akun Caffora</h2>
      <p style="margin:0 0 12px 0;">Halo <strong>{$safeName}</strong>,</p>
      <p style="margin:0 0 6px 0;">Kode OTP Anda:</p>
      <div style="font-size:26px;font-weight:700;letter-spacing:6px;color:#333;margin:6px 0 14px 0;">{$safeOtp}</div>
      <p style="margin:0 0 10px 0;">Kode berlaku <strong>5 menit</strong>. Jangan bagikan kepada siapa pun.</p>
      <hr style="border:none;border-top:1px solid #eee;margin:18px 0;">
      <p style="font-size:12px;color:#666;margin:0;">Anda menerima email ini karena melakukan pendaftaran/permintaan OTP di Caffora.</p>
    </div>
  </body>
</html>
HTML;

        $plain = "Verifikasi Akun Caffora\n\n".
                 "Halo {$toName},\n\n".
                 "Kode OTP Anda: {$otp}\n".
                 "Berlaku 5 menit. Jangan bagikan kepada siapa pun.\n\n".
                 "Anda menerima email ini karena melakukan pendaftaran/permintaan OTP di Caffora.";

        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body    = $html;
        $mail->AltBody = $plain;

        /* ===== KIRIM ===== */
        return $mail->send();
    } catch (Exception $e) {
        error_log('Mailer Error: ' . $mail->ErrorInfo);
        return false;
    }
}

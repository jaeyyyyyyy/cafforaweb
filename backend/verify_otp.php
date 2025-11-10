<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mailer.php';

function is_ajax(): bool {
    return (
        (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && str_contains(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json'))
    );
}
function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function redirect(string $to): void { header('Location: '.$to); exit; }

session_start();

const RESEND_COOLDOWN = 300; // 5 menit

/* =============== RESEND OTP (GET ?resend=1) =============== */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['resend'])) {
    $email = trim($_GET['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return is_ajax() ? json_response(['ok'=>false,'error'=>'invalid_email'],400)
                         : redirect('/public/verify_otp.html?err=invalid');
    }

    $stmt = $conn->prepare('SELECT id, name, status, otp, otp_expires_at, otp_sent_at FROM users WHERE email=? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        return is_ajax() ? json_response(['ok'=>false,'error'=>'not_found'],404)
                         : redirect('/public/verify_otp.html?err=notfound');
    }
    if (($user['status'] ?? '') !== 'pending') {
        return is_ajax() ? json_response(['ok'=>false,'error'=>'already_active'],409)
                         : redirect('/public/login.html?msg=already_active');
    }

    $nowTs = time();
    $sentTs = !empty($user['otp_sent_at']) ? strtotime((string)$user['otp_sent_at']) : 0;
    $wait  = RESEND_COOLDOWN - max(0, $nowTs - $sentTs);
    if ($sentTs && $wait > 0) {
        return is_ajax() ? json_response(['ok'=>false,'error'=>'cooldown','wait'=>$wait],429)
                         : redirect('/public/verify_otp.html?email='.urlencode($email).'&err=cooldown');
    }

    // REUSE kalau masih valid
    $otp = (string)($user['otp'] ?? '');
    $valid = false;
    if (!empty($user['otp_expires_at'])) {
        $valid = (new DateTime() < new DateTime($user['otp_expires_at']));
    }
    if (!$valid || $otp === '') {
        $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = (new DateTime('+5 minutes'))->format('Y-m-d H:i:s');
        $stmt = $conn->prepare('UPDATE users SET otp=?, otp_expires_at=? WHERE id=?');
        $stmt->bind_param('ssi', $otp, $expires, $user['id']);
        $stmt->execute();
        $stmt->close();
    }

    // update sent_at selalu
    $sentAt = (new DateTime())->format('Y-m-d H:i:s');
    $stmt = $conn->prepare('UPDATE users SET otp_sent_at=? WHERE id=?');
    $stmt->bind_param('si', $sentAt, $user['id']);
    $stmt->execute();
    $stmt->close();

    if (!sendOtpMail($email, $user['name'] ?? '', $otp)) {
        return is_ajax() ? json_response(['ok'=>false,'error'=>'mail_failed'],500)
                         : redirect('/public/verify_otp.html?email='.urlencode($email).'&err=mail');
    }

    $_SESSION['otp_resend_ts_'.md5($email)] = $nowTs;

    return is_ajax() ? json_response(['ok'=>true,'message'=>$valid ? 'resent_reuse' : 'resent'])
                     : redirect('/public/verify_otp.html?email='.urlencode($email).'&msg=resent');
}

/* ====================== VERIFY OTP (POST) ====================== */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    return is_ajax() ? json_response(['ok'=>false,'error'=>'method'],405)
                     : redirect('/public/verify_otp.html');
}

$email = trim($_POST['email'] ?? '');
$otp   = trim($_POST['otp'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $otp === '' || strlen($otp) !== 6 || !ctype_digit($otp)) {
    return is_ajax() ? json_response(['ok'=>false,'error'=>'invalid_input'],400)
                     : redirect('/public/verify_otp.html?err=invalid');
}

$stmt = $conn->prepare('SELECT id, otp, otp_expires_at, status FROM users WHERE email=? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    return is_ajax() ? json_response(['ok'=>false,'error'=>'not_found'],404)
                     : redirect('/public/verify_otp.html?err=notfound');
}
if (($user['status'] ?? '') !== 'pending') {
    return is_ajax() ? json_response(['ok'=>false,'error'=>'already_active'],409)
                     : redirect('/public/login.html?msg=already_active');
}
if (!hash_equals((string)$user['otp'], $otp)) {
    return is_ajax() ? json_response(['ok'=>false,'error'=>'wrong'],400)
                     : redirect('/public/verify_otp.html?err=wrong&email='.urlencode($email));
}
$expiresAt = $user['otp_expires_at'] ? new DateTime($user['otp_expires_at']) : null;
if (!$expiresAt || (new DateTime()) > $expiresAt) {
    return is_ajax() ? json_response(['ok'=>false,'error'=>'expired'],400)
                     : redirect('/public/verify_otp.html?err=expired&email='.urlencode($email));
}

// sukses → aktifkan + bersihkan field otp
$stmt = $conn->prepare('UPDATE users SET status="active", otp=NULL, otp_expires_at=NULL, otp_sent_at=NULL WHERE id=?');
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$stmt->close();

return is_ajax()
    ? json_response(['ok'=>true,'message'=>'verified','redirect'=>'/public/login.html?msg=verified'])
    : redirect('/public/login.html?msg=verified');

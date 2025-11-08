<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mailer.php'; // harus ada sendOtpMail($email, $name, $otp)

// ---------- Util ----------
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

// ---------- RATE LIMIT resend OTP per session ----------
session_start();
const RESEND_COOLDOWN = 300; //  (after 5 menit bisa resend lagi)

// ==== HANDLE RESEND (GET) ====
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['resend'])) {
    $email = trim($_GET['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        if (is_ajax()) json_response(['ok' => false, 'error' => 'invalid_email'], 400);
        redirect('/public/verify_otp.html?err=invalid');
    }

    $key = 'otp_resend_ts_' . md5($email);
    $now = time();
    if (isset($_SESSION[$key]) && ($now - (int)$_SESSION[$key]) < RESEND_COOLDOWN) {
        $wait = RESEND_COOLDOWN - ($now - (int)$_SESSION[$key]);
        if (is_ajax()) json_response(['ok' => false, 'error' => 'cooldown', 'wait' => $wait], 429);
        redirect('/public/verify_otp.html?email=' . urlencode($email) . '&err=cooldown');
    }

    // Cari user pending
    $stmt = $conn->prepare('SELECT id, name, status FROM users WHERE email=? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        if (is_ajax()) json_response(['ok' => false, 'error' => 'not_found'], 404);
        redirect('/public/verify_otp.html?err=notfound');
    }
    if (($user['status'] ?? '') !== 'pending') {
        if (is_ajax()) json_response(['ok' => false, 'error' => 'already_active'], 409);
        redirect('/public/login.html?msg=already_active');
    }

    // Generate OTP baru
    $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = (new DateTime('+5 minutes'))->format('Y-m-d H:i:s');

    // Simpan
    $stmt = $conn->prepare('UPDATE users SET otp=?, otp_expires_at=? WHERE id=?');
    $stmt->bind_param('ssi', $otp, $expires, $user['id']);
    $stmt->execute();
    $stmt->close();

    // Kirim email (cek bool)
    if (!sendOtpMail($email, $user['name'] ?? '', $otp)) {
        if (is_ajax()) json_response(['ok' => false, 'error' => 'mail_failed'], 500);
        redirect('/public/verify_otp.html?email=' . urlencode($email) . '&err=mail');
    }

    $_SESSION[$key] = $now;

    if (is_ajax()) {
        json_response(['ok' => true, 'message' => 'resent']);
    } else {
        redirect('/public/verify_otp.html?email=' . urlencode($email) . '&msg=resent');
    }
    exit;
}

// ==== HANDLE VERIFY (POST) ====
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (is_ajax()) json_response(['ok' => false, 'error' => 'method'], 405);
    redirect('/public/verify_otp.html');
    exit;
}

$email = trim($_POST['email'] ?? '');
$otp   = trim($_POST['otp'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $otp === '' || strlen($otp) !== 6 || !ctype_digit($otp)) {
    if (is_ajax()) json_response(['ok' => false, 'error' => 'invalid_input'], 400);
    redirect('/public/verify_otp.html?err=invalid');
}

$stmt = $conn->prepare('SELECT id, otp, otp_expires_at, status FROM users WHERE email=? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    if (is_ajax()) json_response(['ok' => false, 'error' => 'not_found'], 404);
    redirect('/public/verify_otp.html?err=notfound');
}
if (($user['status'] ?? '') !== 'pending') {
    if (is_ajax()) json_response(['ok' => false, 'error' => 'already_active'], 409);
    redirect('/public/login.html?msg=already_active');
}
if (!hash_equals((string)$user['otp'], $otp)) {
    if (is_ajax()) json_response(['ok' => false, 'error' => 'wrong'], 400);
    redirect('/public/verify_otp.html?err=wrong&email=' . urlencode($email));
}
$expiresAt = $user['otp_expires_at'] ? new DateTime($user['otp_expires_at']) : null;
if (!$expiresAt || (new DateTime()) > $expiresAt) {
    if (is_ajax()) json_response(['ok' => false, 'error' => 'expired'], 400);
    redirect('/public/verify_otp.html?err=expired&email=' . urlencode($email));
}

// Update status
$stmt = $conn->prepare('UPDATE users SET status="active", otp=NULL, otp_expires_at=NULL WHERE id=?');
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$stmt->close();

if (is_ajax()) {
    json_response(['ok' => true, 'message' => 'verified', 'redirect' => '/public/login.html?msg=verified']);
}
redirect('/public/login.html?msg=verified');
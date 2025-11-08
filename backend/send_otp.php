<?php
// backend/send_otp.php
declare(strict_types=1);

@ini_set('display_errors','0');
@ini_set('log_errors','1');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mailer.php'; // harus ada function: sendOtpMail(string $email, string $name, string $otp): bool
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

header('Content-Type: application/json; charset=utf-8');

const RESEND_COOLDOWN = 300; // 5 menit

// ===== Helpers =====
function json_out(array $data, int $code = 200): void {
  http_response_code($code);
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}
function cooldown_key(string $email): string {
  return 'otp_resend_ts_' . md5(strtolower($email));
}

// ===== Ambil email dari query / session =====
$email = trim((string)($_GET['email'] ?? ''));
if ($email === '' && !empty($_SESSION['pending_email'])) {
  $email = (string)$_SESSION['pending_email'];
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  json_out(['ok' => false, 'error' => 'invalid_email'], 400);
}

// ===== Cek koneksi DB =====
if (!isset($conn) || !($conn instanceof mysqli)) {
  error_log('SEND_OTP: $conn tidak siap');
  json_out(['ok' => false, 'error' => 'server'], 500);
}
@$conn->set_charset('utf8mb4');

// ===== Rate limit per session + email =====
$key = cooldown_key($email);
$now = time();
if (isset($_SESSION[$key]) && ($now - (int)$_SESSION[$key]) < RESEND_COOLDOWN) {
  $wait = RESEND_COOLDOWN - ($now - (int)$_SESSION[$key]);
  json_out(['ok' => false, 'error' => 'cooldown', 'wait' => $wait], 429);
}

// ===== Ambil user pending =====
$stmt = $conn->prepare('SELECT id, name, status, otp, otp_expires_at FROM users WHERE email=? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$res  = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$user) {
  json_out(['ok' => false, 'error' => 'not_found'], 404);
}
if (($user['status'] ?? '') !== 'pending') {
  json_out(['ok' => false, 'error' => 'already_active'], 409);
}

// ===== Putuskan pakai OTP lama atau buat baru jika tidak ada / expired =====
$needNew = false;
$otp      = (string)($user['otp'] ?? '');
$expiresS = (string)($user['otp_expires_at'] ?? '');

$expiresAt = $expiresS ? new DateTime($expiresS) : null;
if ($otp === '' || !$expiresAt || (new DateTime()) > $expiresAt) {
  $needNew = true;
}

if ($needNew) {
  $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
  $newExpires = (new DateTime('+5 minutes'))->format('Y-m-d H:i:s');

  $stmt = $conn->prepare('UPDATE users SET otp=?, otp_expires_at=? WHERE id=?');
  if (!$stmt) {
    error_log('SEND_OTP: prepare update gagal: ' . $conn->error);
    json_out(['ok' => false, 'error' => 'server'], 500);
  }
  $stmt->bind_param('ssi', $otp, $newExpires, $user['id']);
  if (!$stmt->execute()) {
    error_log('SEND_OTP: execute update gagal: ' . $conn->error);
    $stmt->close();
    json_out(['ok' => false, 'error' => 'server'], 500);
  }
  $stmt->close();
}

// ===== Kirim email =====
$name = (string)($user['name'] ?? '');
$sent = false;
try {
  $sent = sendOtpMail($email, $name, $otp);
} catch (Throwable $e) {
  error_log('SEND_OTP: mailer exception: ' . $e->getMessage());
}

if (!$sent) {
  json_out(['ok' => false, 'error' => 'mail_failed'], 500);
}

// set timestamp cooldown setelah kirim sukses
$_SESSION[$key] = $now;

// Sukses
json_out(['ok' => true, 'message' => 'sent']);

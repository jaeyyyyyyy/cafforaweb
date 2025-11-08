<?php
// backend/register.php
declare(strict_types=1);

@ini_set('display_errors','0');
@ini_set('log_errors','1');

require_once __DIR__ . '/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/* ---------- Helpers ---------- */
if (!function_exists('is_json_request')) {
  function is_json_request(): bool {
    $ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
    return stripos((string)$ct,'application/json') !== false
        || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');
  }
}
if (!function_exists('json_out')) {
  function json_out(array $payload,int $code=200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    exit;
  }
}
if (!function_exists('safe_path')) {
  function safe_path(string $p): string {
    $p = '/' . ltrim($p,'/');
    return preg_match('~https?://~i',$p) ? '/' : $p;
  }
}
if (!function_exists('redirect')) {
  function redirect(string $path): void {
    $p = safe_path($path);
    $base = defined('BASE_URL') ? rtrim(BASE_URL,'/') : '';

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')==='https');
    $scheme = $isHttps ? 'https://' : 'http://';
    $host   = $_SERVER['HTTP_HOST'] ?? '';

    header('Location: '.($base ?: $scheme.$host).$p);
    exit;
  }
}

/* ---------- Method guard ---------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  redirect('/register');
}

/* ---------- Ambil input ---------- */
$name     = trim((string)($_POST['name'] ?? ''));
$email    = trim((string)($_POST['email'] ?? ''));
$password = trim((string)($_POST['password'] ?? ''));
$confirm  = trim((string)($_POST['confirm_password'] ?? ''));

/* ---------- Validasi ---------- */
$err = '';
if ($name==='' || $email==='' || $password==='' || $confirm==='')      $err='Lengkapi semua field.';
elseif (!filter_var($email,FILTER_VALIDATE_EMAIL))                      $err='Format email tidak valid.';
elseif (mb_strlen($password) < 6)                                      $err='Password minimal 6 karakter.';
elseif ($password !== $confirm)                                       $err='Konfirmasi password tidak sama.';

if ($err !== '') {
  if (is_json_request()) json_out(['status'=>'error','message'=>$err],400);
  redirect('/register?err='.urlencode($err));
}

/* ---------- DB ---------- */
if (!isset($conn) || !($conn instanceof mysqli)) {
  error_log('REGISTER: koneksi DB null');
  if (is_json_request()) json_out(['status'=>'error','message'=>'Server DB tidak siap'],500);
  redirect('/register?err='.urlencode('Server DB tidak siap'));
}

@$conn->set_charset('utf8mb4');

/* ---------- Cek email exist ---------- */
$stmt = $conn->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
$stmt->bind_param('s',$email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows>0) {
  $stmt->close();
  if (is_json_request()) json_out(['status'=>'exists','message'=>'Email sudah terdaftar'],409);
  redirect('/login?msg=exists');
}
$stmt->close();

/* ---------- Insert user pending ---------- */
$otp      = str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);
$expires  = (new DateTime('+5 minutes'))->format('Y-m-d H:i:s');
$hash     = password_hash($password,PASSWORD_DEFAULT);

$stmt = $conn->prepare('
  INSERT INTO users (name,email,password,status,role,otp,otp_expires_at)
  VALUES (?,?,?,"pending","customer",?,?)
');
if (!$stmt) {
  error_log('REGISTER: prepare gagal '.$conn->error);
  if (is_json_request()) json_out(['status'=>'error','message'=>'Register sementara tidak tersedia'],500);
  redirect('/register?err='.urlencode('Register sementara tidak tersedia'));
}
$stmt->bind_param('sssss',$name,$email,$hash,$otp,$expires);
if(!$stmt->execute()) {
  if((int)$conn->errno===1062) {
    $stmt->close();
    if (is_json_request()) json_out(['status'=>'exists'],409);
    redirect('/login?msg=exists');
  }
  error_log('REGISTER: exec gagal '.$conn->error);
  $stmt->close();
  if (is_json_request()) json_out(['status'=>'error','message'=>'Gagal membuat akun'],500);
  redirect('/register?err='.urlencode('Gagal membuat akun'));
}
$stmt->close();

/* ---------- Simpan email pending ---------- */
$_SESSION['pending_email']=$email;

/* ---------- Beri response ---------- */
if (is_json_request()) {
  json_out(['status'=>'ok','redirect'=>'/verify_otp','email'=>$email]);
}

redirect('/verify_otp?email='.urlencode($email));

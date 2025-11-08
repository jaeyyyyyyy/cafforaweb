<?php
// backend/login.php
declare(strict_types=1);

@ini_set('display_errors','0');
@ini_set('log_errors','1');

require_once __DIR__.'/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/* ---------- Utils ---------- */
if (!function_exists('is_json_request')) {
  function is_json_request(): bool {
    $ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
    return stripos((string)$ct,'application/json') !== false
        || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');
  }
}
if (!function_exists('json_out')) {
  function json_out(array $payload, int $code=200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    exit;
  }
}

/* ---------- Method guard ---------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  if (is_json_request()) json_out(['status'=>'error','message'=>'Method not allowed'],405);
  redirect('/login'); // clean URL
}

/* ---------- Input ---------- */
$identity = trim((string)($_POST['identity'] ?? $_POST['email'] ?? ''));
$password = trim((string)($_POST['password'] ?? ''));
$remember = !empty($_POST['remember']);

if (is_json_request()) {
  $raw  = file_get_contents('php://input') ?: '';
  $data = json_decode($raw,true) ?: [];
  $identity = trim((string)($data['email'] ?? $data['identity'] ?? $identity));
  $password = trim((string)($data['password'] ?? $password));
  $remember = (bool)($data['remember'] ?? $remember);
}

if ($identity==='' || $password==='') {
  if (is_json_request()) json_out(['status'=>'error','message'=>'Email/Username dan password wajib diisi.'],400);
  redirect('/login?err='.urlencode('Email/Username dan password wajib diisi.'));
}

/* ---------- DB ---------- */
if (!isset($conn) || !($conn instanceof mysqli)) {
  error_log('LOGIN: $conn tidak siap');
  if (is_json_request()) json_out(['status'=>'error','message'=>'Server database tidak siap.'],500);
  redirect('/login?err='.urlencode('Server database tidak siap.'));
}
@mysqli_report(MYSQLI_REPORT_OFF);
@$conn->set_charset('utf8mb4');

/* ---------- Query user ---------- */
$stmt = $conn->prepare('SELECT id,name,email,password,status,role FROM users WHERE email=? OR name=? LIMIT 1');
if (!$stmt) {
  error_log('LOGIN: prepare gagal: '.$conn->error);
  if (is_json_request()) json_out(['status'=>'error','message'=>'Login sementara tidak tersedia.'],500);
  redirect('/login?err='.urlencode('Login sementara tidak tersedia.'));
}
$stmt->bind_param('ss',$identity,$identity);
$stmt->execute();
$res  = $stmt->get_result();
$user = $res? $res->fetch_assoc() : null;
$stmt->close();

if (!$user) {
  if (is_json_request()) json_out(['status'=>'error','message'=>'Akun tidak ditemukan.'],404);
  redirect('/login?err='.urlencode('Akun tidak ditemukan.'));
}

/* ---------- Status ---------- */
if (($user['status'] ?? 'pending') !== 'active') {
  if (is_json_request()) {
    json_out(['status'=>'need_verification','message'=>'Akun belum aktif. Silakan verifikasi OTP.','email'=>(string)$user['email']]);
  }
  redirect('/verify_otp?email='.urlencode((string)$user['email']));
}

/* ---------- Password ---------- */
if (!password_verify($password,(string)$user['password'])) {
  if (is_json_request()) json_out(['status'=>'error','message'=>'Password salah.'],401);
  redirect('/login?err='.urlencode('Password salah.'));
}

/* ---------- Session & cookie ---------- */
session_regenerate_id(true);
$_SESSION['user_id']    = (int)$user['id'];
$_SESSION['user_name']  = (string)$user['name'];
$_SESSION['user_email'] = (string)$user['email'];
$_SESSION['user_role']  = strtolower((string)$user['role']);

$ttl = $remember ? 60*60*24*7 : 60*60*24;
// pakai helper setcookie dari config (session_set_cookie_params sudah aman)
// cookie indikator ringan (opsional)
setcookie('caffora_auth','1',[
  'expires'=> time()+$ttl,'path'=>'/','secure'=>true,'httponly'=>false,'samesite'=>'Lax'
]);
setcookie('caffora_uid',(string)$user['id'],[
  'expires'=> time()+$ttl,'path'=>'/','secure'=>true,'httponly'=>false,'samesite'=>'Lax'
]);

/* ---------- Redirect by role ---------- */
$role   = strtolower((string)$user['role']);
$target = $role==='admin' ? '/admin' : ($role==='karyawan' ? '/karyawan' : '/customer');

if (is_json_request()) {
  json_out(['status'=>'success','redirect'=>$target,'user'=>[
    'id'=>(int)$user['id'],'name'=>(string)$user['name'],'email'=>(string)$user['email'],'role'=>$role
  ]]);
}
redirect($target);

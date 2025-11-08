<?php
// backend/config.php
declare(strict_types=1);

/**
 * Konfigurasi inti:
 * - Deteksi prod vs lokal dari host
 * - BASE_URL dinamis (HTTPS-aware, proxy/CDN aware), tanpa trailing slash
 * - Koneksi MySQLi (utf8mb4)
 * - Session cookie params secure
 * - Helper redirect() & json_response()
 */

@ini_set('display_errors', '0');
@ini_set('log_errors', '1');

/* =============================
 * Env & BASE_URL
 * ============================= */
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$isProd = (stripos($host, 'caffora.my.id') !== false);

// deteksi HTTPS (termasuk jika di balik proxy/CDN)
function _is_https(): bool {
  if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') return true;
  if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') return true;
  return false;
}

$scheme = _is_https() ? 'https' : 'http';

// BASE_URL: di prod selalu https://caffora.my.id
if ($isProd) {
  $baseUrl = 'https://caffora.my.id';
} else {
  // lokal: sesuaikan subfolder proyek kamu (mis. /caffora-app1)
  $baseUrl = $scheme . '://' . $host . '/caffora-app1';
}
if (!defined('BASE_URL')) {
  define('BASE_URL', rtrim($baseUrl, '/'));
}

/* =============================
 * DB connection (MySQLi)
 * ============================= */
$db_host = 'localhost';
if ($isProd) {
  // === HOSTING (cPanel) ===
  $db_user = 'cafforam_dhyuncode';
  $db_pass = 'Uroh120202';
  $db_name = 'cafforam_db';
} else {
  // === LOKAL (XAMPP) ===
  $db_user = 'root';
  $db_pass = '';
  $db_name = 'caffora_db';
}

// lempar exception untuk error mysqli (lebih mudah ditangkap/di-log)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
  $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
  $conn->set_charset('utf8mb4');
} catch (Throwable $e) {
  http_response_code(500);
  // jangan bocorkan detail kredensial
  exit('Database connection failed.');
}

/* =============================
 * Kompatibilitas lama (opsional)
 * ============================= */
require_once __DIR__ . '/helpers.php';

if (!defined('DB_HOST')) define('DB_HOST', $db_host);
if (!defined('DB_USER')) define('DB_USER', $db_user);
if (!defined('DB_PASS')) define('DB_PASS', $db_pass);
if (!defined('DB_NAME')) define('DB_NAME', $db_name);

/* =============================
 * Session (cookie secure)
 * ============================= */
if (session_status() === PHP_SESSION_NONE) {
  // set cookie param aman sebelum session_start
  $cookieSecure = _is_https();
  session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',         // biarkan default
    'secure'   => $cookieSecure,
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
  session_start();
}

/* =============================
 * Helper: redirect()
 * - Param absolut (http/https) → kirim apa adanya
 * - Param relatif → gabung dengan BASE_URL (diawali "/")
 * ============================= */
if (!function_exists('redirect')) {
  function redirect(string $to): void {
    // sanitasi sederhana: jangan izinkan skema tersisip di path
    if ($to !== '' && preg_match('~https?://~i', $to)) {
      header('Location: ' . $to);
      exit;
    }
    $path = '/' . ltrim($to, '/');
    header('Location: ' . BASE_URL . $path);
    exit;
  }
}

/* =============================
 * Helper: json_response()
 * ============================= */
if (!function_exists('json_response')) {
  function json_response(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }
}

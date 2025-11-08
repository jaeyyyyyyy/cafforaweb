<?php
// public/index.php
declare(strict_types=1);

require_once __DIR__ . '/../backend/config.php'; // BASE_URL, dll

// ---- util ----
function path_only(): string {
  $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
  // hapus base subdir kalau ada (biasanya root sudah "/")
  return rtrim($uri, '/') === '' ? '/' : rtrim($uri, '/');
}
function include_if_exists(string $absFile): bool {
  if (is_file($absFile)) { require $absFile; return true; }
  return false;
}
function go_404(): never {
  http_response_code(404);
  // boleh pakai halaman 404 kamu sendiri di public/404.html
  $custom = __DIR__ . '/404.html';
  if (is_file($custom)) { readfile($custom); }
  else { echo '<h1>404</h1><p>Halaman tidak ditemukan.</p>'; }
  exit;
}

// ---- routing ----
$path = path_only();
// Normalisasi: '' → '/'
if ($path === '') $path = '/';

// 1) Home → public/index.html (landing yang sudah ada)
if ($path === '/') {
  // HTML statis:
  $landing = __DIR__ . '/index.html';
  if (include_if_exists($landing)) exit;
  // atau kalau kamu punya index.php dinamis:
  $landingPhp = __DIR__ . '/index.php';
  if (include_if_exists($landingPhp)) exit;
  go_404();
}

// 2) Halaman auth & umum (html) → tetap file lama
$mapHtml = [
  '/login'         => __DIR__ . '/login.html',
  '/register'      => __DIR__ . '/register.html',
  '/verify_otp'    => __DIR__ . '/verify_otp.html',
  '/privacy'       => __DIR__ . '/privacy.html',
  '/terms'         => __DIR__ . '/terms.html',
];
if (isset($mapHtml[$path]) && include_if_exists($mapHtml[$path])) exit;

// 3) Route pendek “/c/{id}” → handler khusus
if (preg_match('~^/c/([A-Za-z0-9\-]+)$~', $path, $m)) {
  $_GET['id'] = $m[1];
  $handler = __DIR__ . '/link_handler.php'; // buat file ini jika perlu
  if (include_if_exists($handler)) exit;
  // fallback sederhana
  header('Content-Type: text/plain; charset=utf-8');
  echo "ID: " . htmlspecialchars($_GET['id'] ?? '', ENT_QUOTES, 'UTF-8');
  exit;
}

// 4) Group routes: /customer(/page?), /admin(/page?), /karyawan(/page?)
if (preg_match('~^/(customer|admin|karyawan)(?:/([A-Za-z0-9_\-]+))?$~', $path, $m)) {
  $role  = $m[1];                         // customer | admin | karyawan
  $page  = $m[2] ?? 'index';              // default index
  // mapping ke file lama: public/{role}/{page}.php
  $target = __DIR__ . '/' . $role . '/' . $page . '.php';
  if (include_if_exists($target)) exit;

  // fallback: kalau .php tidak ada, coba .html (kalau ada halaman statis)
  $targetHtml = __DIR__ . '/' . $role . '/' . $page . '.html';
  if (include_if_exists($targetHtml)) exit;

  go_404();
}

// 5) (Opsional) Clean URL untuk file PHP umum di /public
//    Contoh: /menu → public/menu.php
if (preg_match('~^/([A-Za-z0-9_\-]+)$~', $path, $m)) {
  $file = __DIR__ . '/' . $m[1] . '.php';
  if (include_if_exists($file)) exit;
  $fileHtml = __DIR__ . '/' . $m[1] . '.html';
  if (include_if_exists($fileHtml)) exit;
}

// 6) default → 404
go_404();

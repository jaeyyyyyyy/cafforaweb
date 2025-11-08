<?php
// backend/logout.php
declare(strict_types=1);
require_once __DIR__.'/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/* --- bersihkan session --- */
$_SESSION = [];
if (ini_get('session.use_cookies')) {
  $p = session_get_cookie_params();
  setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

/* --- hapus cookies app --- */
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')==='https');
$cookieOpts = ['expires'=>time()-3600, 'path'=>'/', 'secure'=>$https, 'httponly'=>false, 'samesite'=>'Lax'];
setcookie('caffora_auth','', $cookieOpts);
setcookie('caffora_uid','',  $cookieOpts);

/* --- minta browser bersihkan storage (Chrome/Edge/Firefox modern) --- */
header('Clear-Site-Data: "storage", "cookies"'); // hapus localStorage, sessionStorage, cookies untuk origin ini
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

/* --- fallback JS: bersihkan storage lalu redirect ke /login --- */
$to = rtrim((string)BASE_URL,'/').'/login';
?>
<!doctype html>
<html><meta charset="utf-8"><title>Logging out…</title>
<script>
try{
  // hapus semua key keranjang & sisa legacy
  const PREFIXES = ['caffora_cart_', 'cart', 'wishlist', 'caffora_'];
  for (let i = 0; i < localStorage.length; i++) {
    const k = localStorage.key(i); if (!k) continue;
    if (PREFIXES.some(p => k.startsWith(p))) localStorage.removeItem(k);
  }
  sessionStorage.clear?.();
  // bersihkan CacheStorage (kalau pernah pakai service worker)
  if (window.caches?.keys) caches.keys().then(keys => keys.forEach(k => caches.delete(k)));
}catch(e){}
location.replace(<?= json_encode($to) ?>);
</script>
<noscript>
  <meta http-equiv="refresh" content="0;url=<?= htmlspecialchars($to, ENT_QUOTES) ?>">
</noscript>
<body>Logging out…</body></html>

<?php
// public/customer/notifications.php
declare(strict_types=1);

require_once __DIR__ . '/../../backend/auth_guard.php';
require_login(['customer']);
require_once __DIR__ . '/../../backend/config.php';   // $conn, BASE_URL, dst.

$name   = $_SESSION['user_name']  ?? 'Customer';
$email  = $_SESSION['user_email'] ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);

// Ambil list (gunakan koneksi $conn dari config)
@mysqli_report(MYSQLI_REPORT_OFF);
@$conn->set_charset('utf8mb4');

$sql = "
  SELECT id, user_id, role, message, status, created_at, link
  FROM notifications
  WHERE user_id = ?
     OR (user_id IS NULL AND (role IS NULL OR role = 'customer'))
  ORDER BY created_at DESC
  LIMIT 100
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $userId);
$stmt->execute();
$res = $stmt->get_result();
$notifs = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Notifikasi — Caffora</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root{ --gold:#FFD54F; --brown:#4B3F36; --page:#FFFDF8; --ink:#111827; --muted:#6b7280; }
    *{ box-sizing:border-box; font-family:Poppins,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif; }
    body{ background:var(--page); color:var(--ink); margin:0; }
    .topbar{ position:sticky; top:0; z-index:50; background:#fff; border-bottom:1px solid #efefef; }
    .topbar .inner{ max-width:1200px; margin:0 auto; padding:12px 16px; min-height:52px; display:flex; align-items:center; gap:8px; }
    .back-link{ display:inline-flex; align-items:center; gap:10px; color:var(--ink); text-decoration:none; font-weight:600; font-size:1rem; }
    .back-link .chev{ font-size:18px; line-height:1; }
    .page{ max-width:1200px; margin:0 auto; padding:12px 16px 56px; }
    .notif-card{ background:#fff; border:1px solid rgba(75,63,54,.04); border-radius:18px; padding:16px 20px 14px; display:flex; gap:14px; cursor:pointer; transition:box-shadow .15s ease; box-shadow:0 1px 2px rgba(0,0,0,0.02); }
    .notif-card + .notif-card{ margin-top:12px; }
    .notif-card:hover{ box-shadow:0 4px 16px rgba(0,0,0,0.04); }
    .notif-unread{ background:#FFF3C4; border-color:rgba(255,213,79,.35); }
    .notif-body{ flex:1; }
    .notif-msg{ font-size:.95rem; line-height:1.6; color:#111827; }
    .notif-time{ font-size:.8rem; color:var(--muted); margin-top:4px; }
    .empty-box{ background:#fff; border-radius:18px; border:1px dashed rgba(0,0,0,.04); text-align:center; padding:40px 24px; color:var(--muted); }
  </style>
</head>
<body>
  <!-- HEADER -->
  <div class="topbar">
    <div class="inner">
      <!-- Back pakai history.back() dengan fallback ke clean URL -->
      <a href="<?= BASE_URL ?>/customer/index" id="backLink" class="back-link">
        <i class="bi bi-arrow-left chev"></i><span>Kembali</span>
      </a>
    </div>
  </div>

  <!-- LIST NOTIF -->
  <main class="page">
    <?php if (!$notifs): ?>
      <div class="empty-box">Belum ada notifikasi.</div>
    <?php else: foreach ($notifs as $n): ?>
      <div class="notif-card <?= ($n['status']??'')==='unread'?'notif-unread':'' ?>"
           <?= !empty($n['link']) ? 'data-link="'.htmlspecialchars($n['link'],ENT_QUOTES,'UTF-8').'"' : '' ?>>
        <div class="notif-body">
          <div class="notif-msg"><?= htmlspecialchars($n['message'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
          <div class="notif-time" data-time="<?= htmlspecialchars($n['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </main>

  <script>
    // --- Back link aman: history.back() jika referer masih 1 origin
    document.getElementById('backLink').addEventListener('click', function(e){
      try{
        const ref = document.referrer || '';
        if (ref && new URL(ref).origin === location.origin){
          e.preventDefault(); history.back();
        }
      }catch(_){}
    });

    // --- Format waktu "x menit lalu"
    function formatTimeAgo(dateStr){
      const t = new Date(dateStr).getTime();
      if (isNaN(t)) return dateStr || '';
      const d = (Date.now() - t)/1000;
      if (d < 60) return Math.floor(d)+" detik lalu";
      if (d < 3600) return Math.floor(d/60)+" menit lalu";
      if (d < 86400) return Math.floor(d/3600)+" jam lalu";
      if (d < 604800) return Math.floor(d/86400)+" hari lalu";
      return new Date(t).toLocaleString("id-ID");
    }
    function refreshTimes(){
      document.querySelectorAll(".notif-time[data-time]").forEach(el=>{
        el.textContent = formatTimeAgo(el.dataset.time);
      });
    }
    refreshTimes(); setInterval(refreshTimes, 10000);

    // --- Klik kartu → buka link (jika ada)
    document.querySelectorAll(".notif-card[data-link]").forEach(card=>{
      card.addEventListener("click", ()=>{
        const link = card.getAttribute("data-link");
        if (link) location.href = link;
      });
    });

    // --- Mark all as read (clean → fallback legacy)
    (async function markAll(){
      const body = new URLSearchParams({ action:'mark_all_read' });
      async function call(u){
        const r = await fetch(u, { method:'POST', credentials:'same-origin',
          headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'}, body });
        return r.ok ? r.json() : {};
      }
      try{
        let js = await call("/api/notifications");
        if (!js || js.ok !== true) js = await call("<?= BASE_URL ?>/backend/api/notifications.php");
      }catch(e){}
      // beri sinyal ke beranda agar badge di-hide segera
      try{ localStorage.setItem('caffora_notif_just_read','1'); }catch(_){}
    })();
  </script>
</body>
</html>

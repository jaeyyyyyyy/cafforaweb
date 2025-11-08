<?php
// public/karyawan/notifications.php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../../backend/config.php';

// guard: hanya karyawan
if (!isset($_SESSION['user_id']) || (($_SESSION['user_role'] ?? '') !== 'karyawan')) {
  header('Location: ' . BASE_URL . '/public/login.html');
  exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$BASE   = BASE_URL;

// 1) JANGAN tandai read di sini.
//    Biar nanti JS yg tandai setelah halaman tampil.

// 2) Ambil daftar notif (karyawan spesifik + broadcast karyawan)
$sql = "
  SELECT id, message, status, created_at, link
  FROM notifications
  WHERE user_id = ?
     OR (user_id IS NULL AND (role IS NULL OR role='karyawan'))
  ORDER BY created_at DESC
  LIMIT 100
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $userId);
$stmt->execute();
$res  = $stmt->get_result();
$rows = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Notifikasi — Karyawan</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root{
      --gold:#FFD54F;
      --page:#FFFDF8;
      --ink:#111827;
      --muted:#6b7280;
      --unread:#FFEBA6; /* kuning kayak customer */
    }
    *{
      box-sizing:border-box;
      font-family:Poppins,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;
    }
    body{
      background:var(--page);
      color:var(--ink);
      margin:0;
    }

    /* ===== TOPBAR (lebar penuh kayak customer) ===== */
    .topbar{
      position:sticky;
      top:0;
      z-index:50;
      background:#fff;
      border-bottom:1px solid #efefef;
    }
    .topbar .inner{
      max-width:100%;
      padding:12px 32px;
      display:flex;
      align-items:center;
      gap:8px;
      min-height:52px;
    }
    .back-link{
      display:inline-flex;
      align-items:center;
      gap:10px;
      color:var(--ink);
      text-decoration:none;
      font-weight:600;
      font-size:1rem;
    }
    .back-link .chev{
      font-size:18px;
      line-height:1;
    }

    /* ===== WRAPPER ===== */
    .page{
      padding:16px 32px 70px;
    }

    /* ===== NOTIF CARD ===== */
    .notif-card{
      background:#fff;
      border-radius:18px;
      padding:16px 20px 14px;
      transition:box-shadow .15s ease;
      cursor:pointer;
      box-shadow:0 1px 2px rgba(0,0,0,0.02);
      border:1px solid rgba(0,0,0,.015);
    }
    .notif-card + .notif-card{
      margin-top:14px;
    }
    .notif-card:hover{
      box-shadow:0 4px 16px rgba(0,0,0,0.04);
    }
    /* ini yang bikin kuning */
    .notif-unread{
      background:var(--unread);
      border-color:rgba(255,213,79,.3);
    }
    .notif-msg{
      font-size:.95rem;
      line-height:1.6;
    }
    .notif-time{
      font-size:.8rem;
      color:var(--muted);
      margin-top:4px;
    }
    .empty-box{
      background:#fff;
      border-radius:18px;
      border:1px dashed rgba(0,0,0,.04);
      text-align:center;
      padding:40px 24px;
      color:var(--muted);
    }

    @media (max-width: 720px){
      .topbar .inner{ padding:12px 16px; }
      .page{ padding:12px 16px 40px; }
      .notif-card{ border-radius:14px; padding:14px 14px 12px; }
    }
  </style>
</head>
<body>
  <!-- HEADER -->
  <header class="topbar">
    <div class="inner">
      <a href="<?= htmlspecialchars($BASE) ?>/public/karyawan/index.php" class="back-link">
        <i class="bi bi-arrow-left chev"></i>
        <span>Kembali</span>
      </a>
    </div>
  </header>

  <!-- LIST NOTIF -->
  <main class="page">
    <?php if (!count($rows)): ?>
      <div class="empty-box">Belum ada notifikasi.</div>
    <?php else: ?>
      <?php foreach ($rows as $n): ?>
        <?php
          $link = $n['link'] ?? '';
          if (!$link && stripos($n['message'] ?? '', 'invoice') !== false) {
            $link = $BASE . '/public/karyawan/orders.php';
          }
        ?>
        <div
          class="notif-card <?= $n['status'] === 'unread' ? 'notif-unread' : '' ?>"
          <?php if ($link): ?> data-link="<?= htmlspecialchars($link, ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?>
        >
          <div class="notif-msg">
            <?= htmlspecialchars($n['message'] ?? '', ENT_QUOTES, 'UTF-8') ?>
          </div>
          <div class="notif-time" data-time="<?= htmlspecialchars($n['created_at'], ENT_QUOTES, 'UTF-8') ?>"></div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </main>

  <script>
    // format waktu
    function formatTimeAgo(dateStr){
      const t = new Date(dateStr).getTime();
      if (isNaN(t)) return dateStr;
      const diff = (Date.now() - t)/1000;
      if (diff < 60) return Math.floor(diff) + " detik lalu";
      if (diff < 3600) return Math.floor(diff/60) + " menit lalu";
      if (diff < 86400) return Math.floor(diff/3600) + " jam lalu";
      if (diff < 604800) return Math.floor(diff/86400) + " hari lalu";
      return new Date(dateStr).toLocaleString("id-ID");
    }
    function refreshTimes(){
      document.querySelectorAll(".notif-time[data-time]").forEach(el=>{
        el.textContent = formatTimeAgo(el.dataset.time);
      });
    }
    refreshTimes();
    setInterval(refreshTimes, 10_000);

    // klik kartu → ke link
    document.querySelectorAll(".notif-card[data-link]").forEach(card=>{
      card.addEventListener("click", ()=>{
        const link = card.getAttribute("data-link");
        if (link) window.location.href = link;
      });
    });

    // 3) setelah HALAMAN tampil, baru tandai semua dibaca
    //    kamu bisa pakai endpoint customer yg udah ada,
    //    tapi kita tambahi query ?role=karyawan biar di backend bisa bedain
    fetch("<?= $BASE ?>/backend/api/notifications.php?action=mark_all_read&role=karyawan", {
      credentials: "same-origin"
    }).catch(()=>{});
  </script>
</body>
</html>

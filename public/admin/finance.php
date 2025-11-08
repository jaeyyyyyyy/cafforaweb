<?php 
// public/admin/finance.php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../../backend/config.php';

/* ===== Guard role: admin ===== */
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
  header('Location: ' . BASE_URL . '/public/login.html');
  exit;
}

$userName  = htmlspecialchars($_SESSION['user_name']  ?? '', ENT_QUOTES, 'UTF-8');
$userEmail = htmlspecialchars($_SESSION['user_email'] ?? '', ENT_QUOTES, 'UTF-8');

/* ============================================================
   RENTANG TANGGAL
   ============================================================ */
$range     = $_GET['range'] ?? '7d';
$today     = new DateTime('today');
$endDate   = clone $today;
$startDate = clone $today;

if ($range === 'today') {
  // today 00:00:00 - 23:59:59
} elseif ($range === '7d') {
  $startDate->modify('-6 day');
} elseif ($range === '30d') {
  $startDate->modify('-29 day');
} elseif ($range === 'custom') {
  $from = $_GET['from'] ?? '';
  $to   = $_GET['to']   ?? '';
  $tmpStart = $from ? DateTime::createFromFormat('Y-m-d', $from) : null;
  $tmpEnd   = $to   ? DateTime::createFromFormat('Y-m-d', $to)   : null;
  if ($tmpStart && $tmpEnd) { $startDate = $tmpStart; $endDate = $tmpEnd; }
  else { $range = '7d'; $startDate = (clone $today)->modify('-6 day'); }
} else {
  $range = '7d';
  $startDate->modify('-6 day');
}

$startStr = $startDate->format('Y-m-d 00:00:00');
$endStr   = $endDate->format('Y-m-d 23:59:59');

/* ============================================================
   EXPORT CSV (ikut periode aktif) — LENGKAP
   ============================================================ */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  $file = sprintf('finance_%s-%s.csv', $startDate->format('Ymd'), $endDate->format('Ymd'));
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="'.$file.'"');

  $out = fopen('php://output', 'w');
  // BOM UTF-8 agar Excel aman
  fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

  fputcsv($out, [
    'Order ID','Invoice','Waktu','Customer',
    'Order Items (nama x qty)','Total (IDR)',
    'Payment Method','Service Type','Order Status','Payment Status'
  ]);

  $sqlCsv = "
    SELECT
      o.id AS order_id,
      o.invoice_no,
      DATE_FORMAT(o.created_at, '%Y-%m-%d %H:%i') AS waktu,
      COALESCE(NULLIF(o.customer_name,''), u.name, 'Guest') AS customer,
      GROUP_CONCAT(CONCAT(m.name,' x',oi.qty) ORDER BY oi.id SEPARATOR '; ') AS items,
      COALESCE(NULLIF(o.grand_total,0), o.total) AS total_idr,
      o.payment_method,
      o.service_type,
      o.order_status,
      o.payment_status
    FROM orders o
    LEFT JOIN users u        ON u.id = o.user_id
    LEFT JOIN order_items oi ON oi.order_id = o.id
    LEFT JOIN menu m         ON m.id = oi.menu_id
    WHERE o.created_at BETWEEN ? AND ?
    GROUP BY o.id, o.invoice_no, o.created_at, u.name,
             o.total, o.grand_total, o.payment_method, o.service_type,
             o.order_status, o.payment_status
    ORDER BY o.created_at DESC
  ";
  $stmtCsv = $conn->prepare($sqlCsv);
  $stmtCsv->bind_param('ss', $startStr, $endStr);
  $stmtCsv->execute();
  $resCsv = $stmtCsv->get_result();

  while ($r = $resCsv->fetch_assoc()) {
    fputcsv($out, [
      $r['order_id'],
      $r['invoice_no'],
      $r['waktu'],
      $r['customer'],
      $r['items'] ?? '',
      (int)$r['total_idr'],
      $r['payment_method'],
      $r['service_type'],
      $r['order_status'],
      $r['payment_status']
    ]);
  }
  $stmtCsv->close();
  fclose($out);
  exit;
}

/* ============================================================
   REVENUE HARIAN (paid) — ikut periode
   ============================================================ */
$labels = [];
$map    = [];
$period = new DatePeriod($startDate, new DateInterval('P1D'), (clone $endDate)->modify('+1 day'));
foreach ($period as $d) {
  $key = $d->format('Y-m-d'); $labels[] = $key; $map[$key] = 0.0;
}
$stmt = $conn->prepare("
  SELECT DATE(created_at) AS d, SUM(total) AS s
  FROM orders
  WHERE created_at BETWEEN ? AND ?
    AND payment_status = 'paid'
  GROUP BY DATE(created_at)
  ORDER BY d ASC
");
$stmt->bind_param('ss', $startStr, $endStr);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) { $map[$row['d']] = (float)$row['s']; }
$stmt->close();

$revenue = [];
foreach ($labels as $d) $revenue[] = $map[$d] ?? 0;
$totalRevenue = array_sum($revenue);

/* count paid & avg */
$stmt2 = $conn->prepare("
  SELECT COUNT(*) AS c
  FROM orders
  WHERE created_at BETWEEN ? AND ?
    AND payment_status='paid'
");
$stmt2->bind_param('ss', $startStr, $endStr);
$stmt2->execute();
$row2 = $stmt2->get_result()->fetch_assoc();
$stmt2->close();
$ordersPaidCount = (int)($row2['c'] ?? 0);
$avgOrder = $ordersPaidCount ? ($totalRevenue / $ordersPaidCount) : 0;

/* ============================================================
   RINGKASAN 4 KARTU (ikut periode)
   ============================================================ */
$statusSummary = ['paid'=>0,'pending'=>0,'cancel'=>0,'done'=>0];
$qs = $conn->prepare("
  SELECT 
    SUM(CASE WHEN payment_status='paid'    THEN 1 ELSE 0 END) AS cnt_paid,
    SUM(CASE WHEN payment_status='pending' THEN 1 ELSE 0 END) AS cnt_pending,
    SUM(CASE WHEN 
          LOWER(order_status) IN ('cancelled','canceled','cancel','returned','return','failed')
          OR payment_status='failed'
        THEN 1 ELSE 0 END) AS cnt_cancel,
    SUM(CASE WHEN LOWER(order_status)='completed' THEN 1 ELSE 0 END) AS cnt_done
  FROM orders
  WHERE created_at BETWEEN ? AND ?
");
$qs->bind_param('ss', $startStr, $endStr);
$qs->execute();
$qr = $qs->get_result()->fetch_assoc();
$qs->close();
$statusSummary['paid']    = (int)($qr['cnt_paid']    ?? 0);
$statusSummary['pending'] = (int)($qr['cnt_pending'] ?? 0);
$statusSummary['cancel']  = (int)($qr['cnt_cancel']  ?? 0);
$statusSummary['done']    = (int)($qr['cnt_done']    ?? 0);

/* ============================================================
   TOP MENU TERLARIS — ikut periode
   ============================================================ */
$topMenus = [];
$sqlTop = "
  SELECT 
    m.id, m.name, m.image,
    COALESCE(SUM(oi.qty),0) AS sold_qty,
    COALESCE(SUM(oi.qty * IFNULL(oi.price, m.price)),0) AS sold_amount
  FROM order_items oi
  INNER JOIN orders o ON o.id = oi.order_id
  INNER JOIN menu   m ON m.id = oi.menu_id
  WHERE o.created_at BETWEEN ? AND ?
    AND o.payment_status = 'paid'
  GROUP BY m.id, m.name, m.image
  ORDER BY sold_qty DESC, sold_amount DESC
  LIMIT 6
";
$stmt3 = $conn->prepare($sqlTop);
$stmt3->bind_param('ss', $startStr, $endStr);
$stmt3->execute();
$resTop = $stmt3->get_result();
while ($row = $resTop->fetch_assoc()) {
  $imgRaw = (string)($row['image'] ?? '');
  $row['img_url'] = $imgRaw ? BASE_URL . '/public/' . ltrim($imgRaw, '/') : BASE_URL . '/public/assets/img/menu-placeholder.png';
  $topMenus[] = $row;
}
$stmt3->close();

/* ============================================================
   DISTRIBUSI STATUS
   ============================================================ */
$dist = [
  'new'        => ['orders'=>0,'qty'=>0],
  'processing' => ['orders'=>0,'qty'=>0],
  'ready'      => ['orders'=>0,'qty'=>0],
  'completed'  => ['orders'=>0,'qty'=>0],
  'cancelled'  => ['orders'=>0,'qty'=>0],
];
$stmtDist = $conn->prepare("
  SELECT 
    CASE 
      WHEN LOWER(o.order_status) IN ('cancelled','canceled','cancel','return','returned','failed') 
        THEN 'cancelled'
      ELSE LOWER(o.order_status)
    END AS st,
    COUNT(DISTINCT o.id)    AS orders_cnt,
    COALESCE(SUM(oi.qty),0) AS items_qty
  FROM orders o
  LEFT JOIN order_items oi ON oi.order_id = o.id
  WHERE o.created_at BETWEEN ? AND ?
    AND (
      LOWER(o.order_status) IN ('new','processing','ready','completed')
      OR LOWER(o.order_status) IN ('cancelled','canceled','cancel','return','returned','failed')
    )
  GROUP BY st
");
$stmtDist->bind_param('ss', $startStr, $endStr);
$stmtDist->execute();
$resDist = $stmtDist->get_result();
while ($r = $resDist->fetch_assoc()) {
  $k = $r['st'];
  if (isset($dist[$k])) {
    $dist[$k]['orders'] = (int)($r['orders_cnt'] ?? 0);
    $dist[$k]['qty']    = (int)($r['items_qty']  ?? 0);
  }
}
$stmtDist->close();
$distLabels = ['New','Processing','Ready','Completed','Cancelled'];
$distOrders = [$dist['new']['orders'],$dist['processing']['orders'],$dist['ready']['orders'],$dist['completed']['orders'],$dist['cancelled']['orders']];

/* ============================================================
   TRANSAKSI TERBARU — pakai orders.customer_name
   ============================================================ */
$latestTx = [];
$stmt4 = $conn->prepare("
  SELECT 
    o.id,
    o.total,
    o.payment_status,
    o.created_at,
    COALESCE(NULLIF(o.customer_name,''), u.name, 'Guest') AS customer_name
  FROM orders o
  LEFT JOIN users u ON u.id = o.user_id
  WHERE o.created_at BETWEEN ? AND ?
  ORDER BY o.created_at DESC
  LIMIT 6
");
$stmt4->bind_param('ss', $startStr, $endStr);
$stmt4->execute();
$res4 = $stmt4->get_result();
while ($row = $res4->fetch_assoc()) $latestTx[] = $row;
$stmt4->close();
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Finance — Caffora</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <script src="https://code.iconify.design/2/2.2.1/iconify.min.js"></script>

  <style>
  :root{
    --gold:#FFD54F;
    --gold-border:#f7d78d;
    --brown:#4B3F36;
    --ink:#0f172a;
    --radius:18px;
    --sidebar-w:320px;
    --soft:#fff7d1;
    --hover:#ffefad;
    --btn-radius:14px;
    --input-border:#E8E2DA;
  }
  *,:before,:after{ box-sizing:border-box; }

  body{
    background:#FAFAFA; color:var(--ink);
    font-family:Inter,system-ui,Segoe UI,Roboto,Arial;
    font-weight:500;
  }

  /* ===== Sidebar ===== */
  .sidebar{
    position:fixed; left:-320px; top:0; bottom:0; width:var(--sidebar-w);
    background:#fff; border-right:1px solid rgba(0,0,0,.05);
    transition:left .25s ease; z-index:1050; padding:16px 18px; overflow-y:auto;
  }
  .sidebar.show{ left:0; }
  .sidebar-head{ display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:10px; }
  .sidebar-inner-toggle, .sidebar-close-btn{ background:transparent; border:0; width:40px; height:36px; display:grid; place-items:center; }
  .hamb-icon{ width:24px; height:20px; display:flex; flex-direction:column; justify-content:space-between; gap:4px; }
  .hamb-icon span{ height:2px; background:var(--brown); border-radius:99px; }
  .sidebar .nav-link{ display:flex; align-items:center; gap:12px; padding:12px 14px; border-radius:16px; font-weight:600; color:#111; text-decoration:none; }
  .sidebar .nav-link:hover{ background:rgba(255,213,79,0.25); }
  .sidebar hr{ border-color:rgba(0,0,0,.05); opacity:1; }

  /* Backdrop */
  .backdrop-mobile{ position:fixed; inset:0; background:rgba(0,0,0,.25); z-index:1040; display:none; }
  .backdrop-mobile.active{ display:block; }

  /* ===== Content & Topbar (DISESUAIKAN) ===== */
  .content{ padding:16px 14px 50px; }
  .topbar{ display:flex; align-items:center; gap:12px; margin-bottom:16px; }
  .btn-menu{ background:transparent; border:0; width:40px; height:38px; display:grid; place-items:center; }
  .hamb-icon{ width:24px; height:20px; display:flex; flex-direction:column; justify-content:space-between; gap:4px; }
  .hamb-icon span{ height:2px; background:var(--brown); border-radius:99px; }

  /* Search */
  .search-box{ position:relative; flex:1 1 420px; min-width:220px; }
  .search-input{
    height:46px; width:100%; border-radius:9999px; padding-left:16px; padding-right:44px;
    border:1px solid #e5e7eb; background:#fff; outline:none; transition:all .12s;
  }
  .search-input:focus{ border-color:var(--gold); box-shadow:none; }
  .search-icon{ position:absolute; right:16px; top:50%; transform:translateY(-50%); color:var(--brown); cursor:pointer; }
  .search-suggest{
    position:absolute; top:100%; left:0; margin-top:6px; background:#fff; border:1px solid rgba(247,215,141,.8);
    border-radius:16px; width:100%; max-height:280px; overflow-y:auto; display:none; z-index:40;
  }
  .search-suggest.visible{ display:block; }
  .search-empty{ padding:12px 14px; color:#6b7280; font-size:.8rem; }

  .top-actions{ display:flex; align-items:center; gap:14px; flex:0 0 auto; }
  .icon-btn{ width:38px; height:38px; border-radius:999px; display:flex; align-items:center; justify-content:center; color:var(--brown); text-decoration:none; background:transparent; outline:none; }

  /* Cards */
  .cardx{ background:#fff; border:1px solid var(--gold-border); border-radius:var(--radius); padding:18px; min-width:0; }
  .kpi .value{ font-size:2rem; font-weight:700; letter-spacing:.2px; }

  .summary-grid{ display:grid; grid-template-columns:1fr 1fr; gap:18px; }
  .summary-card{ background:#fff; border:1px solid var(--gold-border); border-radius:20px; padding:18px 20px; min-width:0; }
  .summary-card .label{ color:#6b7280; font-weight:600; }
  .summary-card .value{ margin-top:6px; font-size:2.05rem; font-weight:700; color:#0f172a; line-height:1; }

  .top-menu-item{ display:flex; align-items:center; gap:14px; padding:12px 10px; border-bottom:1px solid rgba(17,24,39,.05); }
  .top-menu-item:last-child{ border-bottom:0; }
  .top-menu-thumb{ width:46px; height:46px; border-radius:16px; overflow:hidden; background:#f3f4f6; flex:0 0 auto; }
  .top-menu-thumb img{ width:100%; height:100%; object-fit:cover; display:block; }
  .top-menu-name{ font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:100%; }
  .top-menu-sub{ font-size:.78rem; color:#6b7280; white-space:nowrap; }

  /* Range + Download */
  .range-wrap{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; justify-content:flex-start; }

  .select-ghost{ position:absolute !important; width:1px; height:1px; opacity:0; pointer-events:none; left:-9999px; top:auto; overflow:hidden; }

  .select-custom{ position:relative; display:inline-block; max-width:100%; }
  .select-toggle{
    width:200px; max-width:100%; height:42px; display:flex; align-items:center; justify-content:space-between;
    gap:10px; background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:0 14px;
    cursor:pointer; user-select:none; outline:0;
  }
  .select-toggle:focus{ border-color:#ffd54f; }
  .select-caret{ font-size:16px; color:#111; }
  .select-menu{
    position:absolute; top:46px; left:0; z-index:1060; background:#fff; border:1px solid rgba(247,215,141,.9);
    border-radius:14px; box-shadow:0 12px 28px rgba(0,0,0,.08); min-width:100%; display:none; padding:6px; max-height:280px; overflow:auto;
  }
  .select-menu.show{ display:block; }
  .select-item{ padding:10px 12px; border-radius:10px; cursor:pointer; font-weight:600; color:#374151; }
  .select-item:hover{ background:var(--hover); }
  .select-item.active{ background:var(--soft); }

  /* Tombol Download (base) */
  .btn-download{
    background-color: var(--gold);
    color: var(--brown);
    border: 0;
    border-radius: var(--btn-radius);
    font-family: Arial, Helvetica, sans-serif;
    font-weight: 600;
    font-size: .9rem;
    padding: 10px 18px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    line-height: 1;
  }
  .btn-download:hover{ filter: brightness(.97); }

  /* Chart */
  #revChart{ width:100% !important; max-height:330px; }
  .chart-wrapper{ min-height:220px; height:clamp(220px, 38vh, 360px); }

  /* ====== Responsive ====== */
  @media (min-width:992px){
    .content{ padding:20px 26px 50px; }
    .search-box{ max-width:1100px; }
  }
  @media (min-width:768px) and (max-width:991.98px){
    .content{ padding:18px 16px 60px; }
    .summary-grid{ grid-template-columns:1fr 1fr; gap:14px; }
    .summary-card{ padding:16px; }
    .summary-card .value{ font-size:1.8rem; }
    #revChart{ max-height:300px; }
    .chart-wrapper{ height:clamp(220px, 34vh, 320px); }
  }

  /* ===== Mobile tweaks (<=575.98px) ===== */
  .search-box{ min-width:0; } /* biar bisa menyusut */

  @media (max-width:575.98px){
    .content{ padding:14px 12px 70px; } /* kiri-kanan sama seperti halaman lain */
    .summary-grid{ grid-template-columns:1fr; gap:12px; }
    .cardx{ padding:16px; }
    #revChart{ max-height:240px !important; }
    .chart-wrapper{ height:240px; }

    /* Topbar konsisten */
    .topbar{ padding:8px 0; gap:8px; }
    .btn-menu{ width:36px; height:34px; flex:0 0 36px; margin-left:-2px; order:1; }
    .top-actions{ order:3; flex:0 0 auto; gap:8px; }
    .icon-btn{ width:34px; height:34px; }
    .search-box{
      order:2; flex:1 1 auto;
      max-width: clamp(140px, calc(100% - 120px), 100%);
    }
    .search-input{ height:40px; }

    /* Range + Download mengikuti gaya halaman Audit */
    .range-wrap{ gap:8px; width:100%; }
    .range-wrap .select-custom{ width:100%; flex:1 0 100%; }
    #btnDownload{
      flex:1 0 100%;
      width:100%;
      height:44px;
      display:inline-flex;
      align-items:center;
      justify-content:center;   /* teks & ikon center */
      gap:8px;
      border:1px solid rgba(0,0,0,.06);
      border-radius:14px;
      padding:0 12px;
      font-weight:700;          /* tebal seperti Audit */
    }
    #btnDownload svg{ width:18px; height:18px; }
  }

  @media (max-width:360px){
    .top-actions{ gap:6px; }
    .search-box{ max-width: calc(100% - 128px); } /* ekstra ruang device sempit */
  }

  /* ===== Desktop padding tweak tetap seperti sebelumnya ===== */
  @media (min-width:1200px){
    .content{ padding-left:16px !important; padding-right:16px !important; }
    .topbar{ padding-left:4px; padding-right:4px; }
    .btn-menu{ margin-left:-6px; }
  }
  @media (min-width:1400px){
    .content{ padding-left:12px !important; padding-right:12px !important; }
    .btn-menu{ margin-left:-10px; }
  }
  @media (max-width:575.98px){
  .range-wrap{
    display:grid;
    grid-template-columns: 1fr 1fr;
    gap:8px;
  }
  .range-wrap .select-custom,
  #btnDownload{ width:100%; flex:unset; }
}

  </style>
</head>
<body>

<div id="backdrop" class="backdrop-mobile"></div>
<!-- sidebar -->
<aside class="sidebar" id="sideNav">
  <div class="sidebar-head">
    <button class="sidebar-inner-toggle" id="toggleSidebarInside" aria-label="Tutup menu"></button>
    <button class="sidebar-close-btn" id="closeSidebar" aria-label="Tutup menu"><i class="bi bi-x-lg"></i></button>
  </div>

  <nav class="nav flex-column gap-2" id="sidebar-nav">
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/index.php"><i class="bi bi-house-door"></i> Dashboard</a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/orders.php"><i class="bi bi-receipt"></i> Orders</a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/catalog.php"><i class="bi bi-box-seam"></i> Catalog</a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/users.php"><i class="bi bi-people"></i> Users</a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/finance.php"><i class="bi bi-cash-coin"></i> Finance</a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/notifications_send.php"><i class="bi bi-megaphone"></i> Kirim Notifikasi</a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/audit.php"><i class="bi bi-shield-check"></i> Audit Log</a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/settings.php"><i class="bi bi-gear"></i> Settings</a>
    <hr>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/help.php"><i class="bi bi-question-circle"></i> Help Center</a>
    <a class="nav-link" href="<?= BASE_URL ?>/backend/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
  </nav>
</aside>

<!-- Content -->
<main class="content">
  <div class="container-xxl px-2 px-xl-3">
    <div class="topbar">
      <button class="btn-menu" id="openSidebar" aria-label="Buka menu">
        <div class="hamb-icon"><span></span><span></span><span></span></div>
      </button>

      <div class="search-box">
        <input class="search-input" id="searchInput" placeholder="Search..." autocomplete="off" />
        <i class="bi bi-search search-icon" id="searchIcon"></i>
        <div class="search-suggest" id="searchSuggest"></div>
      </div>

      <div class="top-actions">
        <a id="btnBell" class="icon-btn position-relative text-decoration-none" href="<?= BASE_URL ?>/public/admin/notifications.php">
          <span class="iconify" data-icon="mdi:bell-outline" data-width="24" data-height="24"></span>
          <span id="badgeNotif" class="d-none" style="position:absolute;top:3px;right:3px;width:8px;height:8px;background:#4b3f36;border-radius:999px;box-shadow:0 0 0 1.5px #fff;"></span>
        </a>
        <a class="icon-btn text-decoration-none" href="<?= BASE_URL ?>/public/admin/settings.php">
          <span class="iconify" data-icon="mdi:account-circle-outline" data-width="28" data-height="28"></span>
        </a>
      </div>
    </div>

    <h2 class="fw-bold mb-1">Finance</h2>

    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
      <div>
        <div class="text-muted small mb-1">Periode ditampilkan</div>
        <div class="fw-semibold"><?= $startDate->format('d M Y') ?> – <?= $endDate->format('d M Y') ?></div>
      </div>

      <!-- Custom Dropdown Periode + Download CSV -->
      <div class="range-wrap">
        <select id="rangeSelect" class="select-ghost" tabindex="-1" aria-hidden="true" hidden>
          <option value="today"  <?= $range==='today'?'selected':''; ?>>Hari ini</option>
          <option value="7d"     <?= $range==='7d'?'selected':''; ?>>7 hari</option>
          <option value="30d"    <?= $range==='30d'?'selected':''; ?>>30 hari</option>
          <option value="custom" <?= $range==='custom'?'selected':''; ?>>Custom…</option>
        </select>

        <div class="select-custom" id="rangeCustom">
          <button type="button" class="select-toggle" id="rangeBtn" aria-haspopup="listbox" aria-expanded="false" tabindex="0">
            <span id="rangeText">
              <?= $range==='today' ? 'Hari ini' : ($range==='7d' ? '7 hari' : ($range==='30d' ? '30 hari' : 'Custom…')) ?>
            </span>
            <i class="bi bi-chevron-down select-caret"></i>
          </button>
          <div class="select-menu" id="rangeMenu" role="listbox" aria-labelledby="rangeBtn">
            <div class="select-item<?= $range==='today'?' active':''; ?>" data-value="today">Hari ini</div>
            <div class="select-item<?= $range==='7d'?' active':''; ?>" data-value="7d">7 hari</div>
            <div class="select-item<?= $range==='30d'?' active':''; ?>" data-value="30d">30 hari</div>
            <div class="select-item<?= $range==='custom'?' active':''; ?>" data-value="custom">Custom…</div>
          </div>
        </div>

        <!-- ====== BUTTON DOWNLOAD ====== -->
        <button id="btnDownload" class="btn-download">
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" width="20" height="20">
            <path d="M12 3a1 1 0 011 1v8.586l2.293-2.293a1 1 0 111.414 1.414l-4.001 4a1 1 0 01-1.414 0l-4.001-4a1 1 0 111.414-1.414L11 12.586V4a1 1 0 011-1z"></path>
            <path d="M5 19a1 1 0 011-1h12a1 1 0 110 2H6a1 1 0 01-1-1z"></path>
          </svg>
          <span>Download</span>
        </button>
      </div>
    </div>

    <!-- KPI -->
    <div class="row g-3 mb-3 kpi">
      <div class="col-12 col-md-4">
        <div class="cardx">
          <div class="text-muted small">Total Revenue</div>
          <div class="value">Rp <?= number_format($totalRevenue,0,',','.') ?></div>
        </div>
      </div>
      <div class="col-12 col-md-4">
        <div class="cardx">
          <div class="text-muted small">Order Lunas</div>
          <div class="value"><?= number_format($ordersPaidCount,0,',','.') ?></div>
        </div>
      </div>
      <div class="col-12 col-md-4">
        <div class="cardx">
          <div class="text-muted small">Rata-rata / Order</div>
          <div class="value">Rp <?= number_format($avgOrder,0,',','.') ?></div>
        </div>
      </div>
    </div>

    <!-- Row utama -->
    <div class="row g-3 row-finance-main">
      <div class="col-12 col-lg-8 d-flex">
        <div class="cardx flex-fill">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-bold mb-0">Revenue Periode Ini</h6>
          </div>
          <div class="chart-wrapper">
            <canvas id="revChart"></canvas>
          </div>
        </div>
      </div>
      <div class="col-12 col-lg-4 d-flex">
        <div class="cardx flex-fill">
          <h6 class="fw-bold mb-2">Top Menu Terlaris</h6>
          <?php if ($topMenus): foreach ($topMenus as $m): ?>
            <div class="top-menu-item">
              <div class="top-menu-thumb">
                <img src="<?= htmlspecialchars($m['img_url'], ENT_QUOTES, 'UTF-8') ?>"
                     alt="<?= htmlspecialchars($m['name'], ENT_QUOTES, 'UTF-8') ?>"
                     onerror="this.src='<?= BASE_URL ?>/public/assets/img/menu-placeholder.png';">
              </div>
              <div class="flex-grow-1">
                <div class="top-menu-name text-truncate-1"><?= htmlspecialchars($m['name'], ENT_QUOTES, 'UTF-8') ?></div>
                <div class="top-menu-sub">Rp <?= number_format((float)$m['sold_amount'],0,',','.') ?></div>
              </div>
              <div class="fw-semibold">x<?= (int)$m['sold_qty'] ?></div>
            </div>
          <?php endforeach; else: ?>
            <div class="text-muted small">Belum ada data penjualan di periode ini.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Ringkasan + Donut -->
    <div class="row g-3 mt-1 mb-3 align-items-stretch">
      <div class="col-12 col-lg-6 d-flex">
        <div class="cardx flex-fill h-100">
          <h5 class="fw-bold mb-3">Ringkasan Status Order</h5>
          <div class="summary-grid">
            <div class="summary-card"><div class="label">Paid</div><div class="value"><?= $statusSummary['paid'] ?></div></div>
            <div class="summary-card"><div class="label">Pending</div><div class="value"><?= $statusSummary['pending'] ?></div></div>
            <div class="summary-card"><div class="label">Overdue / Cancel</div><div class="value"><?= $statusSummary['cancel'] ?></div></div>
            <div class="summary-card"><div class="label">Done (completed)</div><div class="value"><?= $statusSummary['done'] ?></div></div>
          </div>
        </div>
      </div>
      <div class="col-12 col-lg-6 d-flex">
        <div class="cardx flex-fill h-100">
          <h6 class="fw-bold mb-2">Distribusi Status Pesanan</h6>
          <canvas id="statusDonut" style="max-height:360px;"></canvas>
          <div id="statusLegend" class="mt-2 small text-muted"></div>
        </div>
      </div>
    </div>

    <!-- Transaksi terbaru -->
    <div class="cardx mb-4">
      <h6 class="fw-bold mb-2">Transaksi Terbaru</h6>
      <?php if ($latestTx): foreach ($latestTx as $tx): ?>
        <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-light-subtle">
          <div>
            <div class="fw-semibold">#<?= (int)$tx['id'] ?> — <?= htmlspecialchars($tx['customer_name'] ?? 'Guest', ENT_QUOTES, 'UTF-8') ?></div>
            <div class="text-muted small"><?= date('d M Y H:i', strtotime($tx['created_at'])) ?></div>
          </div>
          <div class="text-end">
            <div class="fw-semibold">Rp <?= number_format((float)$tx['total'],0,',','.') ?></div>
            <span class="badge rounded-pill <?= $tx['payment_status']==='paid' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' ?>">
              <?= htmlspecialchars($tx['payment_status'], ENT_QUOTES, 'UTF-8') ?>
            </span>
          </div>
        </div>
      <?php endforeach; else: ?>
        <div class="text-muted small">Belum ada transaksi di periode ini.</div>
      <?php endif; ?>
    </div>
  </div>
</main>

<!-- Modal Custom Range -->
<div class="modal fade" id="modalCustom" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:460px">
    <form class="modal-content" id="customForm">
      <div class="modal-header">
        <h5 class="modal-title">Pilih rentang tanggal</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label">Dari tanggal</label><input type="date" name="from" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Sampai tanggal</label><input type="date" name="to" class="form-control" required></div>
      </div>
      <div class="modal-footer flex-wrap gap-2">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn-download">Terapkan</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* Sidebar */
const sideNav = document.getElementById('sideNav');
const backdrop = document.getElementById('backdrop');
document.getElementById('openSidebar')?.addEventListener('click',()=>{ sideNav.classList.add('show'); backdrop.classList.add('active'); });
document.getElementById('closeSidebar')?.addEventListener('click',()=>{ sideNav.classList.remove('show'); backdrop.classList.remove('active'); });
backdrop?.addEventListener('click',()=>{ sideNav.classList.remove('show'); backdrop.classList.remove('active'); });
document.querySelectorAll('#sidebar-nav .nav-link').forEach(a=>{
  a.addEventListener('click',function(){
    document.querySelectorAll('#sidebar-nav .nav-link').forEach(l=>l.classList.remove('active'));
    this.classList.add('active');
    if (window.innerWidth < 1200){ sideNav.classList.remove('show'); backdrop.classList.remove('active'); }
  });
});

/* Search suggest */
function attachSearch(inputEl, suggestEl){
  if (!inputEl || !suggestEl) return;
  const ADMIN_EP = "<?= BASE_URL ?>/backend/api/admin_search.php";
  const KARY_EP  = "<?= BASE_URL ?>/backend/api/karyawan_search.php";
  async function fetchResults(q){
    try { const r = await fetch(ADMIN_EP+"?q="+encodeURIComponent(q)); if(r.ok) return await r.json(); } catch(e){}
    try { const r2= await fetch(KARY_EP +"?q="+encodeURIComponent(q)); if(r2.ok) return await r2.json(); } catch(e){}
    return {ok:true,results:[]};
  }
  inputEl.addEventListener('input', async function(){
    const q = this.value.trim();
    if (q.length < 2){ suggestEl.classList.remove('visible'); suggestEl.innerHTML=''; return; }
    const data = await fetchResults(q);
    const arr = Array.isArray(data.results)?data.results:[];
    if (!arr.length){ suggestEl.innerHTML='<div class="search-empty">Tidak ada hasil.</div>'; suggestEl.classList.add('visible'); return; }
    let html=''; arr.forEach(r=>{
      html += `<div class="item" data-type="${r.type}" data-key="${r.key}">${r.label ?? ''} ${r.sub ? `<small>${r.sub}</small>` : ''}</div>`;
    });
    suggestEl.innerHTML = html; suggestEl.classList.add('visible');
    suggestEl.querySelectorAll('.item').forEach(it=>{
      it.addEventListener('click',()=>{
        const type = it.dataset.type, key = it.dataset.key;
        if (type==='order')      window.location = "<?= BASE_URL ?>/public/admin/orders.php?search="+encodeURIComponent(key);
        else if (type==='menu')  window.location = "<?= BASE_URL ?>/public/admin/catalog.php?search="+encodeURIComponent(key);
        else if (type==='user')  window.location = "<?= BASE_URL ?>/public/admin/users.php?search="+encodeURIComponent(key);
        else                     window.location = "<?= BASE_URL ?>/public/admin/orders.php?search="+encodeURIComponent(key);
      });
    });
  });
  document.addEventListener('click',(ev)=>{ if(!suggestEl.contains(ev.target) && ev.target!==inputEl){ suggestEl.classList.remove('visible'); }});
  document.getElementById('searchIcon')?.addEventListener('click',()=>inputEl.focus());
}
attachSearch(document.getElementById('searchInput'), document.getElementById('searchSuggest'));

/* notif badge */
async function refreshAdminNotifBadge(){
  const badge = document.getElementById('badgeNotif'); if (!badge) return;
  try {
    const res = await fetch("<?= BASE_URL ?>/backend/api/notifications.php?action=unread_count",{credentials:"same-origin"});
    if (!res.ok) return; const data = await res.json(); const count = data.count ?? 0;
    badge.classList.toggle('d-none', !(count>0));
  } catch(e){}
}
refreshAdminNotifBadge(); setInterval(refreshAdminNotifBadge, 30000);

/* ===== Custom Period Dropdown ===== */
const rangeBtn  = document.getElementById('rangeBtn');
const rangeMenu = document.getElementById('rangeMenu');
const rangeText = document.getElementById('rangeText');
const customModal = new bootstrap.Modal(document.getElementById('modalCustom'));
const customForm  = document.getElementById('customForm');

function applyRange(v){
  if (v === 'custom'){ customModal.show(); return; }
  const url = new URL(window.location.href);
  url.searchParams.set('range', v);
  url.searchParams.delete('from'); url.searchParams.delete('to');
  window.location.href = url.toString();
}

rangeBtn?.addEventListener('click', ()=>{
  const shown = rangeMenu.classList.toggle('show');
  rangeBtn.setAttribute('aria-expanded', shown ? 'true':'false');
});
document.addEventListener('click', (e)=>{
  if (!rangeMenu.contains(e.target) && e.target !== rangeBtn) {
    rangeMenu.classList.remove('show');
    rangeBtn.setAttribute('aria-expanded','false');
  }
});
document.addEventListener('keydown',(e)=>{
  if (e.key === 'Escape'){ rangeMenu.classList.remove('show'); rangeBtn.setAttribute('aria-expanded','false'); }
});
rangeMenu.querySelectorAll('.select-item').forEach(it=>{
  it.addEventListener('click', ()=>{
    rangeMenu.querySelectorAll('.select-item').forEach(x=>x.classList.remove('active'));
    it.classList.add('active');
    rangeText.textContent = it.textContent.trim();
    applyRange(it.dataset.value);
  });
});
customForm?.addEventListener('submit',(ev)=>{
  ev.preventDefault();
  const from = customForm.elements['from'].value, to = customForm.elements['to'].value;
  if (!from || !to) return;
  const url = new URL(window.location.href);
  url.searchParams.set('range','custom'); url.searchParams.set('from',from); url.searchParams.set('to',to);
  window.location.href = url.toString();
});

/* ====== DOWNLOAD CSV ====== */
document.getElementById('btnDownload')?.addEventListener('click',(e)=>{
  e.preventDefault();
  const url = new URL(window.location.href);
  url.searchParams.set('export','csv');
  window.location.href = url.toString();
});

/* ===== Charts ===== */
const labels  = <?= json_encode($labels) ?>;
const revenue = <?= json_encode($revenue) ?>;

new Chart(document.getElementById('revChart'),{
  type:'line',
  data:{ 
    labels,
    datasets:[{
      label:'Revenue', data:revenue, tension:.35, fill:true,
      borderColor:'#ffd54f', backgroundColor:'rgba(255,213,79,.18)',
      pointRadius:4, pointBackgroundColor:'#ffd54f', pointBorderColor:'#ffd54f'
    }]
  },
  options:{
    maintainAspectRatio:false,
    responsive:true,
    plugins:{ legend:{ display:false } },
    scales:{
      y:{
        ticks:{ callback:v=> new Intl.NumberFormat('id-ID',{style:'currency',currency:'IDR',maximumFractionDigits:0}).format(v) },
        grid:{ color:'rgba(17,24,39,.06)' }
      },
      x:{ grid:{ display:false } }
    }
  }
});

/* Donut distribusi status */
const dLabels = <?= json_encode($distLabels) ?>;
const dOrders = <?= json_encode($distOrders) ?>;
new Chart(document.getElementById('statusDonut'),{
  type:'doughnut',
  data:{
    labels:dLabels,
    datasets:[{
      data:dOrders,
      backgroundColor:['#ffe761','#eae3c0','#facf43','#fdeb9e','#edde3bff']
    }]
  },
  options:{
    responsive:true,
    cutout:'60%',
    plugins:{ legend:{ position:'bottom' } }
  }
});

// Legend sederhana
(function(){
  const el = document.getElementById('statusLegend'); if (!el) return;
  const parts = dLabels.map((lb,i)=> `${lb}: <b>${dOrders[i]??0}</b>`);
  el.innerHTML = parts.join(' &nbsp;&nbsp;•&nbsp;&nbsp; ');
})();
</script>
</body>
</html>

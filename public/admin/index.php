<?php
// public/admin/index.php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../../backend/config.php';

/* ===== Guard role: hanya admin ===== */
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
  header('Location: ' . BASE_URL . '/public/login.html');
  exit;
}

/* ===== User info ===== */
$user = [
  'id'    => (int)($_SESSION['user_id'] ?? 0),
  'name'  => (string)($_SESSION['user_name'] ?? ''),
  'email' => (string)($_SESSION['user_email'] ?? ''),
  'role'  => (string)($_SESSION['user_role'] ?? ''),
];
$userName  = htmlspecialchars($user['name'],  ENT_QUOTES, 'UTF-8');
$userEmail = htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8');

/* ===== KPI ===== */
$kpi = [
  'total_orders'     => 0,
  'orders_today'     => 0,
  'menu_count'       => 0,
  'active_customers' => 0,
];

$res = $conn->query("SELECT COUNT(*) AS c FROM orders");
$kpi['total_orders'] = (int)($res?->fetch_assoc()['c'] ?? 0);

$today = (new DateTime('today'))->format('Y-m-d');
$stmt  = $conn->prepare("SELECT COUNT(*) AS c FROM orders WHERE DATE(created_at)=?");
$stmt->bind_param('s', $today);
$stmt->execute();
$kpi['orders_today'] = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$res = $conn->query("SELECT COUNT(*) AS c FROM menu");
$kpi['menu_count'] = (int)($res?->fetch_assoc()['c'] ?? 0);

$res = $conn->query("SELECT COUNT(*) AS c FROM users WHERE status='active' AND role='customer'");
$kpi['active_customers'] = (int)($res?->fetch_assoc()['c'] ?? 0);

/* ===== Chart data: Revenue 7 hari terakhir ===== */
$labels  = [];
$revenue = [];
$map     = [];

for ($i = 6; $i >= 0; $i--) {
  $d = (new DateTime("today -$i day"))->format('Y-m-d');
  $labels[] = $d;
  $map[$d]  = 0.0;
}

$sql = "
  SELECT DATE(created_at) AS d, SUM(total) AS s
  FROM orders
  WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
  GROUP BY DATE(created_at)
  ORDER BY d ASC
";
$res = $conn->query($sql);
while ($row = $res?->fetch_assoc()) {
  $map[$row['d']] = (float)$row['s'];
}
foreach ($labels as $d) {
  $revenue[] = $map[$d];
}

/* ===== Distribusi status (Today) =====
   Ambil dari orders.order_status untuk HARI INI saja.
   Bucket final: new, processing, ready, completed, cancelled
============================================================= */
$statusBuckets = [
  'new'        => 0,
  'processing' => 0,
  'ready'      => 0,
  'completed'  => 0,
  'cancelled'  => 0,  // akan menampung cancelled/canceled/failed
];

$stmt = $conn->prepare("
  SELECT LOWER(order_status) AS s, COUNT(*) AS c
  FROM orders
  WHERE DATE(created_at) = ?
  GROUP BY LOWER(order_status)
");
$stmt->bind_param('s', $today);
$stmt->execute();
$rst = $stmt->get_result();
while ($row = $rst?->fetch_assoc()) {
  $s = (string)$row['s'];
  $c = (int)$row['c'];

  if (isset($statusBuckets[$s])) {
    $statusBuckets[$s] += $c;
  } else {
    // Normalisasi status lain yang bermakna "cancel"
    if (in_array($s, ['canceled','cancel','cancelled','failed','void','refunded'], true)) {
      $statusBuckets['cancelled'] += $c;
    }
    // status lain diabaikan agar tidak mengubah layout/legenda
  }
}
$stmt->close();

// urutan data untuk chart donut
$distToday = [
  $statusBuckets['new'],
  $statusBuckets['processing'],
  $statusBuckets['ready'],
  $statusBuckets['completed'],
  $statusBuckets['cancelled'],
];
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin Desk — Caffora</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <script src="https://code.iconify.design/2/2.2.1/iconify.min.js"></script>

    <style>
    :root{
      --gold:#ffd54f;
      --gold-200:#ffe883;
      --gold-soft:#f4d67a;
      --ink:#111827;
      --muted:#6B7280;
      --brown:#4B3F36;
      --radius:18px;
      --sidebar-w:320px;
    }
    body{
      background:#FAFAFA;
      color:var(--ink);
      font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial;
    }

    /* ===== Sidebar ===== */
    .sidebar{
      position:fixed;
      left:-320px;
      top:0;
      bottom:0;
      width:var(--sidebar-w);
      background:#fff;
      border-right:1px solid rgba(0,0,0,.04);
      transition:left .25s ease;
      z-index:1050;
      padding:14px 18px 18px;
      overflow-y:auto;
    }
    .sidebar.show{ left:0; }

    .sidebar-head{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:10px;
      margin-bottom:10px;
    }
    .sidebar-inner-toggle,
    .sidebar-close-btn{
      background:transparent;border:0;
      width:40px;height:36px;
      display:grid;place-items:center;
    }

    .hamb-icon{
      width:24px;
      height:20px;
      display:flex;
      flex-direction:column;
      justify-content:space-between;
      gap:4px;
    }
    .hamb-icon span{
      height:2px;
      background:var(--brown);
      border-radius:99px;
    }

    .sidebar .nav-link{
      display:flex; align-items:center; gap:12px;
      padding:12px 14px; border-radius:16px;
      color:#111; font-weight:600;
      text-decoration:none;
      background:transparent;
      user-select:none;
    }
    .sidebar .nav-link:hover,
    .sidebar .nav-link:focus,
    .sidebar .nav-link:active{
      background:rgba(255,213,79,0.25);
      color:#111;
      outline:none;
      box-shadow:none;
    }
    .sidebar hr{ border-color:rgba(0,0,0,.05); opacity:1; }

    /* ===== content ===== */
    .content{ margin-left:0; padding:16px 14px 40px; }

    /* ===== topbar ===== */
    .topbar{
      display:flex;
      align-items:center;
      gap:12px;
      margin-bottom:16px;
    }
    .btn-menu{
      background:transparent;
      border:0;
      width:40px;height:38px;
      display:grid;place-items:center;
      flex:0 0 auto;
    }

    /* ===== SEARCH ===== */
    .search-box{ position:relative; flex:1 1 auto; min-width:0; }
    .search-input{
      height:46px; width:100%;
      border-radius:9999px;
      padding-left:16px; padding-right:44px;
      border:1px solid #e5e7eb; background:#fff;
      outline:none !important; transition:border-color .12s ease;
    }
    .search-input:focus{ border-color:var(--gold-soft) !important; background:#fff; box-shadow:none !important; }
    .search-icon{
      position:absolute; right:16px; top:50%;
      transform:translateY(-50%); font-size:1.1rem; color:var(--brown); cursor:pointer;
    }
    .search-suggest{
      position:absolute; top:100%; left:0; margin-top:6px; background:#fff;
      border:1px solid rgba(247,215,141,.8); border-radius:16px; box-shadow:0 12px 28px rgba(0,0,0,.08);
      width:100%; max-height:280px; overflow-y:auto; display:none; z-index:40;
    }
    .search-suggest.visible{ display:block; }
    .search-suggest .item{ padding:10px 14px 6px; border-bottom:1px solid rgba(0,0,0,.03); cursor:pointer; }
    .search-suggest .item:last-child{ border-bottom:0; }
    .search-suggest .item:hover{ background:#fffbea; }
    .search-suggest .item small{ display:block; color:#6b7280; font-size:.74rem; margin-top:2px; }
    .search-empty{ padding:12px 14px; color:#6b7280; font-size:.8rem; }

    .top-actions{ display:flex; align-items:center; gap:14px; flex:0 0 auto; }
    .icon-btn{
      width:38px; height:38px; border-radius:999px;
      display:flex; align-items:center; justify-content:center;
      color:var(--brown); text-decoration:none; background:transparent; outline:none;
    }
    .icon-btn:focus,.icon-btn:active{ outline:none; box-shadow:none; color:var(--brown); }

    #btnBell{ position:relative; }
    #badgeNotif.notif-dot{
      position:absolute; top:3px; right:5px; width:8px; height:8px; background:#4b3f36; border-radius:50%;
      display:inline-block; box-shadow:0 0 0 1.5px #fff;
    }
    #badgeNotif.d-none{ display:none !important; }

    /* KPI & cards */
    .kpi,.cardx{ background:#fff; border:1px solid #f7d78d; border-radius:var(--radius); padding:18px; }
    .kpi .ico{ width:44px;height:44px;border-radius:12px; background:var(--gold-200);display:grid;place-items:center; }

    /* charts */
    .charts-row .cardx.chart-wrap{ height:100%; display:flex; flex-direction:column; gap:12px; }
    .charts-row .chart-body{ flex:1 1 auto; }
    .charts-row canvas{ width:100% !important; height:100% !important; max-height:300px; }

    .backdrop-mobile{ display:none; }
    .backdrop-mobile.active{ display:block; position:fixed; inset:0; background:rgba(0,0,0,.35); z-index:1040; }

    @media (min-width: 992px){ .content{ padding:20px 26px 50px; } .search-box{ max-width:1100px; } }

    @media (min-width: 768px) and (max-width: 991.98px) {
      .content{ padding:18px 16px 50px; }
      .charts-row{ display:grid; grid-template-columns:minmax(0,1.05fr) minmax(0,0.95fr); gap:14px; align-items:stretch; }
      .charts-row > [class^="col-"], .charts-row > [class*=" col-"]{ width:100%;flex:0 0 auto; }
      .charts-row .cardx.chart-wrap{ padding:14px; }
      .charts-row canvas{ max-height:240px!important; }
    }

    @media (min-width: 992px) and (max-width: 1199.98px){
      .charts-row{ display:grid; grid-template-columns:minmax(0,0.6fr) minmax(0,0.4fr); gap:16px; align-items:stretch; }
      .charts-row > [class^="col-"], .charts-row > [class*=" col-"]{ width:100%;flex:0 0 auto; }
      .charts-row .cardx.chart-wrap{ padding:16px 16px 14px; height:100%; }
      .charts-row canvas{ max-height:300px!important; }
    }
  </style>
</head>
<body>


<div id="backdrop" class="backdrop-mobile"></div>
      <!-- sidebar -->
<aside class="sidebar" id="sideNav">
  <div class="sidebar-head">
    <button class="sidebar-inner-toggle" id="toggleSidebarInside" aria-label="Tutup menu"></button>
    <button class="sidebar-close-btn" id="closeSidebar" aria-label="Tutup menu">
      <i class="bi bi-x-lg"></i>
    </button>
  </div>

  <nav class="nav flex-column gap-2" id="sidebar-nav">
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/index.php">
      <i class="bi bi-house-door"></i> Dashboard
    </a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/orders.php">
      <i class="bi bi-receipt"></i> Orders
    </a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/catalog.php">
      <i class="bi bi-box-seam"></i> Catalog
    </a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/users.php">
      <i class="bi bi-people"></i> Users
    </a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/finance.php">
      <i class="bi bi-cash-coin"></i> Finance
    </a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/notifications_send.php">
      <i class="bi bi-megaphone"></i> Kirim Notifikasi
    </a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/audit.php">
      <i class="bi bi-shield-check"></i> Audit Log
    </a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/settings.php">
      <i class="bi bi-gear"></i> Settings
    </a>

    <hr>

    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/help.php">
      <i class="bi bi-question-circle"></i> Help Center
    </a>

    <a class="nav-link" href="<?= BASE_URL ?>/backend/logout.php">
      <i class="bi bi-box-arrow-right"></i> Logout
    </a>
  </nav>
</aside>


<main class="content">
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
      <a id="btnBell" class="icon-btn position-relative text-decoration-none" href="<?= BASE_URL ?>/public/admin/notifications.php" aria-label="Notifikasi">
        <span class="iconify" data-icon="mdi:bell-outline" data-width="24" data-height="24"></span>
        <span id="badgeNotif" class="notif-dot d-none"></span>
      </a>
      <a class="icon-btn text-decoration-none" href="<?= BASE_URL ?>/public/admin/settings.php" aria-label="Akun">
        <span class="iconify" data-icon="mdi:account-circle-outline" data-width="28" data-height="28"></span>
      </a>
    </div>
  </div>

  <h2 class="fw-bold mb-3">Dashboard Admin</h2>

  <div class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-lg-3">
      <div class="kpi d-flex align-items-center gap-3">
        <div class="ico"><i class="bi bi-list-ul"></i></div>
        <div>
          <div class="text-muted small">Total Pesanan</div>
          <div class="fs-4 fw-bold"><?= number_format($kpi['total_orders']) ?></div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-6 col-lg-3">
      <div class="kpi d-flex align-items-center gap-3">
        <div class="ico"><i class="bi bi-calendar2-day"></i></div>
        <div>
          <div class="text-muted small">Pesanan Hari Ini</div>
          <div class="fs-4 fw-bold"><?= number_format($kpi['orders_today']) ?></div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-6 col-lg-3">
      <div class="kpi d-flex align-items-center gap-3">
        <div class="ico"><i class="bi bi-box"></i></div>
        <div>
          <div class="text-muted small">Menu Tersedia</div>
          <div class="fs-4 fw-bold"><?= number_format($kpi['menu_count']) ?></div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-6 col-lg-3">
      <div class="kpi d-flex align-items-center gap-3">
        <div class="ico"><i class="bi bi-people"></i></div>
        <div>
          <div class="text-muted small">Pelanggan Aktif</div>
          <div class="fs-4 fw-bold"><?= number_format($kpi['active_customers']) ?></div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 charts-row align-items-stretch">
    <div class="col-12 col-xl-7 d-flex">
      <div class="cardx chart-wrap w-100">
        <h6 class="fw-bold mb-2">Revenue 7 Hari Terakhir</h6>
        <div class="chart-body">
          <canvas id="revChart"></canvas>
        </div>
      </div>
    </div>
    <div class="col-12 col-xl-5 d-flex">
      <div class="cardx chart-wrap w-100">
        <h6 class="fw-bold mb-2">Distribusi Status Pesanan (Hari Ini)</h6>
        <div class="chart-body donut-holder">
          <canvas id="distChart"></canvas>
        </div>
      </div>
    </div>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ===== sidebar toggle ===== */
const sideNav = document.getElementById('sideNav');
const backdrop = document.getElementById('backdrop');
document.getElementById('openSidebar')?.addEventListener('click', () => { sideNav.classList.add('show'); backdrop.classList.add('active'); });
document.getElementById('closeSidebar')?.addEventListener('click', () => { sideNav.classList.remove('show'); backdrop.classList.remove('active'); });
document.getElementById('toggleSidebarInside')?.addEventListener('click', () => { sideNav.classList.remove('show'); backdrop.classList.remove('active'); });
backdrop?.addEventListener('click', () => { sideNav.classList.remove('show'); backdrop.classList.remove('active'); });

/* ===== nav active ===== */
document.querySelectorAll('#sidebar-nav .nav-link').forEach(a => {
  a.addEventListener('click', function(){
    document.querySelectorAll('#sidebar-nav .nav-link').forEach(l => l.classList.remove('active'));
    this.classList.add('active');
    if (window.innerWidth < 1200) { sideNav.classList.remove('show'); backdrop.classList.remove('active'); }
  });
});

/* ===== SEARCH (admin → fallback karyawan) ===== */
function attachSearch(inputEl, suggestEl){
  if (!inputEl || !suggestEl) return;
  const ADMIN_EP = "<?= BASE_URL ?>/backend/api/admin_search.php";
  const KARY_EP  = "<?= BASE_URL ?>/backend/api/karyawan_search.php";

  async function fetchResults(q){
    try { const r = await fetch(ADMIN_EP + "?q=" + encodeURIComponent(q), { headers:{Accept:"application/json"} }); if (r.ok) return await r.json(); } catch(e){}
    try { const r2 = await fetch(KARY_EP + "?q=" + encodeURIComponent(q), { headers:{Accept:"application/json"} }); if (r2.ok) return await r2.json(); } catch(e){}
    return { ok:true, results:[] };
  }

  inputEl.addEventListener('input', async function(){
    const q = this.value.trim();
    if (q.length < 2){ suggestEl.classList.remove('visible'); suggestEl.innerHTML=''; return; }
    const data = await fetchResults(q);
    const arr = Array.isArray(data.results) ? data.results : [];
    if (!arr.length){ suggestEl.innerHTML = '<div class="search-empty">Tidak ada hasil.</div>'; suggestEl.classList.add('visible'); return; }
    let html=''; arr.forEach(r => { html += `<div class="item" data-type="${r.type}" data-key="${r.key}">${r.label ?? ''}${r.sub ? `<small>${r.sub}</small>` : ''}</div>`; });
    suggestEl.innerHTML = html; suggestEl.classList.add('visible');
    suggestEl.querySelectorAll('.item').forEach(it => {
      it.addEventListener('click', () => {
        const type = it.dataset.type; const key = it.dataset.key;
        if (type === 'order')      window.location = "<?= BASE_URL ?>/public/admin/orders.php?search="  + encodeURIComponent(key);
        else if (type === 'menu')  window.location = "<?= BASE_URL ?>/public/admin/catalog.php?search=" + encodeURIComponent(key);
        else if (type === 'user')  window.location = "<?= BASE_URL ?>/public/admin/users.php?search="   + encodeURIComponent(key);
        else                       window.location = "<?= BASE_URL ?>/public/admin/orders.php?search="  + encodeURIComponent(key);
      });
    });
  });

  document.addEventListener('click', (ev) => { if (!suggestEl.contains(ev.target) && ev.target !== inputEl){ suggestEl.classList.remove('visible'); } });
  document.getElementById('searchIcon')?.addEventListener('click', () => inputEl.focus());
}
attachSearch(document.getElementById('searchInput'), document.getElementById('searchSuggest'));

/* ===== BADGE NOTIF ===== */
async function refreshAdminNotifBadge() {
  const badge = document.getElementById('badgeNotif'); if (!badge) return;
  try {
    const res = await fetch("<?= BASE_URL ?>/backend/api/notifications.php?action=unread_count", { credentials:"same-origin" });
    if (!res.ok) return;
    const data = await res.json();
    const count = data.count ?? 0;
    if (count > 0) badge.classList.remove('d-none'); else badge.classList.add('d-none');
  } catch(err){}
}
refreshAdminNotifBadge(); setInterval(refreshAdminNotifBadge, 30000);

/* ===== CHARTS ===== */
const labels  = <?= json_encode($labels) ?>;
const revenue = <?= json_encode($revenue) ?>;
const dist    = <?= json_encode($distToday) ?>;

/* Line chart */
new Chart(document.getElementById('revChart'), {
  type: 'line',
  data: {
    labels,
    datasets: [{
      label: 'Revenue',
      data: revenue,
      tension: .35,
      fill: true,
      borderColor: '#ffd54f',
      backgroundColor: 'rgba(255,213,79,.18)',
      pointRadius: 4,
      pointBackgroundColor: '#ffd54f',
      pointBorderColor: '#ffd54f'
    }]
  },
  options: {
    maintainAspectRatio: false,
    plugins: { legend: { display:false } },
    scales: {
      y: {
        ticks: {
          callback: v => new Intl.NumberFormat('id-ID', { style:'currency', currency:'IDR', maximumFractionDigits:0 }).format(v)
        },
        grid: { color:'rgba(17,24,39,.06)' }
      },
      x: { grid: { display:false } }
    }
  }
});

/* Donut chart (Today) */
new Chart(document.getElementById('distChart'), {
  type: 'doughnut',
  data: {
    labels: ['New','Processing','Ready','Completed','Cancelled'],
    datasets: [{
      data: dist,
      borderWidth: 0,
       backgroundColor:['#ffe761','#eae3c0','#facf43','#fdeb9e','#edde3bff']
    }]
  },
  options: {
    maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom' } }
  }
});
</script>
</body>
</html>

<?php
// public/karyawan/stock.php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../../backend/auth_guard.php';
require_login(['karyawan','admin']);
require_once __DIR__ . '/../../backend/config.php'; // BASE_URL, $conn, h()

// ===== data user utk topbar =====
$userId     = (int)($_SESSION['user_id'] ?? 0);
$userName   = $_SESSION['user_name']  ?? 'Staff';
$userEmail  = $_SESSION['user_email'] ?? '';
$userAvatar = $_SESSION['user_avatar'] ?? '';
$initials   = strtoupper(substr($userName ?: 'U', 0, 2));

$avatarUrl = '';
$hasAvatar = false;
if ($userAvatar) {
  if (str_starts_with($userAvatar, 'http')) {
    $avatarUrl = $userAvatar;
  } else {
    $avatarUrl = rtrim(BASE_URL, '/') . (str_starts_with($userAvatar, '/') ? $userAvatar : '/' . $userAvatar);
  }
  $hasAvatar = true;
}

// ===== helper =====
function rupiah($n): string { return 'Rp ' . number_format((float)$n, 0, ',', '.'); }

// ====== Actions: update status stok ======
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id   = (int)($_POST['id'] ?? 0);
  $stat = ($_POST['stock_status'] ?? '') === 'Sold Out' ? 'Sold Out' : 'Ready';

  if ($id > 0) {
    $stmt = $conn->prepare("UPDATE menu SET stock_status=? WHERE id=?");
    $stmt->bind_param('si', $stat, $id);
    $ok = $stmt->execute();
    $stmt->close();
    $msg = $ok ? 'Status stok diperbarui.' : 'Gagal memperbarui status.';
  } else {
    $msg = 'Data tidak valid.';
  }
}

// ====== Filter (GET) ======
$q   = trim((string)($_GET['q'] ?? ''));

$where = [];
$types = '';
$params = [];

if ($q !== '') {
  $where[] = '(name LIKE ? OR category LIKE ?)';
  $like = '%' . $q . '%';
  $params[] = $like;
  $params[] = $like;
  $types .= 'ss';
}

$sql = 'SELECT * FROM menu';
if ($where) {
  $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY created_at DESC';

$menus = [];
$stmt = $conn->prepare($sql);
if ($stmt) {
  if ($params) {
    $stmt->bind_param($types, ...$params);
  }
  $stmt->execute();
  $res = $stmt->get_result();
  $menus = $res?->fetch_all(MYSQLI_ASSOC) ?? [];
  $stmt->close();
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Menu Stock — Karyawan Desk</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://code.iconify.design/2/2.2.1/iconify.min.js"></script>

  <style>
    :root{
      --gold:#ffd54f;
      --gold-200:#ffe883;
      --gold-soft:#f4d67a;
      --ink:#111827;
      --muted:#6b7280;
      --brown:#4B3F36;
      --radius:18px;
      --sidebar-w:320px;
    }
    body{
      background:#FAFAFA;
      color:var(--ink);
      font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial
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


    /* ===== CONTENT ===== */
    .content{ margin-left:0; padding:16px 14px 40px; }

    /* ===== TOPBAR ===== */
    .topbar{
      display:flex;
      align-items:center;
      gap:12px;
      margin-bottom:16px;
    }
    .btn-menu{
      background:transparent;
      border:0;
      width:40px;
      height:38px;
      display:grid;
      place-items:center;
      flex:0 0 auto;
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

    
    /* ===== SEARCH (disamain ke stock) ===== */
    .search-box{
      position:relative;
      flex:1 1 auto;          /* ini kunci: biar dia ngambil semua ruang sisa */
      min-width:0;            /* biar flex nggak maksa kecil */
    }
    .search-input{
      height:46px;
      width:100%;
      border-radius:9999px;
      padding-left:16px;
      padding-right:44px;
      border:1px solid #e5e7eb;
      background:#fff;
      outline:none !important;
      transition:border-color .1s ease;
    }
    .search-input:focus{
      border-color:var(--gold-soft) !important;
      background:#fff;
      box-shadow:none !important;
    }
    .search-icon{
      position:absolute;
      right:16px;
      top:50%;
      transform:translateY(-50%);
      font-size:1.1rem;
      color:var(--brown);
      cursor:pointer;
    }

    /* dropdown hasil */
    .search-suggest{
      position:absolute;
      top:100%;
      left:0;
      margin-top:6px;
      background:#fff;
      border:1px solid rgba(247,215,141,.8);
      border-radius:16px;
      box-shadow:0 12px 28px rgba(0,0,0,.08);
      width:100%;
      max-height:280px;
      overflow-y:auto;
      display:none;
      z-index:40;
    }
    .search-suggest.visible{ display:block; }
    .search-suggest .item{
      padding:10px 14px 6px;
      border-bottom:1px solid rgba(0,0,0,.03);
      cursor:pointer;
    }
    .search-suggest .item:last-child{ border-bottom:0; }
    .search-suggest .item:hover{ background:#fffbea; }
    .search-suggest .item small{
      display:block;
      color:#6b7280;
      font-size:.74rem;
      margin-top:2px;
    }
    .search-empty{
      padding:12px 14px;
      color:#6b7280;
      font-size:.8rem;
    }
    .top-actions{
      display:flex;
      align-items:center;
      gap:14px;
      flex:0 0 auto;
    }
    .icon-btn{
      width:38px;
      height:38px;
      border-radius:999px;
      display:flex;
      align-items:center;
      justify-content:center;
      color:var(--brown);
      text-decoration:none;
      background:transparent;
    }

    /* notif dot */
    #btnBell{ position:relative; }
    #badgeNotif.notif-dot{
      position:absolute;
      top:3px;
      right:5px;
      width:8px;
      height:8px;
      background:#4b3f36;
      border-radius:50%;
      display:inline-block;
      box-shadow:0 0 0 1.5px #fff;
    }
    #badgeNotif.d-none{ display:none !important; }
      @media (max-width: 600px) {
        #badgeNotif.notif-dot {
          width: 10px;
          height: 10px;
          top: 4px;
          right: 3px;
        }
      }

    .avatar{
      width:44px;
      height:44px;
      border-radius:50%;
      background:var(--gold);
      display:grid;
      place-items:center;
      font-weight:800;
      color:#111;
      border:2px solid #fff;
      background-size:cover;
      background-position:center;
    }

    /* backdrop mobile */
    .backdrop-mobile{ display:none; }
    .backdrop-mobile.active{
      display:block;
      position:fixed;
      inset:0;
      background:rgba(0,0,0,.35);
      z-index:1040;
    }

    /* ===== TABLE CARD ===== */
    .cardx{
      background:#fff;
      border:1px solid #f7d78d;
      border-radius:var(--radius);
      padding:16px;
    }
    .table thead th{
      background:#fffdf0;
      border-bottom:0;
    }
    .thumb{
      width:48px;
      height:48px;
      object-fit:cover;
      border:1px solid #e5e7eb;
      border-radius:12px;
      background:#fff;
    }

    @media (min-width: 992px){
  .content{
    padding-left: 28px !important;   /* atau 26px jika mau persis dashboard */
    padding-right: 28px !important;  /* atau 26px */
    padding-top: 20px;
    padding-bottom: 60px;
  }
  /* optional: biar search bar tak memaksa lebar konten */
  .search-box{ max-width: 1100px !important; } /* sama seperti dashboard */
}

@media (min-width: 1200px){
  .content{
    padding-left: 26px !important;   /* sedikit lebih rapat di layar lebar */
    padding-right: 26px !important;
  }
}

  </style>
</head>
<body>

<!-- backdrop -->
<div id="backdrop" class="backdrop-mobile"></div>

<!-- ===== SIDEBAR ===== -->
<aside class="sidebar" id="sideNav">
  <div class="sidebar-head">
    <button class="sidebar-inner-toggle" id="toggleSidebarInside" aria-label="Tutup menu"></button>
    <button class="sidebar-close-btn" id="closeSidebar" aria-label="Tutup menu">
      <i class="bi bi-x-lg"></i>
    </button>
  </div>
  <nav class="nav flex-column gap-2" id="sidebar-nav">
    <a class="nav-link" href="<?= BASE_URL ?>/public/karyawan/index.php"><i class="bi bi-house-door"></i> Dashboard</a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/karyawan/orders.php"><i class="bi bi-receipt"></i> Orders</a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/karyawan/stock.php"><i class="bi bi-box-seam"></i> Menu Stock</a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/karyawan/settings.php"><i class="bi bi-gear"></i> Settings</a>
    <hr>
    <a class="nav-link" href="#"><i class="bi bi-question-circle"></i> Help Center</a>
    <a class="nav-link" href="<?= BASE_URL ?>/backend/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
  </nav>
</aside>

<!-- ===== CONTENT ===== -->
<main class="content">

  <!-- TOPBAR -->
  <div class="topbar">
    <button class="btn-menu" id="openSidebar" aria-label="Buka menu">
      <div class="hamb-icon">
        <span></span><span></span><span></span>
      </div>
    </button>

    <div class="search-box">
      <input class="search-input" id="searchInput" placeholder="Search..." value="<?= htmlspecialchars($q, ENT_QUOTES) ?>" autocomplete="off" />
      <i class="bi bi-search search-icon" id="searchIcon"></i>
    </div>

    <div class="top-actions">
      <a id="btnBell" class="icon-btn position-relative text-decoration-none" href="<?= BASE_URL ?>/public/karyawan/notifications.php" aria-label="Notifikasi">
        <span class="iconify" data-icon="mdi:bell-outline" data-width="24" data-height="24"></span>
        <span id="badgeNotif" class="notif-dot d-none"></span>
      </a>
   <!-- Profil (ikon seperti customer) -->
<a
  href="<?= BASE_URL ?>/public/karyawan/settings.php"
  class="icon-btn text-decoration-none"
  aria-label="Akun"
>
  <span
    class="iconify"
    data-icon="mdi:account-circle-outline"
    data-width="28"
    data-height="28"
       ></span>
      </a>
    </div>
  </div>

  <h2 class="fw-bold mb-3">Stok Menu</h2>

  <?php if ($msg): ?>
    <div class="alert alert-warning py-2"><?= h($msg) ?></div>
  <?php endif; ?>

  <!-- TABEL -->
  <div class="cardx">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th>ID</th>
            <th>Gambar</th>
            <th>Nama</th>
            <th>Kategori</th>
            <th>Harga</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$menus): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada data.</td></tr>
        <?php else: foreach ($menus as $m): ?>
          <tr>
            <td><?= (int)$m['id'] ?></td>
            <td>
              <?php if (!empty($m['image'])): ?>
                <img class="thumb" src="<?= h(BASE_URL . '/public/' . ltrim($m['image'],'/')) ?>" alt="">
              <?php endif; ?>
            </td>
            <td><?= h($m['name']) ?></td>
            <td><?= h($m['category']) ?></td>
            <td><?= rupiah($m['price']) ?></td>
            <td>
              <?= ($m['stock_status'] === 'Ready')
                ? '<span class="badge text-bg-success">Ready</span>'
                : '<span class="badge text-bg-danger">Sold Out</span>' ?>
            </td>
            <td>
              <form class="d-inline" method="post">
                <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                <input type="hidden" name="stock_status" value="<?= $m['stock_status']==='Ready' ? 'Sold Out' : 'Ready' ?>">
                <?php if ($m['stock_status']==='Ready'): ?>
                  <button class="btn btn-sm btn-outline-danger" type="submit">
                    <i class="bi bi-x-circle me-1"></i>
                  </button>
                <?php else: ?>
                  <button class="btn btn-sm btn-outline-success" type="submit">
                    <i class="bi bi-check2-circle me-1"></i> 
                <?php endif; ?>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// sidebar toggle
const sideNav=document.getElementById('sideNav');
const backdrop=document.getElementById('backdrop');
document.getElementById('openSidebar')?.addEventListener('click',()=>{sideNav.classList.add('show');backdrop.classList.add('active');});
document.getElementById('closeSidebar')?.addEventListener('click',()=>{sideNav.classList.remove('show');backdrop.classList.remove('active');});
document.getElementById('toggleSidebarInside')?.addEventListener('click',()=>{sideNav.classList.remove('show');backdrop.classList.remove('active');});
backdrop.addEventListener('click',()=>{sideNav.classList.remove('show');backdrop.classList.remove('active');});

// search
const searchInput=document.getElementById('searchInput');
const searchIcon=document.getElementById('searchIcon');
function goSearch(){
  const q=searchInput.value.trim();
  const url=new URL(window.location.href);
  if(q) url.searchParams.set('q',q); else url.searchParams.delete('q');
  window.location.href=url.toString();
}
searchIcon.addEventListener('click',goSearch);
searchInput.addEventListener('keydown',e=>{
  if(e.key==='Enter'){e.preventDefault();goSearch();}
});

// notif badge
async function refreshKaryawanNotifBadge(){
  const badge=document.getElementById('badgeNotif');
  if(!badge)return;
  try{
    const res=await fetch("<?= BASE_URL ?>/backend/api/notifications.php?action=unread_count",{credentials:"same-origin"});
    if(!res.ok)return;
    const data=await res.json();
    const count=data.count??0;
    if(count>0){badge.classList.remove('d-none');}else{badge.classList.add('d-none');}
  }catch(err){}
}
refreshKaryawanNotifBadge();
setInterval(refreshKaryawanNotifBadge,30000);
</script>
</body>
</html>

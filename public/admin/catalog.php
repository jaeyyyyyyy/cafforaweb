<?php
// public/admin/catalog.php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../../backend/config.php';
require_once __DIR__ . '/../../backend/auth_guard.php';
require_once __DIR__ . '/../../backend/helpers.php';
require_login(['admin']);

// fallback helper
if (!function_exists('h')) {
  function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('rupiah')) {
  function rupiah($n): string { return 'Rp ' . number_format((float)$n, 0, ',', '.'); }
}

$msg = $_GET['msg'] ?? '';

/* ===== ACTIONS (CRUD) ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $act   = $_POST['action'] ?? '';
  $id    = (int)($_POST['id'] ?? 0);
  $name  = trim((string)($_POST['name'] ?? ''));
  $cat   = strtolower(trim((string)($_POST['category'] ?? '')));
  $cat   = in_array($cat, ['food','pastry','drink'], true) ? $cat : 'food';
  $price = (float)($_POST['price'] ?? 0);
  $stat  = ($_POST['stock_status'] ?? 'Ready') === 'Sold Out' ? 'Sold Out' : 'Ready';

  // upload gambar (opsional)
  $imagePath = null;
  if (!empty($_FILES['image']['name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg','jpeg','png','webp'], true)) {
      $updir = __DIR__ . '/../../public/uploads/menu';
      if (!is_dir($updir)) @mkdir($updir, 0777, true);
      $new = 'm_' . time() . '_' . mt_rand(1000,9999) . '.' . $ext;
      if (move_uploaded_file($_FILES['image']['tmp_name'], $updir . '/' . $new)) {
        $imagePath = 'uploads/menu/' . $new;
      }
    }
  }

  if ($act === 'add') {
    $stmt = $conn->prepare("INSERT INTO menu(name,category,image,price,stock_status) VALUES (?,?,?,?,?)");
    $stmt->bind_param('sssds', $name, $cat, $imagePath, $price, $stat);
    $ok = $stmt->execute();
    $stmt->close();
    header('Location: '.$_SERVER['PHP_SELF'].'?msg='.urlencode($ok ? 'Menu ditambahkan.' : 'Gagal menambah menu.'));
    exit;
  }

  if ($act === 'edit' && $id > 0) {
    if ($imagePath) {
      $stmt = $conn->prepare("UPDATE menu SET name=?, category=?, image=?, price=?, stock_status=? WHERE id=?");
      $stmt->bind_param('sssdsi', $name, $cat, $imagePath, $price, $stat, $id);
    } else {
      $stmt = $conn->prepare("UPDATE menu SET name=?, category=?, price=?, stock_status=? WHERE id=?");
      $stmt->bind_param('ssdsi', $name, $cat, $price, $stat, $id);
    }
    $ok = $stmt->execute();
    $stmt->close();
    header('Location: '.$_SERVER['PHP_SELF'].'?msg='.urlencode($ok ? 'Menu diperbarui.' : 'Gagal mengedit menu.'));
    exit;
  }
}

// DELETE
if (isset($_GET['delete'])) {
  $id = (int)$_GET['delete'];
  $stmt = $conn->prepare("DELETE FROM menu WHERE id=?");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $stmt->close();
  header('Location: '.$_SERVER['PHP_SELF'].'?msg='.urlencode('Menu dihapus.'));
  exit;
}

/* ===== DATA ===== */
$res   = $conn->query("SELECT * FROM menu ORDER BY created_at DESC");
$menus = $res?->fetch_all(MYSQLI_ASSOC) ?? [];
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Catalog — Admin Desk</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://code.iconify.design/2/2.2.1/iconify.min.js"></script>
  <style>
    :root{
      --gold:#FFD54F;
      --gold-soft:#F6D472;
      --brown:#4B3F36;
      --ink:#111827;
      --muted:#6B7280;
      --radius:18px;
      --sidebar-w:320px;
      --input-border:#E8E2DA;
      --bg:#FAFAFA;
      --btn-radius:14px; /* konsisten dengan users */
    }
    *{ box-sizing:border-box; font-family:Poppins,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif; }
    body{ background:var(--bg); color:var(--ink); }

    /* ===== SIDEBAR ===== */
    .sidebar{
      position:fixed; left:-320px; top:0; bottom:0; width:var(--sidebar-w);
      background:#fff; border-right:1px solid rgba(0,0,0,.05);
      transition:left .25s ease; z-index:1050; padding:16px 18px; overflow-y:auto;
    }
    .sidebar.show{ left:0; }
    .sidebar-head{ display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:10px; }
    .sidebar-inner-toggle,.sidebar-close-btn{ background:transparent; border:0; width:40px; height:36px; display:grid; place-items:center; }
    .hamb-icon{ width:24px; height:20px; display:flex; flex-direction:column; justify-content:space-between; gap:4px; }
    .hamb-icon span{ height:2px; background:var(--brown); border-radius:999px; }
    .sidebar .nav-link{ display:flex; align-items:center; gap:12px; padding:12px 14px; border-radius:16px; font-weight:600; color:#111; text-decoration:none; }
    .sidebar .nav-link:hover{ background:rgba(255,213,79,.25); }

    /* ===== BACKDROP ===== */
    .backdrop-mobile{ display:none; }
    .backdrop-mobile.active{ display:block; position:fixed; inset:0; background:rgba(0,0,0,.35); z-index:1040; }

    /* ===== TOPBAR & CONTENT ===== */
    .content{ padding:16px 14px 40px; }
    .topbar{ display:flex; align-items:center; gap:12px; margin-bottom:16px; }
    .btn-menu{ background:transparent; border:0; width:40px; height:38px; display:grid; place-items:center; }
    .search-box{ position:relative; flex:1; }
    .search-input{
      height:46px; width:100%; border-radius:999px; border:1px solid #e5e7eb;
      padding-left:16px; padding-right:44px; outline:none; transition:border-color .15s ease; background:#fff;
    }
    .search-input:focus{ border-color:var(--gold-soft); box-shadow:none; }
    .search-icon{ position:absolute; right:16px; top:50%; transform:translateY(-50%); color:var(--brown); cursor:pointer; }

    .icon-btn{
      width:38px; height:38px; border-radius:9999px;
      display:flex; align-items:center; justify-content:center; text-decoration:none; color:var(--brown); position:relative;
    }
    /* ——— Notif badge (dot kecil) ——— */
    .notif-dot{
      position:absolute; top:3px; right:4px;
      width:8px; height:8px; border-radius:999px;
      background:#4B3F36; box-shadow:0 0 0 1.5px #fff;
    }
    .d-none{ display:none !important; }

    /* ===== CARD / TABEL ===== */
    .cardx{ background:#fff; border:1px solid #f7d78d; border-radius:var(--radius); padding:16px; }
    .table thead th{ background:#fffbe6; white-space:nowrap; }
    .thumb{ width:48px; height:48px; object-fit:cover; border-radius:10px; border:1px solid #ececec; }

    /* ===== BUTTONS (samakan dengan users) ===== */
    .btn-saffron,.btn-add-main,.modal-footer .btn{
      background-color: var(--gold);
      color: var(--brown) !important;
      border: 0;
      border-radius: var(--btn-radius);
      font-family: Arial, Helvetica, sans-serif;
      font-weight: 600;
      font-size: .88rem;
      padding: 10px 18px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      white-space: nowrap;
      box-shadow: none;
    }
    .btn-saffron:hover{ background:#FFE07A; color:#111; }

    /* tombol Tambah (SVG plus tebal, sama seperti users) */
    .btn-add-main{
      background:var(--gold); border:1px solid rgba(0,0,0,.02); color:#111;
      border-radius:var(--btn-radius); padding:.6rem 1.1rem;
      display:inline-flex; align-items:center; gap:.55rem;
    }
    .btn-add-main:hover{ background:#FFE07A; }
    .btn-add-main svg.icon-plus{
      width:18px; height:18px;
      stroke:currentColor; fill:none;
      stroke-width:3.2;
      stroke-linecap:round; stroke-linejoin:round;
      display:inline-block;
    }

    /* ===== FORM STYLE ===== */
    .form-control,.form-select{
      border:1px solid var(--input-border) !important; border-radius:14px !important;
      box-shadow:none !important; outline:none !important; font-size:.95rem;
    }
    .form-control:focus,.form-select:focus{ border-color:var(--gold-soft) !important; box-shadow:none !important; outline:none !important; }

    /* ===== CUSTOM SELECT ===== */
    .cf-select{ position:relative; width:100%; }
    .cf-select__trigger{
      width:100%; background:#fff; border:1px solid var(--input-border); border-radius:14px;
      padding:8px 38px 8px 14px; display:flex; align-items:center; justify-content:space-between; gap:12px;
      cursor:pointer; transition:border-color .12s ease;
    }
    .cf-select.is-open .cf-select__trigger,.cf-select__trigger:focus-visible{ border-color:var(--gold-soft); outline:none; }
    .cf-select__text{ font-size:.92rem; color:#2b2b2b; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .cf-select__icon{ color:var(--brown); }
    .cf-select__list{
      position:absolute; left:0; top:calc(100% + 6px); width:100%; background:#fff;
      border:1px solid rgba(0,0,0,.02); border-radius:14px; box-shadow:0 16px 30px rgba(0,0,0,.09);
      display:none; max-height:230px; overflow-y:auto; z-index:9999;
    }
    .cf-select.is-open .cf-select__list{ display:block; }
    .cf-select__option{ padding:9px 14px; font-size:.9rem; color:#413731; cursor:pointer; }
    .cf-select__option:hover{ background:#FFF2C9; }
    .cf-select__option.is-active{ background:#FFEB9B; font-weight:600; }

    .btn i{ font-size:16px; vertical-align:middle; }

    /* ====== MODAL (samakan dengan users) ====== */
    .modal-content{ border-radius:12px !important; border:none !important; box-shadow:0 20px 40px rgba(0,0,0,.15); overflow:hidden !important; }
    .modal-header{ border-bottom:none !important; padding-bottom:.5rem; }
    .modal-footer{ border-top:none !important; background:#fff !important; padding-top:.75rem; padding-bottom:3rem; }

    /* Tombol Batalkan abu-abu seperti contoh */
    .modal-footer .btn-outline-secondary{
      background:#F3F4F6;                  /* abu lembut */
      color:#111827;                       /* teks gelap */
      border:1px solid #E5E7EB !important; /* garis halus */
      border-radius:var(--btn-radius) !important;
      font-family: Arial, Helvetica, sans-serif;
      font-weight: 700;
      box-shadow:none;
    }
    .modal-footer .btn-outline-secondary:hover{
      background:#ECEFF3;
      border-color:#E5E7EB !important;
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

<!-- content -->
<main class="content">
  <!-- topbar -->
  <div class="topbar">
    <button class="btn-menu" id="openSidebar" aria-label="Buka menu">
      <div class="hamb-icon"><span></span><span></span><span></span></div>
    </button>

    <div class="search-box">
      <input class="search-input" id="searchInput" placeholder="Search..." autocomplete="off">
      <i class="bi bi-search search-icon" id="searchIcon"></i>
    </div>

    <div class="d-flex align-items-center gap-2">
      <a id="btnBell" class="icon-btn position-relative text-decoration-none" href="<?= BASE_URL ?>/public/admin/notifications.php" aria-label="Notifikasi">
        <span class="iconify" data-icon="mdi:bell-outline" data-width="24" data-height="24"></span>
        <span id="badgeNotif" class="notif-dot d-none"></span>
      </a>
      <a class="icon-btn text-decoration-none" href="<?= BASE_URL ?>/public/admin/settings.php" aria-label="Akun">
        <span class="iconify" data-icon="mdi:account-circle-outline" data-width="28" data-height="28"></span>
      </a>
    </div>
  </div>

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="fw-bold m-0">Kelola Menu</h2>
    <!-- Samakan dengan Users: btn-add-main + SVG plus -->
    <button class="btn-add-main d-print-none" data-bs-toggle="modal" data-bs-target="#menuModal" onclick="openAdd()">
      <svg class="icon-plus" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 5v14M5 12h14"></path></svg>
      <span>Tambah Menu</span>
    </button>
  </div>

  <?php if ($msg): ?>
    <div class="alert alert-warning py-2"><?= h($msg) ?></div>
  <?php endif; ?>

  <div class="cardx">
    <div class="table-responsive">
      <table class="table align-middle mb-0" id="menuTable">
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
          <tr><td colspan="7" class="text-center text-muted py-4">Belum ada data.</td></tr>
        <?php else: foreach ($menus as $m): ?>
          <tr>
            <td><?= (int)$m['id'] ?></td>
            <td>
              <?php
              $src = !empty($m['image'])
                ? (str_starts_with($m['image'], 'http') ? $m['image'] : BASE_URL.'/public/'.$m['image'])
                : 'https://picsum.photos/seed/caffora/96';
              ?>
              <img src="<?= h($src) ?>" alt="" class="thumb">
            </td>
            <td><?= h($m['name']) ?></td>
            <td><?= h($m['category']) ?></td>
            <td><?= rupiah($m['price']) ?></td>
            <td>
              <?= $m['stock_status'] === 'Ready'
                ? '<span class="badge text-bg-success">Ready</span>'
                : '<span class="badge text-bg-danger">Sold Out</span>' ?>
            </td>
            <td class="text-nowrap">
              <button class="btn btn-sm btn-outline-primary me-1"
                title="Edit"
                onclick='openEdit(<?= (int)$m["id"] ?>, <?= json_encode($m, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>)'>
                <i class="bi bi-pencil-square"></i>
              </button>
              <a class="btn btn-sm btn-outline-danger"
                 href="?delete=<?= (int)$m['id'] ?>"
                 title="Hapus"
                 onclick="return confirm('Hapus menu ini?')">
                <i class="bi bi-trash"></i>
              </a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<!-- MODAL -->
<div class="modal fade" id="menuModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="post" enctype="multipart/form-data">
      <div class="modal-header">
        <h5 class="modal-title" id="menuTitle">Tambah Menu</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" id="action" value="add">
        <input type="hidden" name="id" id="id">
        <!-- real input buat PHP -->
        <input type="hidden" name="category" id="category" value="food">
        <input type="hidden" name="stock_status" id="stock_status" value="Ready">

        <div class="mb-2">
          <label class="form-label">Nama</label>
          <input type="text" class="form-control" name="name" id="name" required>
        </div>

        <div class="mb-2">
          <label class="form-label">Kategori</label>
          <!-- custom select -->
          <div class="cf-select" data-target="category">
            <div class="cf-select__trigger" tabindex="0">
              <span class="cf-select__text" id="category_label">Food</span>
              <i class="bi bi-chevron-down cf-select__icon"></i>
            </div>
            <div class="cf-select__list">
              <div class="cf-select__option is-active" data-value="food">Food</div>
              <div class="cf-select__option" data-value="pastry">Pastry</div>
              <div class="cf-select__option" data-value="drink">Drink</div>
            </div>
          </div>
        </div>

        <div class="mb-2">
          <label class="form-label">Harga</label>
          <input type="number" class="form-control" name="price" id="price" min="0" step="1" required>
        </div>

        <div class="mb-2">
          <label class="form-label">Status Stok</label>
          <div class="cf-select" data-target="stock_status">
            <div class="cf-select__trigger" tabindex="0">
              <span class="cf-select__text" id="stock_status_label">Ready</span>
              <i class="bi bi-chevron-down cf-select__icon"></i>
            </div>
            <div class="cf-select__list">
              <div class="cf-select__option is-active" data-value="Ready">Ready</div>
              <div class="cf-select__option" data-value="Sold Out">Sold Out</div>
            </div>
          </div>
        </div>

        <div class="mb-2">
          <label class="form-label">Gambar (opsional)</label>
          <input type="file" class="form-control" name="image" accept="image/*">
        </div>
      </div>
      <div class="modal-footer">
        <!-- Sama seperti users: Batalkan abu-abu -->
        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Batalkan</button>
        <button class="btn btn-saffron" type="submit">Simpan</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ===== sidebar ===== */
const sideNav   = document.getElementById('sideNav');
const backdrop  = document.getElementById('backdrop');
document.getElementById('openSidebar')?.addEventListener('click', ()=>{ sideNav.classList.add('show'); backdrop.classList.add('active'); });
document.getElementById('closeSidebar')?.addEventListener('click', closeSide);
document.getElementById('toggleSidebarInside')?.addEventListener('click', closeSide);
backdrop?.addEventListener('click', closeSide);
function closeSide(){ sideNav.classList.remove('show'); backdrop.classList.remove('active'); }

document.querySelectorAll('#sidebar-nav .nav-link').forEach(a=>{
  a.addEventListener('click', function(){
    document.querySelectorAll('#sidebar-nav .nav-link').forEach(l=>l.classList.remove('active'));
    this.classList.add('active');
    if (window.innerWidth < 1200) closeSide();
  });
});

/* ===== search filter ===== */
const searchInput = document.getElementById('searchInput');
const searchIcon  = document.getElementById('searchIcon');
const tableBody   = document.querySelector('#menuTable tbody');
function doFilter(){
  const q = (searchInput.value || '').toLowerCase();
  tableBody.querySelectorAll('tr').forEach(tr => {
    const text = tr.innerText.toLowerCase();
    tr.style.display = text.includes(q) ? '' : 'none';
  });
}
searchInput?.addEventListener('input', doFilter);
searchIcon?.addEventListener('click', doFilter);

/* ===== notif badge (polling unread_count) ===== */
async function refreshAdminNotifBadge(){
  const badge = document.getElementById('badgeNotif');
  if (!badge) return;
  try{
    const res = await fetch("<?= BASE_URL ?>/backend/api/notifications.php?action=unread_count", {
      credentials:'same-origin',
      headers: { 'Cache-Control':'no-cache' }
    });
    if (!res.ok) return;
    const data = await res.json();
    const count = Number(data?.count ?? 0);
    badge.classList.toggle('d-none', !(count > 0));
  }catch(e){ /* silent */ }
}
refreshAdminNotifBadge();
setInterval(refreshAdminNotifBadge, 30000);

/* ===== modal helpers ===== */
const modalEl = document.getElementById('menuModal');
const modal   = new bootstrap.Modal(modalEl);

function openAdd(){
  document.getElementById('menuTitle').textContent = 'Tambah Menu';
  document.getElementById('action').value = 'add';
  document.getElementById('id').value = '';
  document.getElementById('name').value = '';
  document.getElementById('price').value = '';
  // reset hidden
  document.getElementById('category').value = 'food';
  document.getElementById('category_label').textContent = 'Food';
  document.getElementById('stock_status').value = 'Ready';
  document.getElementById('stock_status_label').textContent = 'Ready';
}

function openEdit(id,row){
  document.getElementById('menuTitle').textContent = 'Edit Menu';
  document.getElementById('action').value = 'edit';
  document.getElementById('id').value = id;
  document.getElementById('name').value = row.name || '';
  document.getElementById('price').value = row.price || '';
  // set kategori
  const cat = (row.category || 'food').toLowerCase();
  document.getElementById('category').value = cat;
  document.getElementById('category_label').textContent = cat === 'food' ? 'Food' : (cat === 'pastry' ? 'Pastry' : 'Drink');
  // set stok
  const st = row.stock_status === 'Sold Out' ? 'Sold Out' : 'Ready';
  document.getElementById('stock_status').value = st;
  document.getElementById('stock_status_label').textContent = st;
  modal.show();
}

/* ===== init custom select ===== */
(function initCfSelect(){
  const selects = document.querySelectorAll('.cf-select');
  const closeAll = () => { selects.forEach(s => s.classList.remove('is-open')); };
  selects.forEach(sel => {
    const targetId = sel.dataset.target;
    const trigger  = sel.querySelector('.cf-select__trigger');
    const list     = sel.querySelector('.cf-select__list');
    const label    = sel.querySelector('.cf-select__text');

    trigger.addEventListener('click', (e)=>{
      e.stopPropagation();
      const isOpen = sel.classList.contains('is-open');
      closeAll();
      if (!isOpen) sel.classList.add('is-open');
    });

    list.querySelectorAll('.cf-select__option').forEach(opt => {
      opt.addEventListener('click', ()=>{
        const val  = opt.dataset.value;
        const text = opt.textContent.trim();
        label.textContent = text;
        const hid = document.getElementById(targetId);
        if (hid) hid.value = val;
        list.querySelectorAll('.cf-select__option').forEach(o => o.classList.remove('is-active'));
        opt.classList.add('is-active');
        sel.classList.remove('is-open');
      });
    });
  });
  document.addEventListener('click', ()=> closeAll());
})();
</script>
</body>
</html>

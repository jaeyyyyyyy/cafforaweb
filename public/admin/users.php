<?php  
// public/admin/users.php
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

/* ================== UTIL ================== */
function emailUsedByOther(mysqli $conn, string $email, int $exceptId = 0): bool {
  $sql = $exceptId > 0
    ? "SELECT id FROM users WHERE email=? AND id<>? LIMIT 1"
    : "SELECT id FROM users WHERE email=? LIMIT 1";
  $stmt = $conn->prepare($sql);
  if ($exceptId > 0) { $stmt->bind_param('si', $email, $exceptId); }
  else { $stmt->bind_param('s', $email); }
  $stmt->execute();
  $stmt->store_result();
  $exists = $stmt->num_rows > 0;
  $stmt->close();
  return $exists;
}

$msg = $_GET['msg'] ?? '';

/* ================== ACTIONS ================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $act    = $_POST['action'] ?? '';
  $id     = (int)($_POST['id'] ?? 0);
  $name   = trim((string)($_POST['name'] ?? ''));
  $email  = trim((string)($_POST['email'] ?? ''));
  $role   = trim((string)($_POST['role'] ?? 'customer'));
  $status = trim((string)($_POST['status'] ?? 'active'));
  $pass   = (string)($_POST['password'] ?? '');

  if (!in_array($role, ['admin','karyawan','customer'], true)) $role = 'customer';
  if (!in_array($status, ['pending','active'], true)) $status = 'active';

  // ADD
  if ($act === 'add') {
    if ($name === '' || $email === '' || $pass === '') {
      header('Location: '.$_SERVER['PHP_SELF'].'?msg='.urlencode('Nama, email, dan password wajib diisi.'));
      exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      header('Location: '.$_SERVER['PHP_SELF'].'?msg='.urlencode('Format email tidak valid.'));
      exit;
    }
    if (emailUsedByOther($conn, $email, 0)) {
      header('Location: '.$_SERVER['PHP_SELF'].'?msg='.urlencode('Email sudah digunakan.'));
      exit;
    }
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users(name,email,password,role,status) VALUES (?,?,?,?,?)");
    $stmt->bind_param('sssss', $name, $email, $hash, $role, $status);
    $ok = $stmt->execute();
    $stmt->close();
    header('Location: '.$_SERVER['PHP_SELF'].'?msg='.urlencode($ok ? 'User ditambahkan.' : 'Gagal menambah user.'));
    exit;
  }

  // EDIT
  if ($act === 'edit' && $id > 0) {
    if ($name === '' || $email === '') {
      header('Location: '.$_SERVER['PHP_SELF'].'?msg='.urlencode('Nama dan email wajib.'));
      exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      header('Location: '.$_SERVER['PHP_SELF'].'?msg='.urlencode('Format email tidak valid.'));
      exit;
    }
    if (emailUsedByOther($conn, $email, $id)) {
      header('Location: '.$_SERVER['PHP_SELF'].'?msg='.urlencode('Email sudah dipakai user lain.'));
      exit;
    }

    if ($pass !== '') {
      $hash = password_hash($pass, PASSWORD_DEFAULT);
      $stmt = $conn->prepare("UPDATE users SET name=?, email=?, password=?, role=?, status=? WHERE id=?");
      $stmt->bind_param('sssssi', $name, $email, $hash, $role, $status, $id);
    } else {
      $stmt = $conn->prepare("UPDATE users SET name=?, email=?, role=?, status=? WHERE id=?");
      $stmt->bind_param('ssssi', $name, $email, $role, $status, $id);
    }
    $ok = $stmt->execute();
    $stmt->close();
    header('Location: '.$_SERVER['PHP_SELF'].'?msg='.urlencode($ok ? 'User diperbarui.' : 'Gagal mengedit user.'));
    exit;
  }
}

// DELETE
if (isset($_GET['delete'])) {
  $id = (int)$_GET['delete'];
  if ($id > 0) {
    $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    header('Location: '.$_SERVER['PHP_SELF'].'?msg='.urlencode('User dihapus.'));
    exit;
  }
}

/* ================== DATA ================== */
$rows = [];
$res = $conn->query("SELECT id,name,email,role,status,created_at FROM users ORDER BY created_at DESC");
if ($res) {
  $rows = $res->fetch_all(MYSQLI_ASSOC);
  $res->close();
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Users — Admin Desk</title>
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
      --radius:18px;
      --sidebar-w:320px;
      --input-border:#E8E2DA;
      --bg:#FAFAFA;
      --btn-radius:14px; /* pusat radius tombol, termasuk Batal */
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
    .hamb-icon span{ height:2px; background:var(--brown); border-radius:99px; }
    .sidebar .nav-link{ display:flex; align-items:center; gap:12px; padding:12px 14px; border-radius:16px; font-weight:600; color:#111; text-decoration:none; }
    .sidebar .nav-link:hover{ background:rgba(255,213,79,.25); }
    .sidebar .nav-link.active{ background:rgba(255,213,79,.4); }

    .backdrop-mobile{ display:none; }
    .backdrop-mobile.active{ display:block; position:fixed; inset:0; background:rgba(0,0,0,.35); z-index:1040; }

    /* ===== CONTENT ===== */
    .content{ padding:16px 14px 40px; }
    .topbar{ display:flex; align-items:center; gap:12px; margin-bottom:16px; }
    .btn-menu{ background:transparent; border:0; width:40px; height:38px; display:grid; place-items:center; }

    .search-box{ position:relative; flex:1 1 auto; }
    .search-input{
      height:46px; width:100%; border-radius:9999px;
      padding-left:16px; padding-right:44px;
      border:1px solid #e5e7eb; background:#fff; outline:none; transition:border-color .12s ease;
    }
    .search-input:focus{ border-color:var(--gold-soft) !important; }
    .search-icon{ position:absolute; right:16px; top:50%; transform:translateY(-50%); color:var(--brown); cursor:pointer; }

    .top-actions{ display:flex; align-items:center; gap:14px; }
    .icon-btn{ width:38px; height:38px; border-radius:999px; display:flex; align-items:center; justify-content:center; color:var(--brown); text-decoration:none; position:relative; }

    /* ——— Notif badge (dot kecil) ——— */
    .notif-dot{
      position:absolute; top:3px; right:4px;
      width:8px; height:8px; border-radius:999px;
      background:#4B3F36; box-shadow:0 0 0 1.5px #fff;
    }
    .d-none{ display:none !important; }

    .cardx{ background:#fff; border:1px solid #f7d78d; border-radius:var(--radius); padding:18px; }
    .table thead th{ background:#fffbe6; }

    /* ===== Buttons (Arial) ===== */
    .btn-saffron,.btn-add-main,.modal-footer .btn{
      background-color: var(--gold);
      color: var(--brown) !important;
      border: 0;
      border-radius: 14px;
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

    /* tombol Tambah User – clean */
    .btn-add-main{
      background:var(--gold); border:1px solid rgba(0,0,0,.02); color:#111;
      border-radius:var(--btn-radius); padding:.6rem 1.1rem;
      display:inline-flex; align-items:center; gap:.55rem;
    }
    .btn-add-main:hover{ background:#FFE07A; }
    /* SVG plus tebal */
    .btn-add-main svg.icon-plus{
      width:18px; height:18px;
      stroke:currentColor; fill:none;
      stroke-width:3.2;
      stroke-linecap:round; stroke-linejoin:round;
      display:inline-block;
    }
    /* jika masih ada .plus-dot di HTML lama, matikan saja */
    .btn-add-main .plus-dot{ display:none !important; }

    /* form */
    .form-control{
      border:1px solid var(--input-border) !important; border-radius:14px !important;
      box-shadow:none !important; outline:none !important;
    }
    .form-control:focus{ border-color:var(--gold-soft) !important; box-shadow:none !important; outline:none !important; }

    /* custom select */
    .cf-select{ position:relative; width:100%; }
    .cf-select__trigger{
      width:100%; background:#fff; border:1px solid var(--input-border); border-radius:14px;
      padding:8px 38px 8px 14px; display:flex; align-items:center; justify-content:space-between; gap:12px;
      cursor:pointer; transition:border-color .12s ease;
    }
    .cf-select.is-open .cf-select__trigger,.cf-select__trigger:focus-visible{ border-color:var(--gold-soft); outline:none; }
    .cf-select__text{ font-size:.9rem; color:#2b2b2b; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .cf-select__icon{ color:var(--brown); }
    .cf-select__list{
      position:absolute; left:0; top:calc(100% + 6px); width:100%; background:#fff;
      border:1px solid rgba(0,0,0,.02); border-radius:14px; box-shadow:0 14px 30px rgba(0,0,0,.09);
      display:none; max-height:240px; overflow-y:auto; z-index:200000; /* > modal */
    }
    .cf-select.is-open .cf-select__list{ display:block; }
    .cf-select__option{ padding:9px 14px; font-size:.88rem; color:#413731; cursor:pointer; }
    .cf-select__option:hover{ background:#FFF2C9; }
    .cf-select__option.is-active{ background:#FFEB9B; font-weight:600; }

    @media(min-width:992px){
      .content{ padding:20px 26px 50px; }
      .search-box{ max-width:1100px; }
    }
    @media print{ .d-print-none{ display:none !important; } }

    /* ===== Modal polish ===== */
    .modal-content{ border:0 !important; box-shadow:0 18px 50px rgba(0,0,0,.16); border-radius:18px; }
    .modal-header{ border-bottom:0 !important; }
    .modal-footer{ border-top:0 !important; }
    .modal-body{ padding-bottom:110px; } /* ruang dropdown agar tak keluar */

    /* Batal seperti contoh (abu-abu) */
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
    .modal-footer .btn-saffron{ border-radius:var(--btn-radius) !important; }
  </style>
</head>
<body>

<!-- backdrop -->
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

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="fw-bold m-0">Kelola User</h2>
    <button class="btn-add-main d-print-none" data-bs-toggle="modal" data-bs-target="#userModal" onclick="openAdd()">
      <!-- SVG plus tebal -->
      <svg class="icon-plus" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <path d="M12 5v14M5 12h14"></path>
      </svg>
      <span>Tambah User</span>
    </button>
  </div>

  <?php if ($msg): ?>
    <div class="alert alert-warning py-2"><?= h($msg) ?></div>
  <?php endif; ?>

  <div class="cardx">
    <div class="table-responsive">
      <table class="table align-middle mb-0" id="usersTable">
        <thead>
          <tr>
            <th>ID</th>
            <th>Nama</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            <th>Dibuat</th>
            <th class="d-print-none">Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">Belum ada data.</td></tr>
        <?php else: foreach ($rows as $u): ?>
          <tr>
            <td><?= (int)$u['id'] ?></td>
            <td><?= h($u['name']) ?></td>
            <td><?= h($u['email']) ?></td>
            <td><span class="badge text-bg-secondary text-capitalize"><?= h($u['role']) ?></span></td>
            <td>
              <?php if ($u['status'] === 'active'): ?>
                <span class="badge text-bg-success">Aktif</span>
              <?php else: ?>
                <span class="badge text-bg-warning text-dark">Pending</span>
              <?php endif; ?>
            </td>
            <td><small class="text-muted"><?= h($u['created_at']) ?></small></td>
            <td class="d-print-none text-nowrap">
              <button class="btn btn-sm btn-outline-primary me-1"
                title="Edit"
                onclick='openEdit(<?= (int)$u["id"] ?>, <?= json_encode($u, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>)'>
                <i class="bi bi-pencil-square"></i>
              </button>
              <a class="btn btn-sm btn-outline-danger"
                href="?delete=<?= (int)$u['id'] ?>"
                title="Hapus"
                onclick="return confirm('Hapus user ini?')">
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

<!-- MODAL ADD / EDIT -->
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="post">
      <div class="modal-header">
        <h5 class="modal-title" id="userTitle">Tambah User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" id="action" value="add">
        <input type="hidden" name="id" id="id">
        <!-- hidden buat custom select -->
        <input type="hidden" name="role" id="role" value="customer">
        <input type="hidden" name="status" id="status" value="active">

        <div class="mb-2">
          <label class="form-label">Nama</label>
          <input type="text" class="form-control" name="name" id="name" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Email</label>
          <input type="email" class="form-control" name="email" id="email" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Password <small class="text-muted">(kosongkan saat edit)</small></label>
          <input type="password" class="form-control" name="password" id="password" minlength="6">
        </div>

        <div class="row">
          <div class="col-md-6 mb-2">
            <label class="form-label">Role</label>
            <div class="cf-select" data-target="role">
              <div class="cf-select__trigger" tabindex="0">
                <span class="cf-select__text" id="role_label">Customer</span>
                <i class="bi bi-chevron-down cf-select__icon"></i>
              </div>
              <div class="cf-select__list">
                <div class="cf-select__option is-active" data-value="customer">Customer</div>
                <div class="cf-select__option" data-value="karyawan">Karyawan</div>
                <div class="cf-select__option" data-value="admin">Admin</div>
              </div>
            </div>
          </div>
          <div class="col-md-6 mb-2">
            <label class="form-label">Status</label>
            <div class="cf-select" data-target="status">
              <div class="cf-select__trigger" tabindex="0">
                <span class="cf-select__text" id="status_label">Aktif</span>
                <i class="bi bi-chevron-down cf-select__icon"></i>
              </div>
              <div class="cf-select__list">
                <div class="cf-select__option is-active" data-value="active">Aktif</div>
                <div class="cf-select__option" data-value="pending">Pending</div>
              </div>
            </div>
          </div>
        </div>

      </div>
      <div class="modal-footer">
        <!-- Tombol Batal seperti gambar -->
        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Batalkan</button>
        <button class="btn btn-saffron" type="submit">Simpan</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ===== Sidebar ===== */
const sideNav = document.getElementById('sideNav');
const backdrop = document.getElementById('backdrop');
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

/* ===== Notif badge: polling unread_count ===== */
async function refreshAdminNotifBadge(){
  const badge = document.getElementById('badgeNotif'); if (!badge) return;
  try{
    const res = await fetch("<?= BASE_URL ?>/backend/api/notifications.php?action=unread_count", {
      credentials:"same-origin",
      headers: { "Cache-Control":"no-cache" }
    });
    if (!res.ok) return;
    const data = await res.json();
    const count = Number(data?.count ?? 0);
    badge.classList.toggle('d-none', !(count > 0));
  }catch(e){ /* silent */ }
}
refreshAdminNotifBadge();
setInterval(refreshAdminNotifBadge, 30000);

/* ===== Custom Select ===== */
document.addEventListener('click', function(e){
  document.querySelectorAll('.cf-select').forEach(sel=>{
    const trig = sel.querySelector('.cf-select__trigger');
    const list = sel.querySelector('.cf-select__list');
    if (trig.contains(e.target)) {
      sel.classList.toggle('is-open');
    } else if (!list.contains(e.target)) {
      sel.classList.remove('is-open');
    }
  });
});
document.querySelectorAll('.cf-select').forEach(sel=>{
  const targetId = sel.getAttribute('data-target');
  const hidden = document.getElementById(targetId);
  const label  = document.getElementById(targetId + '_label');
  sel.querySelectorAll('.cf-select__option').forEach(opt=>{
    opt.addEventListener('click', function(){
      sel.querySelectorAll('.cf-select__option').forEach(o=>o.classList.remove('is-active'));
      this.classList.add('is-active');
      hidden.value = this.getAttribute('data-value');
      label.textContent = this.textContent.trim();
      sel.classList.remove('is-open');
    });
  });
});

/* ===== Modal Helpers ===== */
const modalEl = document.getElementById('userModal');
const modal = new bootstrap.Modal(modalEl);

function openAdd(){
  document.getElementById('userTitle').textContent = 'Tambah User';
  document.getElementById('action').value = 'add';
  document.getElementById('id').value = '';
  document.getElementById('name').value = '';
  document.getElementById('email').value = '';
  document.getElementById('password').value = '';
  // reset custom select
  document.getElementById('role').value = 'customer';
  document.getElementById('role_label').textContent = 'Customer';
  document.getElementById('status').value = 'active';
  document.getElementById('status_label').textContent = 'Aktif';
  document.querySelectorAll('[data-target="role"] .cf-select__option').forEach(o=>o.classList.remove('is-active'));
  document.querySelector('[data-target="role"] .cf-select__option[data-value="customer"]').classList.add('is-active');
  document.querySelectorAll('[data-target="status"] .cf-select__option').forEach(o=>o.classList.remove('is-active'));
  document.querySelector('[data-target="status"] .cf-select__option[data-value="active"]').classList.add('is-active');

  modal.show();
}

function openEdit(id, row){
  document.getElementById('userTitle').textContent = 'Edit User';
  document.getElementById('action').value = 'edit';
  document.getElementById('id').value = id;
  document.getElementById('name').value = row.name || '';
  document.getElementById('email').value = row.email || '';
  document.getElementById('password').value = '';

  const roleVal = (row.role || 'customer').toLowerCase();
  document.getElementById('role').value = roleVal;
  document.getElementById('role_label').textContent =
    roleVal === 'admin' ? 'Admin' : (roleVal === 'karyawan' ? 'Karyawan' : 'Customer');
  document.querySelectorAll('[data-target="role"] .cf-select__option').forEach(o=>o.classList.remove('is-active'));
  document.querySelector(`[data-target="role"] .cf-select__option[data-value="${roleVal}"]`)?.classList.add('is-active');

  const st = (row.status || 'active');
  document.getElementById('status').value = st;
  document.getElementById('status_label').textContent = st === 'active' ? 'Aktif' : 'Pending';
  document.querySelectorAll('[data-target="status"] .cf-select__option').forEach(o=>o.classList.remove('is-active'));
  document.querySelector(`[data-target="status"] .cf-select__option[data-value="${st}"]`)?.classList.add('is-active');

  modal.show();
}

/* ===== Search client-side ===== */
const searchInput = document.getElementById('searchInput');
const searchIcon  = document.getElementById('searchIcon');
const tableBody   = document.querySelector('#usersTable tbody');

function doFilter(){
  const q = (searchInput.value || '').toLowerCase();
  tableBody.querySelectorAll('tr').forEach(tr => {
    const text = tr.innerText.toLowerCase();
    tr.style.display = text.includes(q) ? '' : 'none';
  });
}
searchInput?.addEventListener('input', doFilter);
searchIcon?.addEventListener('click', doFilter);
</script>
</body>
</html>

<?php
// public/admin/audit.php
declare(strict_types=1);

require_once __DIR__ . '/../../backend/auth_guard.php';
require_login(['admin']);
require_once __DIR__ . '/../../backend/config.php';

$BASE      = BASE_URL;
$adminName = htmlspecialchars($_SESSION['user_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Audit Log — Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap (untuk modal custom range) & Icons & Iconify -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
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
    }
    *,:before,:after{ box-sizing:border-box; }
    body{
      background:#FAFAFA; color:var(--ink);
      font-family:Inter,system-ui,Segoe UI,Roboto,Arial;
      font-weight:500; margin:0;
    }

    /* ===== Sidebar (sama seperti finance) ===== */
    .sidebar{
      position:fixed; left:-320px; top:0; bottom:0; width:var(--sidebar-w);
      background:#fff; border-right:1px solid rgba(0,0,0,.05);
      transition:left .25s ease; z-index:1050; padding:16px 18px; overflow-y:auto;
    }
    .sidebar.show{ left:0; }
    .sidebar-head{ display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:10px; }
    .sidebar-inner-toggle, .sidebar-close-btn{
      background:transparent; border:0; width:40px; height:36px; display:grid; place-items:center;
    }
    .hamb-icon{ width:24px; height:20px; display:flex; flex-direction:column; justify-content:space-between; gap:4px; }
    .hamb-icon span{ height:2px; background:var(--brown); border-radius:99px; }
    .sidebar .nav-link{
      display:flex; align-items:center; gap:12px; padding:12px 14px; border-radius:16px;
      font-weight:600; color:#111; text-decoration:none; background:transparent; user-select:none;
    }
    .sidebar .nav-link:hover{ background:rgba(255,213,79,0.25); }
    .sidebar hr{ border-color:rgba(0,0,0,.05); opacity:1; }

    /* Backdrop mobile */
    .backdrop-mobile{
      position:fixed; inset:0; background:rgba(0,0,0,.25); z-index:1040; display:none;
    }
    .backdrop-mobile.active{ display:block; }

    /* ===== Content + Topbar (match finance) ===== */
    .content{ margin-left:0; padding:16px 14px 50px; }
    .topbar{ display:flex; align-items:center; gap:12px; margin-bottom:16px; }
    .btn-menu{ background:transparent; border:0; width:40px; height:38px; display:grid; place-items:center; }
    .hamb-icon{ width:24px; height:20px; display:flex; flex-direction:column; justify-content:space-between; gap:4px; }
    .hamb-icon span{ height:2px; background:var(--brown); border-radius:99px; }

    /* Search (aktif/consisten) */
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
    .icon-btn{
      width:38px; height:38px; border-radius:999px; display:flex; align-items:center; justify-content:center;
      color:var(--brown); text-decoration:none; background:transparent; outline:none;
    }

    /* ===== Range + Export (identik finance) ===== */
    .range-wrap{
      display:flex; gap:10px; align-items:center; flex-wrap:wrap; justify-content:flex-start;
    }
    .select-ghost{ position:absolute !important; width:1px; height:1px; opacity:0; pointer-events:none; left:-9999px; top:auto; overflow:hidden; }
    .btn-gold{
      display:inline-flex; align-items:center; gap:8px; height:42px; background:#ffd54f;
      border:1px solid rgba(0,0,0,.06); border-radius:12px; padding:0 14px; font-weight:700; color:#111;
      text-decoration:none; white-space:nowrap;
    }
    .btn-gold:hover{ filter:brightness(.97); color:#111; text-decoration:none; }
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

    .period{ color:#6b7280; margin:6px 0 14px; font-weight:600; }

    /* ===== Card + Table ===== */
    .card{ background:#fff; border:1px solid var(--gold-border); border-radius:20px; padding:18px 20px; }
    table{ width:100%; border-collapse:collapse; }
    th, td{ padding:14px 12px; border-bottom:1px dashed #eee; text-align:left; vertical-align:middle; font-size:14px; }
    th{ background:#fffdf2; position:sticky; top:0; z-index:1; }
    .badge{ display:inline-flex; align-items:center; padding:6px 12px; border-radius:999px; font-size:12px; font-weight:700; background:#fff; border:1.4px solid var(--gold-border); color:#111; }
    .muted{ color:#6b7280; }

    /* ===== Responsive (match finance) ===== */
    @media (min-width:992px){
      .content{
        padding-left:60px !important; padding-right:60px !important;
        padding-top:20px; padding-bottom:60px;
      }
      .search-box{ max-width:1100px !important; }
    }
    @media (min-width:1200px){
      .content{ padding-left:10px !important; padding-right:10px !important; }
    }
    @media (min-width:370px){
      .content{ padding-left:8px !important; padding-right:8px !important; }
      .topbar{ padding-left:4px !important; padding-right:4px !important; }
    }
    @media (max-width:575.98px){
      .content{ padding:14px 12px 70px; }
      .topbar{ gap:10px; }
      .search-box{ min-width:0; width:100%; order:2; flex:1 1 100%; }
      .top-actions{ order:3; }
      .btn-menu{ order:1; }
      .range-wrap{ gap:8px; width:100%; justify-content:stretch; }
      .select-custom{ width:100%; }
      .select-toggle{ width:100%; height:40px; padding:0 12px; }
      .select-menu{ min-width:100%; max-width:100vw; }
      .btn-gold{ height:40px; padding:0 12px; width:100%; justify-content:center; }
      .card{ padding:16px; }
    }
    @media (max-width:360px){
      .icon-btn{ width:34px; height:34px; }
      .search-input{ height:40px; padding-left:12px; padding-right:38px; }
      .select-toggle{ height:38px; }
      .btn-gold{ height:38px; }
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


<!-- Content -->
<main class="content">
  <div class="container-xxl px-2 px-xl-3">

    <!-- Topbar sama finance -->
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

    <h2 class="fw-bold mb-1">Audit Log</h2>

    <!-- Periode + Export (visual sama finance) -->
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
      <div>
        <div class="text-muted small mb-1">Periode ditampilkan</div>
        <div class="fw-semibold" id="periodText">–</div>
      </div>

      <div class="range-wrap">
        <!-- native select (ghost) untuk aksesibilitas -->
        <select id="rangeSelectGhost" class="select-ghost" tabindex="-1" aria-hidden="true" hidden>
          <option value="today">Hari ini</option>
          <option value="7">7 hari</option>
          <option value="30">30 hari</option>
          <option value="custom">Custom…</option>
        </select>

        <!-- custom dropdown -->
        <div class="select-custom" id="rangeCustom">
          <button type="button" class="select-toggle" id="rangeBtn" aria-haspopup="listbox" aria-expanded="false" tabindex="0">
            <span id="rangeText">7 hari</span>
            <i class="bi bi-chevron-down select-caret"></i>
          </button>
          <div class="select-menu" id="rangeMenu" role="listbox" aria-labelledby="rangeBtn">
            <div class="select-item" data-value="today">Hari ini</div>
            <div class="select-item active" data-value="7">7 hari</div>
            <div class="select-item" data-value="30">30 hari</div>
            <div class="select-item" data-value="custom">Custom…</div>
          </div>
        </div>

        <a id="btnExport" class="btn-gold" href="#"><i class="bi bi-download"></i><span>Export CSV</span></a>
      </div>
    </div>

    <!-- Tabel -->
    <div class="card">
      <div style="overflow:auto;">
        <table>
          <thead>
            <tr>
              <th style="min-width:160px">Tanggal</th>
              <th style="min-width:90px">Jenis</th>
              <th style="min-width:140px">Invoice</th>
              <th style="min-width:260px">Aktivitas</th>
              <th style="min-width:160px">Dibuat oleh</th>
              <th style="min-width:120px">Role</th>
              <th style="min-width:220px">Catatan</th>
            </tr>
          </thead>
          <tbody id="tbody">
            <tr><td colspan="7" class="muted">Memuat data…</td></tr>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</main>

<!-- Modal Custom Range (Bootstrap, sama finance) -->
<div class="modal fade" id="modalCustom" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:460px">
    <form class="modal-content" id="customForm">
      <div class="modal-header">
        <h5 class="modal-title">Pilih rentang tanggal</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label" for="from">Dari tanggal</label>
          <input id="from" name="from" type="date" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label" for="to">Sampai tanggal</label>
          <input id="to" name="to" type="date" class="form-control" required>
        </div>
      </div>
      <div class="modal-footer flex-wrap gap-2">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn-gold" style="border:0">Terapkan</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const BASE = '<?= $BASE ?>';
const API  = BASE + '/backend/api/audit_logs.php';

const els = {
  sideNav: document.getElementById('sideNav'),
  backdrop: document.getElementById('backdrop'),
  openSide: document.getElementById('openSidebar'),
  closeSide: document.getElementById('closeSidebar'),

  rangeBtn:  document.getElementById('rangeBtn'),
  rangeMenu: document.getElementById('rangeMenu'),
  rangeText: document.getElementById('rangeText'),
  rangeGhost:document.getElementById('rangeSelectGhost'),

  periodText: document.getElementById('periodText'),
  tbody: document.getElementById('tbody'),
  btnExport: document.getElementById('btnExport'),

  modalCustom: document.getElementById('modalCustom'),
  customForm:  document.getElementById('customForm'),
  from: document.getElementById('from'),
  to:   document.getElementById('to'),
};

const state = {
  page: 1, per_page: 300,
  range: { preset: '7', from: '', to: '' },
  rows: []
};

/* ===== Sidebar (persis finance) ===== */
document.getElementById('openSidebar')?.addEventListener('click',()=>{
  els.sideNav.classList.add('show'); els.backdrop.classList.add('active');
});
document.getElementById('closeSidebar')?.addEventListener('click',()=>{
  els.sideNav.classList.remove('show'); els.backdrop.classList.remove('active');
});
els.backdrop?.addEventListener('click',()=>{
  els.sideNav.classList.remove('show'); els.backdrop.classList.remove('active');
});
document.querySelectorAll('#sidebar-nav .nav-link').forEach(a=>{
  a.addEventListener('click',function(){
    document.querySelectorAll('#sidebar-nav .nav-link').forEach(l=>l.classList.remove('active'));
    this.classList.add('active');
    if (window.innerWidth < 1200){ els.sideNav.classList.remove('show'); els.backdrop.classList.remove('active'); }
  });
});

/* ===== Search aktif (sama finance) ===== */
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

/* ===== Notif badge ===== */
async function refreshAdminNotifBadge(){
  const badge = document.getElementById('badgeNotif'); if (!badge) return;
  try {
    const res = await fetch("<?= BASE_URL ?>/backend/api/notifications.php?action=unread_count",{credentials:"same-origin"});
    if (!res.ok) return; const data = await res.json(); const count = data.count ?? 0;
    badge.classList.toggle('d-none', !(count>0));
  } catch(e){}
}
refreshAdminNotifBadge(); setInterval(refreshAdminNotifBadge, 30000);

/* ===== Helpers ===== */
const h = s => (s==null?'':String(s))
  .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

function pad(n){ return String(n).padStart(2,'0'); }
function fmtDateTime(s){
  if(!s) return '';
  const dt = new Date(String(s).replace(' ','T'));
  if (isNaN(dt.getTime())) return s;
  return `${dt.getFullYear()}-${pad(dt.getMonth()+1)}-${pad(dt.getDate())} ${pad(dt.getHours())}:${pad(dt.getMinutes())}:${pad(dt.getSeconds())}`;
}
function parseJson(s){ if(!s) return null; try{const j=JSON.parse(s); return j&&typeof j==='object'? j : null;}catch{ return null; } }
function extractInvoice(row){
  if (row.invoice) return row.invoice;
  const to = parseJson(row.to_val);
  const from = parseJson(row.from_val);
  const inv = (to && (to.invoice || to.invoice_no))
           || (from && (from.invoice || from.invoice_no));
  return inv || '';
}
function summarize(row){
  const e = row.entity, a = row.action;
  const to = parseJson(row.to_val);
  if (e==='payment' && (a==='update_status' || a==='mark_paid')) {
    const newStatus = (to && (to.status || to.payment_status)) || (row.to_val||'');
    return /(^|\b)paid(\b|$)/i.test(String(newStatus)) ? 'Pembayaran diterima' : 'Status pembayaran diperbarui';
  }
  if (e==='order' && a==='cancel') return 'Pesanan dibatalkan' + ((to && to.reason) ? ` — ${to.reason}` : '');
  if (e==='order' && a==='create') return 'Pesanan baru dibuat';
  if (e==='order' && a==='update_status') return 'Status pesanan diperbarui';
  return (a||'Aksi') + ' pada ' + (e||'entitas');
}

/* ===== Range dropdown (UX sama finance) ===== */
const bsModal = new bootstrap.Modal(els.modalCustom);
function setPreset(preset){
  const now = new Date();
  const to = now.toISOString().slice(0,10);
  let from = to;
  if (preset==='today'){ /* same day */ }
  else if (preset==='7'){ from = new Date(now.getTime() - 6*864e5).toISOString().slice(0,10); }
  else if (preset==='30'){ from = new Date(now.getTime() - 29*864e5).toISOString().slice(0,10); }
  state.range = { preset, from, to };
  els.rangeText.textContent = (preset==='today'?'Hari ini': preset==='7'?'7 hari':'30 hari');
  // set active item visual
  els.rangeMenu.querySelectorAll('.select-item').forEach(x=>x.classList.remove('active'));
  const active = els.rangeMenu.querySelector(`.select-item[data-value="${preset}"]`);
  if (active) active.classList.add('active');
  fetchData();
}
function applyRange(v){
  if (v === 'custom'){ bsModal.show(); return; }
  setPreset(v);
}
els.rangeBtn?.addEventListener('click', ()=>{
  const shown = els.rangeMenu.classList.toggle('show');
  els.rangeBtn.setAttribute('aria-expanded', shown ? 'true':'false');
  if (shown) els.rangeBtn.focus();
});
document.addEventListener('click', (e)=>{
  if (!els.rangeMenu.contains(e.target) && e.target !== els.rangeBtn) {
    els.rangeMenu.classList.remove('show');
    els.rangeBtn.setAttribute('aria-expanded','false');
  }
});
document.addEventListener('keydown',(e)=>{
  if (e.key === 'Escape'){ els.rangeMenu.classList.remove('show'); els.rangeBtn.setAttribute('aria-expanded','false'); }
});
els.rangeMenu.querySelectorAll('.select-item').forEach(it=>{
  it.addEventListener('click', ()=>{
    els.rangeMenu.querySelectorAll('.select-item').forEach(x=>x.classList.remove('active'));
    it.classList.add('active');
    els.rangeText.textContent = it.textContent.trim();
    applyRange(it.dataset.value);
  });
});
els.customForm?.addEventListener('submit',(ev)=>{
  ev.preventDefault();
  const from = els.from.value, to = els.to.value;
  if (!from || !to) return;
  state.range = { preset:'custom', from, to };
  els.rangeText.textContent = 'Custom…';
  bsModal.hide();
  fetchData();
});

/* ===== Fetch & Render ===== */
async function fetchData(){
  const p = new URLSearchParams({ page: state.page, per_page: state.per_page, sort: 'created_at_desc' });
  if (state.range.from) p.set('from', state.range.from);
  if (state.range.to)   p.set('to',   state.range.to);

  const labFrom = state.range.from || state.range.to || '';
  const labTo   = state.range.to   || state.range.from || '';
  els.periodText.textContent = (labFrom && labTo)
    ? `${labFrom.split('-').reverse().join('/')} – ${labTo.split('-').reverse().join('/')}`
    : '(semua)';

  els.tbody.innerHTML = `<tr><td colspan="7" class="muted">Memuat data…</td></tr>`;
  try{
    const res  = await fetch(`${API}?${p.toString()}`, {credentials:'same-origin'});
    const json = await res.json();
    if (!json.ok) throw new Error(json.error || 'Gagal memuat');
    // tampilkan hanya entitas order & payment (sesuai bisnis)
    state.rows = (json.data || []).filter(r => r && (r.entity==='order' || r.entity==='payment'));
    render(state.rows);
  }catch(e){
    els.tbody.innerHTML = `<tr><td colspan="7" class="muted">Error: ${h(e.message)}</td></tr>`;
  }
}
function render(rows){
  if (!rows.length){
    els.tbody.innerHTML = `<tr><td colspan="7" class="muted">Tidak ada data pada periode ini.</td></tr>`;
    return;
  }
  let html = '';
  rows.forEach(r=>{
    const aktivitas = summarize(r);
    const invoice   = extractInvoice(r);
    const actorName = r.actor_name ? String(r.actor_name) : (r.actor_id ? `ID #${r.actor_id}` : '—');
    const role      = r.actor_role || (r.actor_id ? '' : '—');
    html += `<tr>
      <td>${h(fmtDateTime(r.created_at))}</td>
      <td><span class="badge">${h(r.entity || '-')}</span></td>
      <td>${h(invoice)}</td>
      <td>${h(aktivitas)}</td>
      <td>${h(actorName)}</td>
      <td>${h(role || '')}</td>
      <td>${h(r.remark || '—')}</td>
    </tr>`;
  });
  els.tbody.innerHTML = html;
}

/* ===== Export CSV (UI sama finance, sumber dari state.rows) ===== */
els.btnExport?.addEventListener('click',(e)=>{
  e.preventDefault();
  const rows = state.rows || [];
  if (!rows.length){ alert('Tidak ada data untuk diekspor.'); return; }
  const headers = ['Tanggal','Jenis','Invoice','Aktivitas','Dibuat oleh','Role','Catatan'];
  const data = rows.map(r=>[
    fmtDateTime(r.created_at),
    r.entity || '',
    extractInvoice(r),
    summarize(r),
    r.actor_name ? String(r.actor_name) : (r.actor_id ? `ID #${r.actor_id}` : ''),
    r.actor_role || '',
    r.remark || ''
  ]);
  const csv = [headers.join(',')]
    .concat(data.map(arr=>arr.map(v=>`"${String(v??'').replace(/"/g,'""')}"`).join(',')))
    .join('\n');
  const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  const from = state.range.from || 'all';
  const to   = state.range.to   || 'all';
  a.download = `audit_${from}-${to}.csv`;
  a.click();
  URL.revokeObjectURL(a.href);
});

/* ===== Init (default 7 hari seperti finance) ===== */
setPreset('7');
</script>
</body>
</html>

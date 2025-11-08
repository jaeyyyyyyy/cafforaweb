<?php 
// public/karyawan/orders.php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../../backend/auth_guard.php';
require_login(['karyawan','admin']);
require_once __DIR__ . '/../../backend/config.php';

$userName = $_SESSION['user_name'] ?? 'Staff';
$userRole = $_SESSION['user_role'] ?? 'karyawan'; // kirim ke JS
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Orders — Karyawan Desk</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://code.iconify.design/2/2.2.1/iconify.min.js"></script>

  <style>
    :root{
      --gold:#ffd54f; --gold-soft:#f4d67a; --brown:#4B3F36; --ink:#111827;
      --radius:18px; --sidebar-w:320px;
    }
    body{ background:#FAFAFA; color:var(--ink); font-family:Inter,system-ui,Segoe UI,Roboto,Arial; }

    /* Sidebar */
    .sidebar{ position:fixed; left:-320px; top:0; bottom:0; width:var(--sidebar-w);
      background:#fff; border-right:1px solid rgba(0,0,0,.04); transition:left .25s ease;
      z-index:1050; padding:14px 18px 18px; overflow-y:auto; }
    .sidebar.show{ left:0; }
    .sidebar-head{ display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:10px; }
    .sidebar-inner-toggle,.sidebar-close-btn{ background:transparent;border:0; width:40px;height:36px; display:grid;place-items:center; }
    .hamb-icon{ width:24px; height:20px; display:flex; flex-direction:column; justify-content:space-between; gap:4px; }
    .hamb-icon span{ height:2px; background:var(--brown); border-radius:99px; }
    .sidebar .nav-link{ display:flex; align-items:center; gap:12px; padding:12px 14px; border-radius:16px; color:#111; font-weight:600; text-decoration:none; background:transparent; user-select:none; }
    .sidebar .nav-link:hover,.sidebar .nav-link:focus,.sidebar .nav-link:active{ background:rgba(255,213,79,0.25); color:#111; outline:none; box-shadow:none; }
    .sidebar hr{ border-color:rgba(0,0,0,.05); opacity:1; }
    .backdrop-mobile{ display:none; }
    .backdrop-mobile.active{ display:block; position:fixed; inset:0; background:rgba(0,0,0,.35); z-index:1040; }

    .content{ margin-left:0; padding:16px 14px 40px; }
    .topbar{ display:flex; align-items:center; gap:12px; margin-bottom:16px; }
    .btn-menu{ background:transparent; border:0; width:40px; height:38px; display:grid; place-items:center; }
    .search-box{ position:relative; flex:1 1 auto; min-width:0; }
    .search-input{ height:46px; width:100%; border-radius:9999px; padding-left:16px; padding-right:44px; border:1px solid #e5e7eb; background:#fff; outline:none !important; transition:border-color .12s ease; box-shadow:none !important; }
    .search-input:focus{ border-color:var(--gold-soft) !important; background:#fff; }
    .search-icon{ position:absolute; right:16px; top:50%; transform:translateY(-50%); font-size:1.1rem; color:var(--brown); cursor:pointer; }

    .top-actions{ display:flex; align-items:center; gap:14px; flex:0 0 auto; }
    .icon-btn{ width:38px; height:38px; border-radius:999px; display:flex; align-items:center; justify-content:center; color:var(--brown); text-decoration:none; }
    #badgeNotif.notif-dot{ position:absolute; top:3px; right:4px; width:8px; height:8px; background:#4b3f36; border-radius:50%; box-shadow:0 0 0 1.5px #fff; }
    #badgeNotif.d-none{ display:none !important; }

    .cardx{ background:#fff; border:1px solid #f7d78d; border-radius:var(--radius); padding:18px; }
    .table thead th{ background:#fffbe6; font-weight:600; }
    .table td, .table th{ vertical-align:middle; white-space:nowrap; }

    @media (min-width:992px){
      .content{ padding:20px 26px 50px; }
      .search-box{ max-width:1100px; }
    }

    /* Modal */
    #modalCancel .modal-content{ border-radius:18px; }
  </style>
</head>
<body>

<div id="backdrop" class="backdrop-mobile"></div>

<aside class="sidebar" id="sideNav">
  <div class="sidebar-head">
    <button class="sidebar-inner-toggle" id="toggleSidebarInside" aria-label="Tutup menu"></button>
    <button class="sidebar-close-btn" id="closeSidebar" aria-label="Tutup menu"><i class="bi bi-x-lg"></i></button>
  </div>
  <nav class="nav flex-column gap-2" id="sidebar-nav">
    <a class="nav-link" href="<?= BASE_URL ?>/public/karyawan/index.php"><i class="bi bi-house-door"></i> Dashboard</a>
    <a class="nav-link active" href="<?= BASE_URL ?>/public/karyawan/orders.php"><i class="bi bi-receipt"></i> Orders</a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/karyawan/stock.php"><i class="bi bi-box-seam"></i> Menu Stock</a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/karyawan/settings.php"><i class="bi bi-gear"></i> Settings</a>
    <hr>
    <a class="nav-link" href="<?= BASE_URL ?>/public/karyawan/help.php"><i class="bi bi-question-circle"></i> Help Center</a>
    <a class="nav-link" href="<?= BASE_URL ?>/backend/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
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
    </div>

    <div class="top-actions">
      <a id="btnBell" class="icon-btn position-relative text-decoration-none" href="<?= BASE_URL ?>/public/karyawan/notifications.php" aria-label="Notifikasi">
        <span class="iconify" data-icon="mdi:bell-outline" data-width="24" data-height="24"></span>
        <span id="badgeNotif" class="notif-dot d-none"></span>
      </a>
      <a class="icon-btn text-decoration-none" href="<?= BASE_URL ?>/public/karyawan/settings.php" aria-label="Akun">
        <span class="iconify" data-icon="mdi:account-circle-outline" data-width="28" data-height="28"></span>
      </a>
    </div>
  </div>

  <h2 class="fw-bold mb-3">Daftar Pesanan</h2>

  <div class="cardx">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th>Invoice</th>
            <th>Nama</th>
            <th>Total</th>
            <th>Pesanan</th>
            <th>Pembayaran</th>
            <th>Metode</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody id="rows">
          <tr><td colspan="7" class="text-center text-muted py-4">Memuat...</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</main>

<!-- Modal Batalkan Pesanan -->
<div class="modal fade" id="modalCancel" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <form class="modal-content" id="formCancel">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold">Batalkan Pesanan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body pt-2">
        <input type="hidden" name="order_id" id="cancel_order_id">
        <div class="alert alert-warning py-2 mb-3">
          Anda yakin ingin membatalkan pesanan <span id="cancel_invoice" class="fw-semibold"></span>?
        </div>

        <label class="form-label">Alasan pembatalan <span class="text-danger">*</span></label>
        <select class="form-select" name="reason_sel" id="cancel_reason_sel" required>
          <option value="">Pilih alasan…</option>
          <option>Stok habis / tidak mencukupi</option>
          <option>Pelanggan tidak melanjutkan (belum bayar)</option>
          <option>Salah input pesanan</option>
          <option>Menu tidak tersedia hari ini</option>
          <option value="__custom__">Lainnya (tulis manual)</option>
        </select>

        <div class="mt-2 d-none" id="cancel_reason_custom_wrap">
          <textarea class="form-control" id="cancel_reason_custom" rows="2" placeholder="Tulis alasan singkat"></textarea>
        </div>

        <div class="small text-muted mt-2">
          Pembatalan hanya untuk pesanan <strong>belum dibayar</strong>. Setelah batal, pembayaran ditandai <em>failed</em>.
        </div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button class="btn btn-light" type="button" data-bs-dismiss="modal">Tidak</button>
        <button class="btn btn-danger" type="submit">Ya, Batalkan</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const USER_ROLE = <?= json_encode($userRole, JSON_UNESCAPED_SLASHES) ?>;
const BASE      = "<?= rtrim(BASE_URL, '/') ?>";
const ORIGIN    = location.origin.replace(/^http:\/\//i, "https://");

/* ==== Endpoint clean + fallback legacy ==== */
const API_ORDERS_CLEAN  = ORIGIN + "/api/orders";
const API_ORDERS_LEGACY = ORIGIN + "/backend/api/orders.php";
const API_NOTIF_CLEAN   = ORIGIN + "/api/notifications?action=unread_count";
const API_NOTIF_LEGACY  = ORIGIN + "/backend/api/notifications.php?action=unread_count";

const $rows   = document.getElementById('rows');
const $search = document.getElementById('searchInput');

/* Sidebar */
const sideNav = document.getElementById('sideNav');
const backdrop = document.getElementById('backdrop');
document.getElementById('openSidebar')?.addEventListener('click', () => { sideNav.classList.add('show'); backdrop.classList.add('active'); });
document.getElementById('closeSidebar')?.addEventListener('click', () => { sideNav.classList.remove('show'); backdrop.classList.remove('active'); });
document.getElementById('toggleSidebarInside')?.addEventListener('click', () => { sideNav.classList.remove('show'); backdrop.classList.remove('active'); });
backdrop?.addEventListener('click', () => { sideNav.classList.remove('show'); backdrop.classList.remove('active'); });

/* Notif badge (clean → fallback) */
async function refreshNotif(){
  const badge = document.getElementById('badgeNotif'); if(!badge) return;
  let url = API_NOTIF_CLEAN;
  try{
    let r = await fetch(url, {credentials:"same-origin", cache:"no-store"});
    if(!r.ok) { url = API_NOTIF_LEGACY; r = await fetch(url, {credentials:"same-origin", cache:"no-store"}); }
    if(!r.ok) return;
    const d = await r.json(); const c = Number(d?.count ?? 0);
    badge.classList.toggle('d-none', !(c>0));
  }catch(e){}
}
refreshNotif(); setInterval(refreshNotif, 30000);

/* Helpers */
const rp = (n) => 'Rp ' + Number(n||0).toLocaleString('id-ID');
function badgeOrder(os){ const map={new:'secondary',processing:'primary',ready:'warning',completed:'success',cancelled:'dark'}; return `<span class="badge text-bg-${map[os]||'secondary'} fw-semibold text-capitalize">${os||'-'}</span>`; }
function badgePay(ps){ const map={pending:'warning',paid:'success',failed:'danger',refunded:'info',overdue:'secondary'}; return `<span class="badge text-bg-${map[ps]||'secondary'} fw-semibold text-capitalize">${ps||'-'}</span>`; }
function nextButtons(os){
  const order=['new','processing','ready','completed']; const idx=order.indexOf(os);
  if(idx===-1 || os==='completed' || os==='cancelled') return '<button class="btn btn-sm btn-outline-secondary" disabled>Selesai</button>';
  const next=order[idx+1]; const labelMap={processing:'Mulai', ready:'Siap', completed:'Selesai'};
  return `<button class="btn btn-sm btn-outline-primary" data-act="next" data-val="${next}">${labelMap[next]||'→'}</button>`;
}

async function requestJSON(url, opts){
  const res = await fetch(url, opts);
  const ct  = res.headers.get("content-type") || "";
  if (!ct.includes("application/json")) {
    throw new Error("non-json:"+res.status);
  }
  const js = await res.json();
  return { ok: res.ok, json: js };
}

/* Load table (clean → fallback) */
async function loadOrders(q=''){
  try{
    const params = new URLSearchParams({ action:'list' });
    if(q.trim()) params.set('q', q.trim());

    // coba clean
    let url = API_ORDERS_CLEAN + "?" + params.toString();
    let res, data;
    try{
      res = await requestJSON(url, { credentials:'same-origin', cache:'no-store' });
      data = res.json;
    }catch{
      // fallback legacy
      url = API_ORDERS_LEGACY + "?" + params.toString();
      res = await requestJSON(url, { credentials:'same-origin', cache:'no-store' });
      data = res.json;
    }

    if (!res.ok || !data || (data.ok===false)) {
      $rows.innerHTML = '<tr><td colspan="7" class="text-danger text-center py-4">'+(data?.error||'Gagal memuat')+'</td></tr>';
      return;
    }

    const items = Array.isArray(data?.items) ? data.items : [];
    if (!items.length){
      $rows.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Tidak ada data.</td></tr>';
      return;
    }

    $rows.innerHTML = items.map(it=>{
      const receiptPath = (USER_ROLE==='admin') ? '/public/admin/receipt.php?order='+it.id : '/public/karyawan/receipt.php?order='+it.id;
      const strukHref   = BASE + receiptPath;
      const strukDisabled = it.payment_status!=='paid';
      return `
      <tr data-id="${it.id}" data-invoice="${it.invoice_no||''}" data-pay="${it.payment_status||''}" data-ost="${it.order_status||''}">
        <td class="fw-semibold">${it.invoice_no||'-'}</td>
        <td>${it.customer_name||'-'}</td>
        <td>${rp(it.total)}</td>
        <td>${badgeOrder(it.order_status)}</td>
        <td>${badgePay(it.payment_status)}</td>
        <td>${it.payment_method||'-'}</td>
        <td class="d-flex flex-wrap gap-1">
          ${nextButtons(it.order_status)}
          <div class="btn-group">
            <button class="btn btn-sm btn-outline-success" data-act="pay" data-val="paid">Lunas</button>
            <button class="btn btn-sm btn-outline-warning" data-act="pay" data-val="pending">Pending</button>
          </div>
          <div class="btn-group">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">Metode</button>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="#" data-act="method" data-val="cash">Cash</a></li>
              <li><a class="dropdown-item" href="#" data-act="method" data-val="qris">QRIS</a></li>
              <li><a class="dropdown-item" href="#" data-act="method" data-val="bank_transfer">Transfer</a></li>
              <li><a class="dropdown-item" href="#" data-act="method" data-val="ewallet">e-Wallet</a></li>
            </ul>
          </div>
          <button class="btn btn-sm btn-outline-danger" data-act="cancel">Batal</button>
          <a class="btn btn-sm btn-outline-dark ${strukDisabled?'disabled':''} ms-1" ${strukDisabled?'aria-disabled="true"':''} href="${strukDisabled?'#':strukHref}">
            <i class="bi bi-printer me-1"></i> Struk
          </a>
        </td>
      </tr>`;
    }).join('');

    bindRowActions();
  }catch(e){
    console.error(e);
    $rows.innerHTML = '<tr><td colspan="7" class="text-danger text-center py-4">Gagal memuat.</td></tr>';
  }
}

/* Bind actions (update + cancel) */
function bindRowActions(){
  const cancelModal = new bootstrap.Modal(document.getElementById('modalCancel'));
  const selCancel   = document.getElementById('cancel_reason_sel');
  const wrapCustom  = document.getElementById('cancel_reason_custom_wrap');

  selCancel.onchange = ()=> wrapCustom.classList.toggle('d-none', selCancel.value!=='__custom__');

  $rows.querySelectorAll('[data-act]').forEach(btn=>{
    btn.addEventListener('click', async (ev)=>{
      ev.preventDefault();
      const tr=btn.closest('tr'); if(!tr) return;
      const id=tr.dataset.id; const act=btn.dataset.act;
      let payload={ id:Number(id) };

      if(act==='cancel'){
        if(tr.dataset.pay !== 'pending'){
          alert('Pesanan sudah dibayar atau sudah diproses refund. Pembatalan hanya untuk yang belum dibayar.');
          return;
        }
        document.getElementById('cancel_order_id').value = tr.dataset.id;
        document.getElementById('cancel_invoice').textContent = '#'+(tr.dataset.invoice||'');
        selCancel.value=''; 
        wrapCustom.classList.add('d-none'); 
        document.getElementById('cancel_reason_custom').value='';
        cancelModal.show();
        return;
      }

      if(act==='next'){ payload.order_status=btn.dataset.val; }
      else if(act==='pay'){ payload.payment_status=btn.dataset.val; }
      else if(act==='method'){ payload.payment_method=btn.dataset.val; }
      else { return; }

      // kirim ke clean, fallback legacy
      const headers={'Content-Type':'application/json'};
      let ok=false;
      try{
        let r = await fetch(API_ORDERS_CLEAN+'?action=update',{ method:'POST', headers, credentials:'same-origin', body:JSON.stringify(payload) });
        ok = r.ok; if(!ok) throw 0;
      }catch{
        let r = await fetch(API_ORDERS_LEGACY+'?action=update',{ method:'POST', headers, credentials:'same-origin', body:JSON.stringify(payload) });
        ok = r.ok;
      }
      if(ok) loadOrders($search.value);
    });
  });

  // submit cancel -> orders_cancel.php
  document.getElementById('formCancel').addEventListener('submit', async (e)=>{
    e.preventDefault();
    const btn = e.submitter || document.querySelector('#formCancel button[type="submit"]');
    const id  = document.getElementById('cancel_order_id').value;
    const opt = document.getElementById('cancel_reason_sel').value;
    const cus = document.getElementById('cancel_reason_custom').value.trim();
    const reason = (opt==='__custom__') ? (cus || 'Alasan lain') : opt;

    if(!id || !reason){ alert('Lengkapi alasan.'); return; }

    btn.disabled = true; btn.textContent = 'Memproses...';

    try{
      const res = await fetch(BASE + '/backend/api/orders_cancel.php', {
        method:'POST',
        credentials:'same-origin',
        headers:{
          'Accept':'application/json',
          'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: new URLSearchParams({ order_id:id, reason })
      });

      const raw = await res.text();
      let js; try{ js = JSON.parse(raw); }catch{ js = null; }

      if(!res.ok || !js || js.ok!==true){
        console.error('orders_cancel.php response:', res.status, raw);
        alert((js && js.error) ? js.error : 'Gagal membatalkan pesanan.');
        return;
      }

      bootstrap.Modal.getInstance(document.getElementById('modalCancel')).hide();
      await loadOrders($search.value);
    }catch(err){
      console.error(err);
      alert('Terjadi masalah jaringan.');
    }finally{
      btn.disabled = false; btn.textContent = 'Ya, Batalkan';
    }
  });
}

/* Search + init */
$search.addEventListener('input', ()=> loadOrders($search.value));
document.getElementById('searchIcon').addEventListener('click', ()=> loadOrders($search.value));
document.addEventListener('DOMContentLoaded', ()=>{ loadOrders(); refreshNotif(); });
</script>
</body>
</html>

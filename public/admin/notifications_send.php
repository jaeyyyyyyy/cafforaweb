<?php
// public/admin/notifications_send.php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../../backend/auth_guard.php';
require_login(['admin']);
require_once __DIR__ . '/../../backend/config.php';

// ambil daftar user untuk opsi “user tertentu”
$users = [];
if ($res = $conn->query("SELECT id, name, email, role FROM users ORDER BY role, name")) {
  $users = $res->fetch_all(MYSQLI_ASSOC);
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Kirim Notifikasi — Admin Panel</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://code.iconify.design/2/2.2.1/iconify.min.js"></script>

<style>
:root{
  --gold:#ffd54f;
  --gold-soft:#f4d67a;
  --brown:#4B3F36;
  --ink:#111827;
  --radius:18px;
  --sidebar-w:320px;
  --line:#E8E2DA;
  --input-border:#E8E2DA;
}
*,:before,:after{ box-sizing:border-box; }
html,body{ height:100%; }
body{
  background:#FAFAFA;
  color:var(--ink);
  font-family:Inter,system-ui,Segoe UI,Roboto,Arial;
  -webkit-tap-highlight-color:transparent;
}

/* ===== Custom radio agar tidak biru ===== */
input[type="radio"],
input[type="checkbox"]{
  accent-color: var(--gold); /* ubah titik radio jadi gold */
  cursor: pointer;
}
input[type="radio"]:focus-visible,
input[type="checkbox"]:focus-visible{
  outline:none;
  box-shadow:0 0 0 3px rgba(255,213,79,.35);
  border-radius:50%;
}
label{
  cursor:pointer;
}

/* ===== Sidebar ===== */
.sidebar{
  position:fixed;
  left:-320px;top:0;bottom:0;
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
  display:flex;align-items:center;justify-content:space-between;
  gap:10px;margin-bottom:10px;
}
.sidebar-inner-toggle,.sidebar-close-btn{
  background:transparent;border:0;width:40px;height:36px;
  display:grid;place-items:center;
}
.hamb-icon{
  width:24px;height:20px;display:flex;flex-direction:column;
  justify-content:space-between;gap:4px;
}
.hamb-icon span{height:2px;background:var(--brown);border-radius:99px;}
.sidebar .nav-link{
  display:flex;align-items:center;gap:12px;
  padding:12px 14px;border-radius:16px;color:#111;font-weight:600;
  text-decoration:none;background:transparent;user-select:none;
}
.sidebar .nav-link:hover,
.sidebar .nav-link:focus,
.sidebar .nav-link:active{
  background:rgba(255,213,79,0.25);
  color:#111;outline:none;box-shadow:none;
}
.sidebar hr{border-color:rgba(0,0,0,.05);opacity:1;}

/* ===== content ===== */
.content{margin-left:0;padding:16px 14px 40px;}

/* ===== topbar ===== */
.topbar{
  display:flex;align-items:center;gap:12px;margin-bottom:16px;
}
.btn-menu{
  background:transparent;border:0;width:40px;height:38px;
  display:grid;place-items:center;flex:0 0 auto;
}

/* ===== SEARCH ===== */
.search-box{position:relative;flex:1 1 auto;min-width:0;}
.search-input{
  height:46px;width:100%;border-radius:9999px;
  padding-left:16px;padding-right:44px;
  border:1px solid #e5e7eb;background:#fff;
  outline:none!important;transition:border-color .12s ease;
}
.search-input:focus{
  border-color:var(--gold-soft)!important;
  background:#fff;box-shadow:none!important;
}
.search-icon{
  position:absolute;right:16px;top:50%;
  transform:translateY(-50%);
  font-size:1.1rem;color:var(--brown);cursor:pointer;
}
.search-suggest{
  position:absolute;top:100%;left:0;margin-top:6px;background:#fff;
  border:1px solid rgba(247,215,141,.8);border-radius:16px;
  box-shadow:0 12px 28px rgba(0,0,0,.08);
  width:100%;max-height:280px;overflow-y:auto;display:none;z-index:40;
}
.search-suggest.visible{display:block;}
.search-suggest .item{
  padding:10px 14px 6px;border-bottom:1px solid rgba(0,0,0,.03);cursor:pointer;
}
.search-suggest .item:last-child{border-bottom:0;}
.search-suggest .item:hover{background:#fffbea;}
.search-suggest .item small{
  display:block;color:#6b7280;font-size:.74rem;margin-top:2px;
}
.search-empty{padding:12px 14px;color:#6b7280;font-size:.8rem;}

.top-actions{display:flex;align-items:center;gap:14px;flex:0 0 auto;}
.icon-btn{
  width:38px;height:38px;border-radius:999px;
  display:flex;align-items:center;justify-content:center;
  color:var(--brown);text-decoration:none;background:transparent;outline:none;
}
.icon-btn:focus,.icon-btn:active{
  outline:none;box-shadow:none;color:var(--brown);
}
#btnBell{position:relative;}
#badgeNotif.notif-dot{
  position:absolute;top:3px;right:5px;width:8px;height:8px;
  background:#4b3f36;border-radius:50%;
  display:inline-block;box-shadow:0 0 0 1.5px #fff;
}
#badgeNotif.d-none{display:none!important;}

/* ===== Kartu & Form ===== */
.cardx{background:#fff;border:1px solid #f7d78d;
  border-radius:var(--radius);padding:18px;}
.form-label{font-weight:600;margin-bottom:.35rem;}
.form-control,.form-select{
  height:46px;border-radius:14px!important;
  border:1px solid var(--line)!important;
  background:#fff!important;color:#111!important;
  box-shadow:none!important;outline:0!important;
}
.form-control:focus{
  border-color:var(--gold-soft)!important;
  box-shadow:none!important;
}
textarea.form-control{min-height:120px;resize:none;}
.btn-saffron{
  display:inline-flex;align-items:center;gap:8px;
  background:var(--gold);color:#111;font-weight:700;
  padding:.6rem 1.15rem;border-radius:14px;
  border:1px solid rgba(0,0,0,.02);
}

/* ===== Custom dropdown cf-select ===== */
.cf-select{position:relative;width:100%;}
.cf-select__trigger{
  width:100%;background:#fff;
  border:1px solid var(--input-border);
  border-radius:14px;
  padding:8px 38px 8px 14px;
  display:flex;align-items:center;justify-content:space-between;
  gap:12px;cursor:pointer;transition:border-color .12s ease;
  user-select:none;outline:none;
  -webkit-tap-highlight-color:transparent;
}
.cf-select__trigger:focus-visible,
.cf-select.is-open .cf-select__trigger{
  border-color:var(--gold-soft);outline:none;
}
.cf-select__text{
  font-size:.95rem;color:#2b2b2b;
  overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
}
.cf-select__icon{flex:0 0 auto;color:var(--brown);font-size:.9rem;}
.cf-select__list{
  position:absolute;left:0;top:calc(100% + 6px);
  width:100%;background:#fff;border:1px solid rgba(0,0,0,.02);
  border-radius:14px;box-shadow:0 16px 30px rgba(0,0,0,.09);
  overflow:hidden;z-index:40;display:none;
  max-height:260px;overflow-y:auto;
}
.cf-select.is-open .cf-select__list{display:block;}
.cf-select__option{
  padding:9px 14px;font-size:.9rem;color:#413731;
  cursor:pointer;background:#fff;
}
.cf-select__option:hover{background:#FFF2C9;}
.cf-select__option.is-active{background:#FFEB9B;font-weight:600;}
.cf-select.is-disabled .cf-select__trigger{
  background:#f9fafb;border-color:#ececec;color:#9ca3af;cursor:not-allowed;
}
.cf-select.is-disabled .cf-select__icon{color:#c1c1c1;}

/* ===== Responsive HP ===== */
@media screen and (max-width:768px){
  .topbar{padding-left:12px;padding-right:12px;}
  .topbar .inner{padding-left:12px;padding-right:12px;max-width:100%;}
}

@media screen and (min-width: 1450px){
  .content
    padding-left: 10px !important;
    padding-right: 30px !important;
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
  <!-- Topbar -->
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

  <h2 class="fw-bold mb-3">Kirim Notifikasi</h2>

  <div class="cardx">
    <form id="notifForm" class="vstack gap-3">
      <!-- Target -->
      <div>
        <label class="form-label">Target</label>
        <div class="d-flex flex-wrap gap-4">
          <div class="form-check">
            <input class="form-check-input" type="radio" name="target_type" id="tAll"  value="all" checked>
            <label class="form-check-label" for="tAll">Semua (Customer & Karyawan)</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="target_type" id="tRoleC" value="role_customer">
            <label class="form-check-label" for="tRoleC">Role: Customer</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="target_type" id="tRoleK" value="role_karyawan">
            <label class="form-check-label" for="tRoleK">Role: Karyawan</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="target_type" id="tUser" value="user">
            <label class="form-check-label" for="tUser">User tertentu</label>
          </div>
        </div>

        <!-- cf-select user tertentu -->
        <div class="mt-2">
          <div class="cf-select is-disabled" id="userSelect" data-target="target_user">
            <div class="cf-select__trigger" tabindex="0" aria-disabled="true">
              <span class="cf-select__text" id="user_label">Pilih user</span>
              <i class="bi bi-chevron-down cf-select__icon"></i>
            </div>
            <div class="cf-select__list" id="user_list">
              <div class="cf-select__option is-active" data-value="">Pilih user</div>
              <?php foreach ($users as $u):
                $label = ($u['name'] ?: $u['email']).' — '.$u['role']; ?>
                <div class="cf-select__option" data-value="<?= (int)$u['id'] ?>">
                  <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <!-- hidden value yang dikirim ke API -->
          <input type="hidden" id="target_user" value="">
        </div>
      </div>

      <!-- Pesan -->
      <div>
        <label class="form-label">Pesan</label>
        <textarea id="message" class="form-control" rows="4" placeholder="Tulis pesan (mis. promo, pengumuman…)" required></textarea>
      </div>

      <!-- Link -->
      <div>
        <label class="form-label">Link (opsional)</label>
        <input id="link" type="url" class="form-control" placeholder="https://...">
      </div>

      <!-- Submit -->
      <div class="d-flex gap-2">
        <button class="btn-saffron" type="submit">
          <i class="bi bi-send"></i> Kirim
        </button>
        <span id="result" class="align-self-center small text-muted"></span>
      </div>
    </form>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  const BASE = "<?= rtrim(BASE_URL, '/') ?>";

  // ===== Sidebar
  const sideNav  = document.getElementById('sideNav');
  const backdrop = document.getElementById('backdrop');
  document.getElementById('openSidebar')?.addEventListener('click', ()=>{ sideNav.classList.add('show'); backdrop.classList.add('active'); });
  document.getElementById('closeSidebar')?.addEventListener('click', ()=>{ sideNav.classList.remove('show'); backdrop.classList.remove('active'); });
  backdrop?.addEventListener('click', ()=>{ sideNav.classList.remove('show'); backdrop.classList.remove('active'); });

  // ===== Badge notif
  fetch(BASE + "/backend/api/notifications.php?action=unread_count", {credentials:'same-origin'})
    .then(r=>r.json()).then(js=>{
      const dot = document.getElementById('badgeNotif');
      if (js && js.ok && Number(js.count||0) > 0) dot.classList.remove('d-none');
      else dot.classList.add('d-none');
    }).catch(()=>{});

  // ===== cf-select behaviour
  (function initCfSelect(){
    const wrap    = document.getElementById('userSelect');
    const trigger = wrap.querySelector('.cf-select__trigger');
    const list    = wrap.querySelector('.cf-select__list');
    const label   = document.getElementById('user_label');
    const hidden  = document.getElementById('target_user');

    // buka/tutup
    function toggleOpen(){
      if (wrap.classList.contains('is-disabled')) return;
      wrap.classList.toggle('is-open');
    }
    trigger.addEventListener('click', (e)=>{ e.stopPropagation(); toggleOpen(); });
    trigger.addEventListener('keydown', (e)=>{ if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggleOpen(); }});
    document.addEventListener('click', ()=> wrap.classList.remove('is-open'));

    // pilih option
    list.querySelectorAll('.cf-select__option').forEach(opt=>{
      opt.addEventListener('click', ()=>{
        const val = opt.dataset.value || '';
        hidden.value = val;
        label.textContent = opt.textContent.trim();
        list.querySelectorAll('.cf-select__option').forEach(o=>o.classList.remove('is-active'));
        opt.classList.add('is-active');
        wrap.classList.remove('is-open');
      });
    });

    // sync radio → enable/disable
    const rAll  = document.getElementById('tAll');
    const rRC   = document.getElementById('tRoleC');
    const rRK   = document.getElementById('tRoleK');
    const rUser = document.getElementById('tUser');
    function updateUserSelect(){
      const dis = !rUser.checked;
      if (dis){
        wrap.classList.add('is-disabled'); wrap.classList.remove('is-open');
        hidden.value = ''; label.textContent = 'Pilih user';
        list.querySelectorAll('.cf-select__option').forEach(o=>o.classList.remove('is-active'));
        list.querySelector('.cf-select__option[data-value=""]')?.classList.add('is-active');
        trigger.setAttribute('aria-disabled','true');
      }else{
        wrap.classList.remove('is-disabled');
        trigger.removeAttribute('aria-disabled');
      }
    }
    [rAll,rRC,rRK,rUser].forEach(r => r.addEventListener('change', updateUserSelect));
    updateUserSelect();
  })();

  // ===== Submit form
  document.getElementById('notifForm').addEventListener('submit', async (e)=>{
    e.preventDefault();
    const msg  = document.getElementById('message').value.trim();
    const link = document.getElementById('link').value.trim();
    if (!msg) { alert('Pesan wajib diisi'); return; }

    const rAll  = document.getElementById('tAll').checked;
    const rRC   = document.getElementById('tRoleC').checked;
    const rRK   = document.getElementById('tRoleK').checked;
    const rUser = document.getElementById('tUser').checked;
    const uid   = document.getElementById('target_user').value.trim();

    const fd = new FormData();
    fd.append('action','create');
    if (rAll) {
      fd.append('target_type','all');
    } else if (rRC) {
      fd.append('target_type','role'); fd.append('target_role','customer');
    } else if (rRK) {
      fd.append('target_type','role'); fd.append('target_role','karyawan');
    } else if (rUser) {
      if (!uid) { alert('Pilih user'); return; }
      fd.append('target_type','user'); fd.append('target_user', uid);
    }
    fd.append('message', msg);
    fd.append('link', link);

    const $res = document.getElementById('result');
    $res.textContent = 'Mengirim…';
    try{
      const res  = await fetch(BASE + '/backend/api/notifications.php', { method:'POST', credentials:'same-origin', body: fd });
      const data = await res.json();
      if (data.ok) {
        $res.textContent = 'Sukses ✓'; $res.style.color = '#16a34a';
        setTimeout(()=>{$res.textContent=''; $res.removeAttribute('style');}, 1500);
        (e.target).reset();
        // reset cf-select ke default
        document.getElementById('target_user').value = '';
        document.getElementById('user_label').textContent = 'Pilih user';
        document.querySelectorAll('#user_list .cf-select__option').forEach(o=>o.classList.remove('is-active'));
        document.querySelector('#user_list .cf-select__option[data-value=""]')?.classList.add('is-active');
      } else {
        $res.textContent = data.error || 'Gagal mengirim.';
      }
    }catch(err){ $res.textContent = 'Gagal mengirim.'; }
  });

  /* ===== SEARCH (admin → fallback karyawan) ===== */
  function attachSearch(inputEl, suggestEl){
    if (!inputEl || !suggestEl) return;
    const ADMIN_EP = BASE + "/backend/api/admin_search.php";
    const KARY_EP  = BASE + "/backend/api/karyawan_search.php";

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
      let html=''; arr.forEach(r => {
        html += `<div class="item" data-type="${r.type}" data-key="${r.key}">
                   ${r.label ?? ''}${r.sub ? `<small>${r.sub}</small>` : ''}
                 </div>`;
      });
      suggestEl.innerHTML = html; suggestEl.classList.add('visible');
      suggestEl.querySelectorAll('.item').forEach(it => {
        it.addEventListener('click', () => {
          const type = it.dataset.type; const key = it.dataset.key;
          if (type === 'order')      window.location = BASE + "/public/admin/orders.php?search="  + encodeURIComponent(key);
          else if (type === 'menu')  window.location = BASE + "/public/admin/catalog.php?search=" + encodeURIComponent(key);
          else if (type === 'user')  window.location = BASE + "/public/admin/users.php?search="   + encodeURIComponent(key);
          else                       window.location = BASE + "/public/admin/orders.php?search="  + encodeURIComponent(key);
        });
      });
    });

    document.addEventListener('click', (ev) => { if (!suggestEl.contains(ev.target) && ev.target !== inputEl){ suggestEl.classList.remove('visible'); } });
    document.getElementById('searchIcon')?.addEventListener('click', () => inputEl.focus());
  }
  attachSearch(document.getElementById('searchInput'), document.getElementById('searchSuggest'));
</script>
</body>
</html>

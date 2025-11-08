<?php
// public/admin/index.php
declare(strict_types=1);

require_once __DIR__ . '/../../backend/auth_guard.php';
require_login(['admin']); // pastikan hanya admin

// Ambil profil singkat dari session (sudah diisi di require_login)
$name  = $_SESSION['user_name']  ?? 'Admin Caffora';
$email = $_SESSION['user_email'] ?? 'admncaffora@gmail.com';
?>
<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
  <meta charset="utf-8" />
  <title>Caffora — Admin Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet" />

  <!-- Bootstrap & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />

  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

  <style>
    :root{
      /* Palet modern netral + aksen biru */
      --bg: #f6f7fb;
      --card: #ffffff;
      --ink: #111827;
      --muted: #6b7280;
      --brand: #2563eb;         /* biru */
      --brand-weak: #e0ebff;
      --ring: rgba(37, 99, 235, .25);
      --border: #e5e7eb;
    }
    [data-bs-theme="dark"]{
      --bg: #0f172a;
      --card: #111827;
      --ink: #e5e7eb;
      --muted: #9ca3af;
      --brand: #60a5fa;
      --brand-weak: #1e293b;
      --ring: rgba(96, 165, 250, .28);
      --border: #1f2937;
    }

    html,body{height:100%}
    body{
      font-family: "Inter", system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
      background: var(--bg);
      color: var(--ink);
      margin: 0;
    }

    .app{
      display: grid;
      grid-template-columns: 260px 1fr;
      grid-template-rows: 64px 1fr;
      grid-template-areas:
        "sidebar topbar"
        "sidebar main";
      min-height: 100vh;
    }

    /* Topbar */
    .topbar{
      grid-area: topbar;
      background: var(--card);
      border-bottom: 1px solid var(--border);
      display:flex;align-items:center;justify-content:space-between;
      padding: 0 16px;
      position: sticky; top:0; z-index: 1030;
    }
    .brand{
      display:flex;align-items:center;gap:10px;
      font-family:"Playfair Display", serif;
      font-weight:700;
    }
    .brand-badge{
      width:30px;height:30px;border-radius:8px;
      background: var(--brand-weak);
      display:grid;place-items:center;
      color: var(--brand); font-weight:700;
      border: 1px solid var(--border);
    }
    .top-actions{display:flex;align-items:center;gap:10px}
    .icon-btn{
      width:40px;height:40px;border-radius:12px;
      display:grid;place-items:center;
      background: var(--card);
      border:1px solid var(--border);
      transition:.15s;
    }
    .icon-btn:hover{box-shadow:0 0 0 .25rem var(--ring)}
    .notif-dot{
      position:absolute;top:6px;right:6px;
      width:8px;height:8px;border-radius:999px;background:#ef4444;
    }

    /* Sidebar */
    .sidebar{
      grid-area: sidebar;
      background: var(--card);
      border-right: 1px solid var(--border);
      padding: 14px 12px;
      position: sticky; top:0; height:100vh;
    }
    .side-title{font-weight:700; font-size:14px; margin: 6px 8px 12px; color: var(--muted)}
    .nav-side{display:flex;flex-direction:column;gap:6px}
    .nav-side a{
      display:flex;align-items:center;gap:10px;
      padding:10px 12px;border-radius:10px;
      color:var(--ink); text-decoration:none; font-weight:600;
      border:1px solid transparent;
    }
    .nav-side a:hover{background: var(--brand-weak); border-color: var(--border)}
    .nav-side a.active{background: var(--brand); color:#fff}

    /* Main */
    .main{ grid-area: main; padding: 18px; }
    .content{ max-width: 1240px; margin: 0 auto; }

    /* Cards */
    .kpi-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
    @media (max-width: 1100px){ .kpi-grid{grid-template-columns:repeat(2,minmax(0,1fr))} }
    @media (max-width: 640px){ .kpi-grid{grid-template-columns:1fr} }
    .card{
      background: var(--card); border:1px solid var(--border);
      border-radius:14px; box-shadow: 0 8px 30px rgba(0,0,0,.03);
    }
    .card-hd{padding:14px 16px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center}
    .card-bd{padding:16px}

    .kpi{display:flex;gap:12px;align-items:center}
    .kpi .ico{width:42px;height:42px;border-radius:10px;display:grid;place-items:center;background:var(--brand-weak);color:var(--brand);font-size:18px;border:1px solid var(--border)}
    .kpi .lbl{color:var(--muted);font-weight:600;font-size:12px}
    .kpi .val{font-weight:800;font-size:20px}

    .table thead th{color:var(--muted); border-bottom:0}
    .table tbody td{vertical-align:middle}
    .badge-soft{
      padding:6px 10px;border-radius:999px;font-weight:700;font-size:12px;border:1px solid var(--border);
      background: #f1f5f9; color: #0f172a;
    }
    [data-bs-theme="dark"] .badge-soft{background: #111827; color:#e5e7eb}

    /* Sales filters */
    .filter-bar{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
    .btn-pill{
      border:1px solid var(--border); background: var(--card); color: var(--ink);
      height:36px; border-radius:999px; padding:0 12px; font-weight:600;
    }
    .btn-pill.active{background: var(--brand); color:#fff; border-color: transparent}

    /* Sticky section tabs */
    .section-tabs{position: sticky; top: 64px; background: var(--bg); padding:6px 0; z-index: 1029; border-bottom:1px solid var(--border)}
    .section-tabs .tab{padding:8px 12px;border-radius:999px;border:1px solid var(--border);background:var(--card);color:var(--ink);text-decoration:none;font-weight:600;margin-right:8px}
    .section-tabs .tab.active{background:var(--brand);color:#fff;border-color:transparent}
  </style>
</head>
<body>
<div class="app">

  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="side-title">Caffora Admin</div>
    <nav class="nav-side" id="sideNav">
      <a href="#overview" class="active"><i class="bi bi-speedometer2"></i><span>Overview</span></a>
      <a href="#orders"><i class="bi bi-basket2"></i><span>Orders</span></a>
      <a href="#catalog"><i class="bi bi-collection"></i><span>Catalog</span></a>
      <a href="#users"><i class="bi bi-people"></i><span>Users</span></a>
      <a href="#finance"><i class="bi bi-cash-coin"></i><span>Finance</span></a>
      <a href="#todo"><i class="bi bi-check2-square"></i><span>To-Do</span></a>
      <a href="#settings"><i class="bi bi-gear"></i><span>Settings</span></a>
    </nav>
  </aside>

  <!-- Topbar -->
  <header class="topbar">
    <div class="brand">
      <div class="brand-badge">C</div>
      <div>Admin Dashboard</div>
    </div>
    <div class="top-actions">
      <!-- theme toggle -->
      <button class="icon-btn position-relative" id="themeToggle" title="Toggle theme" aria-label="Toggle theme">
        <i class="bi bi-moon-stars" id="themeIcon"></i>
      </button>
      <!-- notifications -->
      <div class="position-relative">
        <button class="icon-btn" id="notifBtn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifikasi">
          <i class="bi bi-bell"></i>
        </button>
        <span class="notif-dot" id="notifDot" style="display:none"></span>
        <ul class="dropdown-menu dropdown-menu-end shadow" id="notifMenu" style="min-width:320px">
          <li class="dropdown-header fw-semibold">Notifikasi</li>
          <li><hr class="dropdown-divider"></li>
          <li class="px-3 small text-muted" id="notifEmpty">Tidak ada notifikasi.</li>
        </ul>
      </div>
      <!-- profile -->
      <div class="dropdown">
        <button class="icon-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Profil">
          <i class="bi bi-person-circle"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow">
          <li class="px-3 py-2">
            <div class="small text-muted">Masuk sebagai</div>
            <div class="fw-semibold"><?= htmlspecialchars($name) ?></div>
            <div class="small text-muted text-truncate" style="max-width:240px"><?= htmlspecialchars($email) ?></div>
          </li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="../../"><i class="bi bi-house-door me-2"></i>Lihat Store</a></li>
          <li><a class="dropdown-item text-danger" href="../../backend/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Keluar</a></li>
        </ul>
      </div>
    </div>
  </header>

  <!-- Main -->
  <main class="main">
    <div class="content">

      <!-- Tabs (sticky) -->
      <div class="section-tabs mb-3" id="sectionTabs">
        <a class="tab active" href="#overview">Overview</a>
        <a class="tab" href="#orders">Orders</a>
        <a class="tab" href="#catalog">Catalog</a>
        <a class="tab" href="#users">Users</a>
        <a class="tab" href="#finance">Finance</a>
        <a class="tab" href="#todo">To-Do</a>
        <a class="tab" href="#settings">Settings</a>
      </div>

      <!-- OVERVIEW -->
      <section id="overview" class="mb-4">
        <div class="kpi-grid mb-3">
          <div class="card">
            <div class="card-bd">
              <div class="kpi">
                <div class="ico"><i class="bi bi-basket2"></i></div>
                <div>
                  <div class="lbl">Pesanan Hari Ini</div>
                  <div class="val" id="kpiOrders">0</div>
                </div>
              </div>
            </div>
          </div>
          <div class="card">
            <div class="card-bd">
              <div class="kpi">
                <div class="ico"><i class="bi bi-cash-stack"></i></div>
                <div>
                  <div class="lbl">Pendapatan Hari Ini</div>
                  <div class="val" id="kpiRevenue">Rp 0</div>
                </div>
              </div>
            </div>
          </div>
          <div class="card">
            <div class="card-bd">
              <div class="kpi">
                <div class="ico"><i class="bi bi-people"></i></div>
                <div>
                  <div class="lbl">Total Customer</div>
                  <div class="val" id="kpiCustomers">0</div>
                </div>
              </div>
            </div>
          </div>
          <div class="card">
            <div class="card-bd">
              <div class="kpi">
                <div class="ico"><i class="bi bi-collection"></i></div>
                <div>
                  <div class="lbl">Item Menu</div>
                  <div class="val" id="kpiMenu">0</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Sales analytics -->
        <div class="card">
          <div class="card-hd">
            <div class="fw-semibold">Sales Analytics</div>
            <div class="filter-bar">
              <button class="btn btn-pill" data-range="today">Today</button>
              <button class="btn btn-pill" data-range="week">This Week</button>
              <button class="btn btn-pill active" data-range="month">This Month</button>
              <div class="d-flex align-items-center gap-1 ms-1">
                <input type="date" class="form-control form-control-sm" id="fromDate" />
                <span class="small">—</span>
                <input type="date" class="form-control form-control-sm" id="toDate" />
                <button class="btn btn-pill" id="btnCustom">Custom</button>
              </div>
            </div>
          </div>
          <div class="card-bd">
            <canvas id="salesChart" height="88"></canvas>
          </div>
        </div>
      </section>

      <!-- ORDERS -->
      <section id="orders" class="mb-4">
        <div class="card">
          <div class="card-hd">
            <div class="fw-semibold">Pesanan Terbaru</div>
            <div>
              <a href="#" class="btn btn-sm btn-outline-secondary">Export</a>
            </div>
          </div>
          <div class="card-bd">
            <div class="table-responsive">
              <table class="table align-middle">
                <thead><tr>
                  <th>#</th><th>Tanggal</th><th>Pelanggan</th><th>Total</th><th>Status</th><th>Aksi</th>
                </tr></thead>
                <tbody id="ordersBody">
                  <tr><td colspan="6" class="text-center text-muted py-4">Memuat…</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </section>

      <!-- CATALOG -->
      <section id="catalog" class="mb-4">
        <div class="card">
          <div class="card-hd">
            <div class="fw-semibold">Catalog</div>
            <a href="#" class="btn btn-sm btn-primary">+ Tambah Item</a>
          </div>
          <div class="card-bd">
            <div class="table-responsive">
              <table class="table align-middle">
                <thead><tr>
                  <th>#</th><th>Nama</th><th>Kategori</th><th>Harga</th><th>Stok</th><th>Aksi</th>
                </tr></thead>
                <tbody id="catalogBody">
                  <tr><td colspan="6" class="text-center text-muted py-4">Memuat…</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </section>

      <!-- USERS -->
      <section id="users" class="mb-4">
        <div class="card">
          <div class="card-hd">
            <div class="fw-semibold">Users</div>
            <div class="d-flex gap-2">
              <a href="#" class="btn btn-sm btn-outline-secondary">Export</a>
              <a href="#" class="btn btn-sm btn-outline-secondary">Tambah User</a>
            </div>
          </div>
          <div class="card-bd">
            <div class="row g-3">
              <div class="col-md-4">
                <div class="card"><div class="card-bd">
                  <div class="kpi">
                    <div class="ico"><i class="bi bi-person-badge"></i></div>
                    <div>
                      <div class="lbl">Admin</div>
                      <div class="val" id="uAdmin">0</div>
                    </div>
                  </div>
                </div></div>
              </div>
              <div class="col-md-4">
                <div class="card"><div class="card-bd">
                  <div class="kpi">
                    <div class="ico"><i class="bi bi-person-workspace"></i></div>
                    <div>
                      <div class="lbl">Karyawan</div>
                      <div class="val" id="uStaff">0</div>
                    </div>
                  </div>
                </div></div>
              </div>
              <div class="col-md-4">
                <div class="card"><div class="card-bd">
                  <div class="kpi">
                    <div class="ico"><i class="bi bi-people"></i></div>
                    <div>
                      <div class="lbl">Customer</div>
                      <div class="val" id="uCust">0</div>
                    </div>
                  </div>
                </div></div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- FINANCE -->
      <section id="finance" class="mb-4">
        <div class="card">
          <div class="card-hd">
            <div class="fw-semibold">Finance</div>
            <div class="text-muted small">Ringkasan transaksi</div>
          </div>
          <div class="card-bd">
            <div class="row g-3">
              <div class="col-md-4">
                <div class="card"><div class="card-bd">
                  <div class="kpi">
                    <div class="ico"><i class="bi bi-wallet2"></i></div>
                    <div>
                      <div class="lbl">Pendapatan Bulan Ini</div>
                      <div class="val" id="finMonth">Rp 0</div>
                    </div>
                  </div>
                </div></div>
              </div>
              <div class="col-md-4">
                <div class="card"><div class="card-bd">
                  <div class="kpi">
                    <div class="ico"><i class="bi bi-clipboard2-data"></i></div>
                    <div>
                      <div class="lbl">Rata-rata Order</div>
                      <div class="val" id="finAOV">Rp 0</div>
                    </div>
                  </div>
                </div></div>
              </div>
              <div class="col-md-4">
                <div class="card"><div class="card-bd">
                  <div class="kpi">
                    <div class="ico"><i class="bi bi-graph-up"></i></div>
                    <div>
                      <div class="lbl">Growth vs. Last Month</div>
                      <div class="val" id="finGrowth">0%</div>
                    </div>
                  </div>
                </div></div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- TO-DO -->
      <section id="todo" class="mb-4">
        <div class="card">
          <div class="card-hd">
            <div class="fw-semibold">To-Do</div>
            <button class="btn btn-sm btn-outline-secondary" id="addTodo">+ Tambah</button>
          </div>
          <div class="card-bd">
            <ul class="list-group" id="todoList">
              <li class="list-group-item text-muted">Memuat…</li>
            </ul>
          </div>
        </div>
      </section>

      <!-- SETTINGS -->
      <section id="settings" class="mb-5">
        <div class="card">
          <div class="card-hd"><div class="fw-semibold">Settings</div></div>
          <div class="card-bd">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold">Nama Brand</label>
                <input class="form-control" value="Caffora" disabled />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Mode Tampilan</label><br />
                <button class="btn btn-outline-secondary" id="settingsThemeToggle">Toggle Theme</button>
              </div>
            </div>
          </div>
        </div>
      </section>

    </div>
  </main>

</div>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // ======= THEME TOGGLE (persist di localStorage)
  const root = document.documentElement;
  const themeToggle = document.getElementById('themeToggle');
  const themeIcon = document.getElementById('themeIcon');
  const settingsThemeToggle = document.getElementById('settingsThemeToggle');
  function applyTheme(mode){
    document.documentElement.setAttribute('data-bs-theme', mode);
    localStorage.setItem('caffora_admin_theme', mode);
    themeIcon.className = mode === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
  }
  (function initTheme(){
    const saved = localStorage.getItem('caffora_admin_theme') || 'light';
    applyTheme(saved);
  })();
  themeToggle.addEventListener('click', ()=>{
    const now = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
    applyTheme(now);
  });
  settingsThemeToggle.addEventListener('click', ()=>{
    const now = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
    applyTheme(now);
  });

  // ======= NAV: side + tabs -> smooth scroll dan aktifkan link
  function setActiveByHash(hash){
    document.querySelectorAll('.nav-side a, .section-tabs .tab').forEach(a=>a.classList.remove('active'));
    if (!hash) hash = '#overview';
    const side = document.querySelector(.nav-side a[href="${hash}"]);
    const tab  = document.querySelector(.section-tabs a[href="${hash}"]);
    side?.classList.add('active'); tab?.classList.add('active');
  }
  document.querySelectorAll('.nav-side a, .section-tabs a').forEach(a=>{
    a.addEventListener('click', (e)=>{
      e.preventDefault();
      const id = a.getAttribute('href');
      document.querySelector(id)?.scrollIntoView({behavior:'smooth', block:'start'});
      history.replaceState(null, '', id);
      setActiveByHash(id);
    });
  });
  window.addEventListener('hashchange', ()=> setActiveByHash(location.hash));
  setActiveByHash(location.hash);

  // ======= API helper
  async function api(action, params={}){
    const url = new URL('../../backend/api/admin_api.php', location.origin);
    url.searchParams.set('action', action);
    Object.entries(params).forEach(([k,v])=> url.searchParams.set(k, v));
    const res = await fetch(url, { credentials: 'same-origin' });
    if (!res.ok) throw new Error('API error');
    return await res.json();
  }
  function rupiah(n){
    n = Number(n||0);
    return 'Rp ' + n.toLocaleString('id-ID', {maximumFractionDigits:0});
  }

  // ======= KPI + daftar awal
  async function loadOverview(){
    try {
      const data = await api('metrics');
      // KPI
      document.getElementById('kpiOrders').textContent    = data.ordersToday ?? 0;
      document.getElementById('kpiRevenue').textContent   = rupiah(data.revenueToday ?? 0);
      document.getElementById('kpiCustomers').textContent = data.customers ?? 0;
      document.getElementById('kpiMenu').textContent      = data.menuItems ?? 0;
      // Users card
      const u = data.usersByRole || {admin:0, karyawan:0, customer:0};
      document.getElementById('uAdmin').textContent = u.admin ?? 0;
      document.getElementById('uStaff').textContent = u.karyawan ?? 0;
      document.getElementById('uCust').textContent  = u.customer ?? 0;
      // Finance
      document.getElementById('finMonth').textContent = rupiah(data.finance?.monthRevenue ?? 0);
      document.getElementById('finAOV').textContent   = rupiah(data.finance?.avgOrderValue ?? 0);
      document.getElementById('finGrowth').textContent= (data.finance?.growthPct ?? 0) + '%';

      // Orders table
      renderOrders(data.latestOrders || []);
      // Catalog table
      renderCatalog(data.catalog || []);
      // Notif
      renderNotif(data.notifications || []);
      // To-do
      renderTodo(data.todo || []);
    } catch(e){
      console.error(e);
    }
  }

  function renderOrders(rows){
    const tbody = document.getElementById('ordersBody');
    if (!rows.length){
      tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Belum ada pesanan.</td></tr>';
      return;
    }
    tbody.innerHTML = rows.map(r=>{
      const st = (r.status||'pending').toLowerCase();
      const badge = st==='done'||st==='completed'||st==='selesai'
        ? '<span class="badge-soft">Selesai</span>' : '<span class="badge-soft">Diproses</span>';
      return `<tr>
        <td>${r.invoice_no || ('INV-' + r.id)}</td>
        <td>${r.created_at || ''}</td>
        <td>${r.customer_name || 'Guest'}</td>
        <td>${rupiah(r.total||0)}</td>
        <td>${badge}</td>
        <td><a href="#" class="btn btn-sm btn-outline-primary me-1">Detail</a>
            <a href="#" class="btn btn-sm btn-outline-success">Selesai</a></td>
      </tr>`;
    }).join('');
  }

  function renderCatalog(rows){
    const tbody = document.getElementById('catalogBody');
    if (!rows.length){
      tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Belum ada item menu.</td></tr>';
      return;
    }
    let i=1;
    tbody.innerHTML = rows.map(m=>`
      <tr>
        <td>${i++}</td>
        <td>${m.name||'-'}</td>
        <td>${m.category||'-'}</td>
        <td>${rupiah(m.price||0)}</td>
        <td>${m.stock_status||'Ready'}</td>
        <td>
          <a href="#" class="btn btn-sm btn-outline-primary me-1">Edit</a>
          <a href="#" class="btn btn-sm btn-outline-danger">Hapus</a>
        </td>
      </tr>
    `).join('');
  }

  function renderNotif(list){
    const menu = document.getElementById('notifMenu');
    const dot  = document.getElementById('notifDot');
    const empty = document.getElementById('notifEmpty');
    if (!list.length){
      empty.style.display = 'block';
      dot.style.display = 'none';
      return;
    }
    empty.style.display = 'none';
    dot.style.display = 'block';
    // hapus item lama selain header/divider
    menu.querySelectorAll('li.item').forEach(li=>li.remove());
    list.slice(0,6).forEach(n=>{
      const li = document.createElement('li');
      li.className = 'item';
      li.innerHTML = `<div class="px-3 py-2">
        <div class="fw-semibold">${n.title||'Notifikasi'}</div>
        <div class="small text-muted">${n.time||''}</div>
      </div>`;
      menu.appendChild(li);
    });
  }

  function renderTodo(list){
    const ul = document.getElementById('todoList');
    if (!list.length){
      ul.innerHTML = '<li class="list-group-item text-muted">Belum ada tugas.</li>';
      return;
    }
    ul.innerHTML = '';
    list.forEach(t=>{
      const li = document.createElement('li');
      li.className = 'list-group-item d-flex justify-content-between align-items-center';
      li.innerHTML = `<span>${t.text}</span>
        <div class="d-flex gap-2">
          <button class="btn btn-sm btn-outline-secondary">Selesai</button>
          <button class="btn btn-sm btn-outline-danger">Hapus</button>
        </div>`;
      ul.appendChild(li);
    });
  }

  // ======= Sales chart + filters
  let salesChart;
  function ensureChart(){
    const ctx = document.getElementById('salesChart').getContext('2d');
    if (salesChart) return salesChart;
    salesChart = new Chart(ctx, {
      type: 'line',
      data: { labels: [], datasets: [{
        label: 'Pendapatan',
        data: [],
        fill: true,
        tension: .35,
        borderWidth: 2
      }]},
      options: {
        responsive: true,
        plugins: { legend: { display: false }},
        scales: {
          x: { grid: { display: false }},
          y: { grid: { color: 'rgba(0,0,0,.06)' }, ticks: { callback:(v)=>'Rp '+Number(v).toLocaleString('id-ID') }}
        }
      }
    });
    return salesChart;
  }

  async function loadSales(range, from=null, to=null){
    // set active button
    document.querySelectorAll('.btn-pill[data-range]').forEach(b=>b.classList.remove('active'));
    const btn = document.querySelector(.btn-pill[data-range="${range}"]);
    btn?.classList.add('active');

    const params = { range };
    if (range==='custom' && from && to){ params.from = from; params.to = to; }

    const data = await api('sales', params);
    const ch = ensureChart();
    ch.data.labels = data.labels || [];
    ch.data.datasets[0].data = (data.values || []).map(Number);
    ch.update();
  }

  document.querySelectorAll('.btn-pill[data-range]').forEach(b=>{
    b.addEventListener('click', ()=> loadSales(b.dataset.range));
  });
  document.getElementById('btnCustom').addEventListener('click', ()=>{
    const f = document.getElementById('fromDate').value;
    const t = document.getElementById('toDate').value;
    if (!f || !t) return;
    loadSales('custom', f, t);
  });

  // ======= Init
  (async function init(){
    await loadOverview();
    await loadSales('month'); // default
  })();
</script>
</body>
</html>
<?php
// public/customer/index.php
declare(strict_types=1);

require_once __DIR__ . '/../../backend/auth_guard.php';
require_login(['customer']); // wajib login

$name   = $_SESSION['user_name']  ?? 'Customer';
$email  = $_SESSION['user_email'] ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);
$role   = $_SESSION['user_role']  ?? 'customer';
?>
<!DOCTYPE html>
<html lang="id">
  <head>
    <meta charset="utf-8" />
    <title>Caffora Caffe</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />

     <!-- Fonts -->
    <link
      href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600&display=swap"
      rel="stylesheet"
    />

    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>

    <!-- Global CSS -->
    <link href="../assets/css/style.css" rel="stylesheet" />

    <style>
      :root {
        --gold: #ffd54f;
        --gold-200: #ffe883;
        --gold-soft: #f6d472; /* dipakai buat focus line */
        --tan: #daa85c;
        --brown: #4b3f36;
        --footer: #876f45;
        --bg-soft: #fffdf8;
      }

      html, body {
        background: var(--bg-soft);
        font-family: "Poppins", system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
        overflow-x: hidden;
      }

      /* ================= NAVBAR ================= */
      .navbar { background: #fff !important; }
      .navbar .container { align-items: center !important; }
      .navbar .nav-link {
        color: var(--brown); font-weight: 500; opacity: .9; transition: opacity .15s, color .15s;
      }
      .navbar .nav-link:hover { opacity: 1; color: var(--tan); }
      .navbar .nav-link.active { color: var(--brown); font-weight: 700; opacity: 1; }

      .right-actions { display:flex; align-items:center !important; justify-content:flex-end !important; gap:12px; }
      .icon-btn { background:transparent; border:0; padding:0; margin:0; line-height:1; color:var(--brown); display:flex; align-items:center; justify-content:center; }
      .icon-btn:hover,.icon-btn:focus,.icon-btn:active { transform:none !important; transition:none !important; box-shadow:none !important; outline:none !important; }

      #cartBtn { border:none !important; background:transparent !important; border-radius:0 !important; padding:0 !important; text-decoration:none !important; color:var(--brown) !important; box-shadow:none !important; outline:none !important; }
      #cartBtn,#cartBtn:hover,#cartBtn:focus,#cartBtn:active{
        position:relative !important; top:0 !important; transform:none !important; transition:none !important; box-shadow:none !important; outline:none !important; color:var(--brown) !important; background:transparent !important; border:0 !important;
      }
      #cartBtn .iconify,#cartBtn .iconify:hover,#cartBtn .iconify:focus,#cartBtn .iconify:active{ transform:none !important; transition:none !important; color:var(--brown) !important; }

      /* notif dot */
      #btnBell{ position:relative; }
      #badgeNotif.notif-dot{
        position:absolute; top:-2px; right:-3px; width:8px; height:8px; background:#4b3f36; border-radius:50%; display:inline-block; box-shadow:0 0 0 1.5px #fff;
      }
      #badgeNotif.d-none{ display:none !important; }

      @media (max-width: 600px) {
        #badgeNotif.notif-dot { width:10px; height:10px; top:-3px; right:-5px; }
      }

      /* dropdown profil */
      .dropdown-menu{
        border-radius:12px; border-color:#eee; padding:6px 0; font-family:"Poppins",system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif; background:#fffdf8;
      }
      .dropdown-menu .dropdown-item{
        font-size:1.05rem; font-weight:600; color:var(--brown); padding:.6rem .9rem; display:flex; align-items:center; gap:.5rem; transition:none !important; background:#fffdf8 !important;
      }
      .dropdown-menu .dropdown-item:hover,.dropdown-menu .dropdown-item:focus{
        background:rgba(255,213,79,.16) !important; color:var(--brown) !important; outline:none !important; box-shadow:none !important;
      }
      .dropdown-menu .dropdown-item:active,.dropdown-menu .dropdown-item:focus-visible{
        background:rgba(255,213,79,.16) !important; color:var(--brown) !important; outline:none !important; box-shadow:none !important;
      }

      .btn-brand{
        background:var(--gold); color:var(--brown); border:0; border-radius:9999px; font-weight:600; padding:8px 14px; font-size:.9rem; line-height:1.2; text-decoration:none !important;
      }
      .btn-brand:hover,.btn-brand:focus{ background:var(--gold-200); color:var(--brown); box-shadow:0 0 0 .16rem rgba(255,213,79,.35); }

      /* ===== OFFCANVAS (mobile) ===== */
      .custom-offcanvas{ width:72%; max-width:300px; border-left:1px solid rgba(0,0,0,.06); }
      @media (max-width:360px){ .custom-offcanvas{ width:70%; max-width:280px; } }
      .offcanvas-user{ color:var(--brown) !important; text-decoration:none !important; display:inline-block; margin:2px 0 12px; font-weight:600; }
      .offcanvas-user .small{ font-weight:400; opacity:.8; }
      #mobileNav .nav-link{
        color:var(--brown) !important; font-weight:600; opacity:.9; padding:.6rem 0; display:flex; align-items:center; gap:.5rem; transition: color .25s ease, font-weight .25s ease;
      }
      #mobileNav .nav-link:hover{ color:var(--tan) !important; opacity:1 !important; }
      #mobileNav .nav-link.active{ color:var(--brown) !important; font-weight:700 !important; opacity:1 !important; }

      @media (max-width: 991.98px){
        .navbar .icon-btn.d-lg-none{ display:flex; align-items:center; justify-content:center; padding:0 !important; margin:0 !important; line-height:1; position:relative; top:1px; }
        .navbar .icon-btn.d-lg-none i{ font-size:30px; color:var(--brown) !important; transition:none !important; }
        .icon-btn,.icon-btn i.bi-list{ transition:none !important; transform:none !important; color:var(--brown) !important; }
        .icon-btn:hover,.icon-btn:active,.icon-btn:focus,.icon-btn:hover i.bi-list,.icon-btn:active i.bi-list,.icon-btn:focus i.bi-list{
          transform:none !important; color:var(--brown) !important; opacity:1 !important; box-shadow:none !important; outline:none !important;
        }
      }

      /* ================= HERO ================= */
      .hero .container{ padding:56px 0; }
      .hero h1{ font-family:"Playfair Display",serif; font-weight:700; }
      section.hero{
        background-image:url("/public/assets/img/hero.jpg");
        background-size:cover; background-position:center; background-repeat:no-repeat;
      }
      @media (max-width:600px){
        section.hero{
          position:relative; display:flex; align-items:center; justify-content:center; text-align:center; aspect-ratio:16/9; overflow:hidden;
        }
        section.hero::before{
          content:""; position:absolute; inset:0; background-image:url("/public/assets/img/hero.jpg"); background-size:cover; background-position:center; background-repeat:no-repeat; z-index:-1;
        }
        section.hero .container{ position:relative; z-index:2; padding:0 1rem; }
        section.hero h1{ font-size:1.3rem; line-height:1.2; margin-bottom:.5rem; }
        section.hero p{ font-size:.9rem; line-height:1.4; margin:0; }
      }

      /* ================= MENU / KATALOG ================= */
      #menuSection { background: transparent; }

      .search-box { position:relative; width:100%; }
      .search-input{
        height:46px; border-radius:9999px; padding-left:16px; padding-right:44px; border:1px solid #e5e7eb; outline:none !important; background:#fff; transition:border-color .1s ease;
      }
      .search-input:focus,.search-input:focus-visible{
        outline:none !important; border-color:var(--gold-soft) !important; box-shadow:none !important; background:#fff;
      }
      .btn-search{
        position:absolute; right:6px; top:50%; transform:translateY(-50%); height:34px; width:34px; display:flex; align-items:center; justify-content:center; border:0; border-radius:50%; background:transparent; color:var(--brown);
      }

      .btn-filter{
        background:var(--gold); color:var(--brown); border:0; border-radius:12px; font-weight:500; height:46px; width:46px; display:inline-flex; align-items:center; justify-content:center; transition:none;
      }

      #filterMenu.dropdown-menu{
        border-radius:5px; border:1px solid rgba(0,0,0,.06); background:#fffef8; min-width:220px; max-width:320px; padding:6px 0; font-family:"Poppins",system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif; box-shadow:0 12px 24px rgba(0,0,0,.08); transform-origin:top right; transition:opacity .15s ease, transform .15s ease;
      }
      #filterMenu .dropdown-item{ font-size:.9rem; font-weight:600; color:var(--brown); padding:9px 14px; }
      #filterMenu .dropdown-item:hover,#filterMenu .dropdown-item:focus{ background:rgba(255,213,79,.25); color:var(--brown); }
      .dropdown.ms-2{ overflow:visible !important; }

      @media (max-width:600px){
        .btn-filter{ width:38px !important; height:38px !important; border-radius:10px !important; font-size:.95rem !important; padding:0 !important; box-shadow:0 2px 6px rgba(0,0,0,.08); }
        .btn-filter .iconify,.btn-filter i{ width:22px !important; height:22px !important; }
        .search-input{ height:40px !important; font-size:.9rem !important; padding:8px 38px 8px 14px !important; border-radius:9999px !important; }
        #filterMenu .dropdown-item{ padding:8px 14px !important; font-size:.85rem !important; white-space:nowrap !important; }
        #filterMenu.dropdown-menu{ max-height:none !important; overflow-y:visible !important; }
      }

      .card-menu,.menu-card,.product-card,.card.item,.card.product{
        width:100% !important; border-radius:18px !important; padding:12px !important; background:#fff; position:relative; overflow:hidden; box-shadow:0 8px 16px rgba(0,0,0,.06);
      }
      .card-menu img,.menu-card img,.product-card img,.card.item img,.card.product img{
        display:block; width:90% !important; height:auto !important; margin:0 auto 8px !important; object-fit:contain !important;
      }
      .card-menu .menu-name,.menu-card .menu-name,.product-card .menu-name,.card.item .menu-name,.card.product .menu-name,
      .card-menu h5,.menu-card h5,.product-card h5,.card.item h5,.card.product h5{
        font-size:.92rem !important; line-height:1.25 !important; font-weight:600; margin:2px 0 0 !important; color:var(--brown);
      }
      .card-menu .btn-add,.menu-card .btn-add,.product-card .btn-add,.card.item .btn-add,.card.product .btn-add,
      .card-menu .add-btn,.menu-card .add-btn,.product-card .add-btn,.card.item .add-btn,.card.product .add-btn,
      .card-menu .btn-add-to-cart,.menu-card .btn-add-to-cart,.product-card .btn-add-to-cart,.card.item .btn-add-to-cart,.card.product .btn-add-to-cart{
        position:absolute !important; right:10px !important; bottom:10px !important; width:32px !important; height:32px !important; line-height:32px !important; border-radius:50% !important; border:none !important; background:var(--gold) !important; color:var(--brown) !important; font-weight:600; font-size:1rem !important; display:inline-flex !important; align-items:center; justify-content:center; box-shadow:0 4px 8px rgba(0,0,0,.08); cursor:pointer;
      }
      @media (max-width:600px){
        .card-menu,.menu-card,.product-card,.card.item,.card.product{ box-shadow:0 4px 10px rgba(0,0,0,.06); }
        .card-menu .btn-add,.menu-card .btn-add,.product-card .btn-add,.card.item .btn-add,.card.product .btn-add{
          width:25px !important; height:25px !important; line-height:25px !important; font-size:1rem !important;
        }
      }

      /* ================= ABOUT ================= */
      #about { background: transparent; }
      #about .about-title{
        font-family:"Playfair Display",serif; font-weight:700; font-size:clamp(26px,3vw,36px); line-height:1.25; color:#111; margin-bottom:10px;
      }
      #about .about-desc{ color:#666; font-size:clamp(14px,1.5vw,16px); margin-bottom:16px; max-width:54ch; }
      .btn-view{
        background-color:var(--gold); color:var(--brown) !important; border:0; border-radius:12px; font-family:Arial,sans-serif; font-weight:600; font-size:13.3px; line-height:1.2; padding:10px 14px; display:inline-flex; align-items:center; justify-content:center; gap:8px; text-decoration:none !important; box-shadow:none; cursor:pointer; white-space:nowrap;
      }
      .about-img-wrap{ text-align:center; }
      .about-img{ width:70%; max-width:70%; border-radius:24px; object-fit:cover; box-shadow:0 10px 22px rgba(0,0,0,.08); }
      @media (max-width:992px){ .about-img{ max-width:75%; } }
      @media (max-width:576px){
        .hero .container{ padding:40px 0; }
        .about-img{ max-width:60%; border-radius:20px; }
        #about .about-title{ font-size:24px; }
      }
      @media (max-width:576px){
        #about{ padding-top:1.25rem !important; padding-bottom:0 !important; background:transparent; position:relative; z-index:1; margin-bottom:0 !important; }
        #about .container{ padding-left:0 !important; padding-right:0 !important; }
        #about .row{ --bs-gutter-x:0; --bs-gutter-y:0; }
        #about .about-img{ width:100% !important; max-width:100% !important; height:auto; display:block; border-radius:0 !important; box-shadow:none !important; margin-top:30px; margin-bottom:0 !important; }
        #about .about-title{ margin:0 1rem .5rem; }
        #about .about-desc{ margin:0 1rem .75rem; }
        #about .btn-view{ margin:0 1rem 1rem; }
      }

      /* ================= FOOTER ================= */
      footer{
        background:var(--footer); color:var(--gold-200); padding-top:36px; padding-bottom:20px; position:relative; z-index:2;
      }
      footer h5,footer h6{ color:var(--gold-200); font-weight:700; }
      footer a{ color:var(--gold-200); text-decoration:none; }
      footer a:hover,footer a:focus{ color:var(--gold); text-decoration:underline; }
      footer .footer-icons a{ color:var(--gold-200); font-size:20px; margin-right:12px; }
      footer .border-secondary-subtle{ border-color:rgba(255,255,255,.18) !important; }
      @media (max-width:600px){
        footer{ width:100vw; margin-left:calc(-1 * (100vw - 100%) / 2); margin-top:-30px; padding:28px 18px 20px; }
        footer .container{ padding:0 !important; }
        footer .row.g-4{ --bs-gutter-y:.75rem; --bs-gutter-x:0; }
        footer .footer-icons a{ font-size:1.1rem; margin-right:10px; }
      }

      .toast-mini{
        position:fixed !important; left:50% !important; bottom:16px !important; transform:translateX(-50%) !important; width:380px !important; max-width:calc(100vw - 32px) !important; z-index:1080 !important; border-radius:12px !important;
      }
      @media (max-width:600px){
        .toast-mini{ bottom:1rem !important; left:50% !important; right:auto !important; transform:translateX(-50%) !important; width:calc(100vw - 24px) !important; max-width:calc(100vw - 24px) !important; border-radius:14px !important; }
        .toast-mini .toast-body{ font-size:.8rem !important; }
      }

      .dropdown-menu .dropdown-item,
      .dropdown-menu .dropdown-item:hover,
      .dropdown-menu .dropdown-item:focus,
      .dropdown-menu .dropdown-item:active{
        background:transparent !important; box-shadow:none !important; outline:none !important;
      }
    </style>

    <!-- Inject identitas user untuk app.js (HARUS sebelum app.js) -->
    <script>
      window.__CAFFORA_USER__ = {
        id: <?= $userId ?>,
        name: <?= json_encode($name, JSON_UNESCAPED_UNICODE) ?>,
        email: <?= json_encode($email, JSON_UNESCAPED_UNICODE) ?>,
        role: <?= json_encode($role, JSON_UNESCAPED_UNICODE) ?>
      };
    </script>
  </head>

  <body>
    <!-- ================= NAVBAR ================= -->
    <nav class="navbar sticky-top shadow-sm">
      <div class="container d-flex align-items-center justify-content-between">
        <!-- Brand -->
        <a class="navbar-brand d-flex align-items-center gap-2" href="#hero">
          <i class="bi bi-cup-hot-fill text-warning"></i>
          <span class="brand-brown fw-bold" style="color: var(--brown)">Caffora</span>
        </a>

        <!-- Nav links (desktop) -->
        <ul class="navbar-nav d-none d-lg-flex flex-row gap-3" id="mainNav">
          <li class="nav-item"><a class="nav-link" href="#hero">Home</a></li>
          <li class="nav-item"><a class="nav-link" href="#menuSection">Product</a></li>
          <li class="nav-item"><a class="nav-link" href="#about">Tentang Kami</a></li>
        </ul>

        <!-- Right icons -->
        <div class="right-actions d-flex align-items-center gap-3">
          <!-- Cart -->
          <a
            class="icon-btn position-relative me-lg-1"
            id="cartBtn"
            aria-label="Buka keranjang"
            href="/public/customer/cart.php">
            <span class="iconify" data-icon="mdi:cart-outline" data-width="24" data-height="24"></span>
            <span class="badge-cart badge rounded-pill bg-danger" id="cartBadge" style="display:none">0</span>
          </a>

          <!-- Notifikasi -->
          <div class="dropdown">
            <a id="btnBell" class="icon-btn position-relative text-decoration-none" href="/public/customer/notifications.php" aria-label="Notifikasi">
              <span class="iconify" data-icon="mdi:bell-outline" data-width="24" data-height="24"></span>
              <span id="badgeNotif" class="notif-dot d-none"></span>
            </a>
          </div>

          <!-- Profil (desktop) -->
          <div class="dropdown d-none d-lg-block">
            <button class="icon-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Akun">
              <span class="iconify" data-icon="mdi:account-circle-outline" data-width="28" data-height="28"></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow" style="min-width: 260px">
              <li class="px-3 py-2">
                <div class="small text-muted">Masuk sebagai</div>
                <div class="fw-semibold"><?= htmlspecialchars($name) ?></div>
                <div class="small text-muted text-truncate" style="max-width:220px"><?= htmlspecialchars($email) ?></div>
              </li>
              <li><hr class="dropdown-divider" /></li>
              <li>
  <a class="dropdown-item" href="profile.php">
    <i class="bi bi-gear"></i>
    Pengaturan
  </a>
</li>

<li>
  <a class="dropdown-item" href="faq.php">
    <i class="bi bi-question-circle"></i>
    FAQ
  </a>
</li>

<li>
  <a class="dropdown-item" href="history.php">
    <i class="bi bi-receipt"></i>
    Riwayat Pesanan
  </a>
</li>
              <li><hr class="dropdown-divider" /></li>
              <!-- FIX LOGOUT (desktop): arahkan via JS -->
              <li><a class="dropdown-item text-danger" href="../login.html" data-logout><span class="iconify" data-icon="mdi:logout"></span>Log out</a></li>
            </ul>
          </div>

          <!-- Hamburger (mobile) -->
          <button class="icon-btn d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileNav" aria-controls="mobileNav" aria-label="Buka menu">
            <i class="bi bi-list" style="font-size: 30px"></i>
          </button>
        </div>
      </div>
    </nav>

    <!-- ================= OFFCANVAS (mobile) ================= -->
    <div class="offcanvas offcanvas-end custom-offcanvas" tabindex="-1" id="mobileNav" aria-labelledby="mobileNavLabel">
      <div class="offcanvas-header border-bottom d-flex justify-content-between align-items-center">
        <h5 class="offcanvas-title fw-semibold m-0" id="mobileNavLabel"><i class="bi bi-list" style="font-size: 2rem"></i></h5>
      </div>
      <div class="offcanvas-body d-flex flex-column gap-2">
        <div class="offcanvas-user">
          <div class="small">Masuk sebagai</div>
          <div class="fw-semibold"><?= htmlspecialchars($name) ?></div>
          <div class="small text-truncate" style="max-width: 240px"><?= htmlspecialchars($email) ?></div>
        </div>

        <ul class="nav flex-column mt-1" id="mobileNavList">
          <li class="nav-item">
  <a class="nav-link" href="profile.php">
    <i class="bi bi-gear"></i>
    Pengaturan
  </a>
</li>

<li class="nav-item">
  <a class="nav-link" href="faq.php">
    <i class="bi bi-question-circle"></i>
    FAQ
  </a>
</li>

<li class="nav-item">
  <a class="nav-link" href="history.php">
    <i class="bi bi-receipt"></i>
    Riwayat pesanan
  </a>
</li>

          <li><hr /></li>
          <!-- FIX LOGOUT (mobile): arahkan via JS -->
          <li class="nav-item">
            <a class="nav-link text-danger" href="../login.html" data-logout>
              <span class="iconify" data-icon="mdi:logout" data-width="22" data-height="22"></span>
              Log out
            </a>
          </li>
        </ul>
      </div>
    </div>

    <!-- ================= HERO ================= -->
    <section class="hero" id="hero">
      <div class="container">
        <h1>Selamat Datang di Caffora</h1>
        <p>Ngopi santai, kerja produktif, dan dessert favorit—semua dalam satu ruang.</p>
      </div>
    </section>

    <!-- ================= MENU / KATALOG ================= -->
    <main id="menuSection" class="container py-5">
      <h2 class="mb-3 text-center" style="color: var(--brown); font-family:'Playfair Display',serif; font-weight:700;">Menu Kami</h2>
      <p class="text-center text-muted mb-4">Cari menu favoritmu lalu tambahkan ke keranjang.</p>

      <!-- Search + Filter -->
      <div class="row gy-3 gx-2 mb-3 justify-content-center">
        <div class="col-12 col-md-7 d-flex align-items-center">
          <div class="search-box flex-grow-1">
            <input id="q" class="form-control search-input" placeholder="Search..." />
            <button id="btnCari" class="btn-search" aria-label="Cari"><i class="bi bi-search"></i></button>
          </div>

          <div class="dropdown ms-2">
            <button class="btn-filter" id="filterBtn" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-label="Filter">
              <span class="iconify" data-icon="mdi:filter-variant" data-width="22" data-height="22"></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="filterBtn" id="filterMenu">
              <li><a class="dropdown-item" href="#" data-filter="cheap">Termurah - Termahal</a></li>
              <li><a class="dropdown-item" href="#" data-filter="expensive">Termahal - Termurah</a></li>
              <li><a class="dropdown-item" href="#" data-filter="pastry">Pastry</a></li>
              <li><a class="dropdown-item" href="#" data-filter="drink">Drink</a></li>
              <li><a class="dropdown-item" href="#" data-filter="food">Food</a></li>
              <li><a class="dropdown-item" href="#" data-filter="all">Tampilkan Semua</a></li>
            </ul>
          </div>
        </div>
      </div>

      <div id="list" class="row g-4"></div>
    </main>

    <!-- ================= ABOUT ================= -->
    <section id="about" class="py-5">
      <div class="container">
        <div class="row g-4 align-items-center">
          <div class="col-lg-6">
            <h2 class="about-title">Caffora tempat Nyaman<br />untuk Setiap Momen</h2>
            <p class="about-desc">
              Nikmati pengalaman ngopi di tempat yang tenang dan nyaman. Ruang yang
              minimalis dengan sentuhan hangat ini siap menemani kamu untuk bekerja,
              bersantai, atau sekadar melepas penat.
            </p>
            <a href="#menuSection" class="btn-view">View more</a>
          </div>
          <div class="col-lg-6 about-img-wrap">
            <img src="../assets/img/cafforaf.jpg" alt="Ruang Caffora yang nyaman" class="about-img"/>
          </div>
        </div>
      </div>
    </section>

    <!-- ================= FOOTER ================= -->
    <footer>
      <div class="container">
        <div class="row g-4 align-items-start">
          <div class="col-md-4 mb-4 mb-md-0">
            <h5>Caffora</h5>
            <p>Coffee <br />Dessert <br />Space</p>
            <div class="footer-icons">
              <a href="#"><i class="bi bi-instagram"></i></a>
              <a href="https://twitter.com/cafforacafe" aria-label="Twitter">
                <i class="bi bi-twitter-x"></i>
               </a>
              <a href="#"><i class="bi bi-whatsapp"></i></a>
              <a href="#"><i class="bi bi-tiktok"></i></a>
            </div>
          </div>
          <div class="col-md-4 mb-4 mb-md-0">
            <h6>Menu</h6>
            <ul class="list-unstyled m-0">
              <li><a href="#menuSection">Semua Menu</a></li>
              <li><a href="#about">Tentang Kami</a></li>
            </ul>
          </div>
          <div class="col-md-4">
            <h6>Kontak</h6>
            <p><a href="https://maps.app.goo.gl/Fznq9KLusdNXH2RE7">Jl. D.I. Panjaitan No. 128</a></p>
            <p><a href="https://wa.me/6287823023379">0878-2302-3379</a></p>
            <p><a href="mailto:Helpcaffora@gmail.com">Helpcaffora@gmail.com</a></p>
          </div>
        </div>

        <hr class="border-secondary-subtle my-4" />

        <div class="d-flex flex-wrap justify-content-between align-items-center small">
          <div>© 2025 Caffora — All rights reserved</div>
          <div class="d-flex gap-3">
            <a href="#">Terms</a>
            <a href="#">Privacy</a>
            <a href="#">Policy</a>
          </div>
        </div>
      </div>
    </footer>

    <!-- ================= TOAST LOGIN ================= -->
    <div class="toast align-items-center text-bg-dark border-0 toast-mini" role="alert" aria-live="assertive" aria-atomic="true" id="loginToast">
      <div class="d-flex">
        <div class="toast-body">Silakan login terlebih dahulu untuk menambahkan ke keranjang.</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
    </div>

    <!-- ================= TOAST BERHASIL ADD TO CART ================= -->
    <div class="toast align-items-center text-bg-success border-0 toast-mini" role="alert" aria-live="assertive" aria-atomic="true" id="cartToast" style="display:none">
      <div class="d-flex">
        <div class="toast-body">Menu berhasil ditambahkan ke keranjang.</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
    </div>

    <!-- JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.iconify.design/3/3.1.1/iconify.min.js"></script>
    <script src="../assets/js/app.js?v=1013" defer></script>

    <!-- NAVBAR ACTIVE -->
    <script>
      (function () {
        const nav = document.getElementById("mainNav");
        if (nav) {
          nav.querySelectorAll(".nav-link").forEach((a) => {
            a.addEventListener("click", function () {
              nav.querySelectorAll(".nav-link").forEach((x) => x.classList.remove("active"));
              this.classList.add("active");
            });
          });
        }

        const mobileLinks = document.querySelectorAll("#mobileNav .nav-link");
        mobileLinks.forEach((link) => {
          link.addEventListener("click", function () {
            mobileLinks.forEach((l) => l.classList.remove("active"));
            this.classList.add("active");
          });
        });
      })();
    </script>

    <!-- BADGE KERANJANG (sinkron per-akun dari app.js) -->
    <script>
      document.addEventListener("DOMContentLoaded", function () {
        if (typeof window.updateCartBadge === "function") {
          window.updateCartBadge();
        }
      });
    </script>

    <!-- PATCH IMAGE CART -->
    <script>
      (function () {
        var PROJECT_BASE = "/caffora-app1";
        var PUBLIC_BASE = PROJECT_BASE + "/public";
        var UPLOADS_DIR = "/uploads/menu/";
        var KEY = "caffora_cart";

        function toAbs(url) {
          if (!url) return "";
          if (/^https?:\/\//i.test(url)) return url;
          if (url.indexOf(PUBLIC_BASE) === 0) return url;
          if (url.indexOf("/uploads/") === 0) return PUBLIC_BASE + url;
          if (url.indexOf("uploads/") === 0) return PUBLIC_BASE + "/" + url;
          if (url[0] !== "/") return PUBLIC_BASE + UPLOADS_DIR + url;
          return PUBLIC_BASE + url;
        }

        function readCart() {
          try { return JSON.parse(localStorage.getItem(KEY) || "[]"); }
          catch (e) { return []; }
        }
        function writeCart(c) { localStorage.setItem(KEY, JSON.stringify(c)); }

        function patchItemImage(id, candidate) {
          var cart = readCart();
          for (var i = 0; i < cart.length; i++) {
            if (String(cart[i].id) === String(id)) {
              var cur = cart[i].image_url || cart[i].image || "";
              if (!cur || /placeholder/i.test(cur)) {
                var src = candidate || cur;
                if (!src) { break; }
                cart[i].image_url = toAbs(src.replace(/\\/g, "/"));
                writeCart(cart);
              }
              break;
            }
          }
        }

        var SELECTORS = [".btn-add","[data-add-to-cart]","[data-action='add']","button.add-to-cart"];
        document.addEventListener("click", function (ev) {
          var btn = null;
          for (var i = 0; i < SELECTORS.length; i++) {
            var t = ev.target.closest(SELECTORS[i]);
            if (t) { btn = t; break; }
          }
          if (!btn) return;

          var id = btn.getAttribute("data-id") || btn.dataset.id || "";
          var imgCandidate = btn.getAttribute("data-img") || btn.dataset.img || "";

          if (!imgCandidate) {
            var card = btn.closest(".card-menu, .card, .product, .item");
            var imgEl = card ? card.querySelector("img") : null;
            if (imgEl) imgCandidate = imgEl.getAttribute("src") || "";
          }

          setTimeout(function () { patchItemImage(id, imgCandidate); }, 60);
        }, true);
      })();
    </script>

    <!-- OFFCANVAS -->
    <script>
      document.addEventListener("DOMContentLoaded", function () {
        const offcanvasEl = document.getElementById("mobileNav");
        const bsOffcanvas = new bootstrap.Offcanvas(offcanvasEl);
        const toggleBtnInside = offcanvasEl.querySelector(".offcanvas-header .bi-list");
        if (toggleBtnInside) {
          toggleBtnInside.style.cursor = "pointer";
          toggleBtnInside.addEventListener("click", () => bsOffcanvas.hide());
        }
      });
    </script>

    <!-- Notif badge -->
    <script>
      (function () {
        const badge = document.getElementById("badgeNotif");
        if (!badge) return;

        async function refreshNotifBadge() {
          try {
            const res = await fetch("/backend/api/notifications.php?action=unread_count", {
              credentials: "same-origin",
              cache: "no-store",
            });
            const js = await res.json();
            if (js && js.ok) {
              if (js.count && js.count > 0) {
                badge.classList.remove("d-none");
              } else {
                badge.classList.add("d-none");
              }
            }
          } catch (err) {}
        }

        refreshNotifBadge();
        setInterval(refreshNotifBadge, 15000);
      })();
    </script>

    <!-- Trigger show all -->
    <script>
      (function () {
        document.addEventListener("click", function (ev) {
          const a = ev.target.closest('#filterMenu [data-filter="all"]');
          if (!a) return;
          ev.preventDefault();
          if (typeof window.cafforaShowAll === "function") {
            window.cafforaShowAll();
          } else {
            window.addEventListener("DOMContentLoaded", function () {
              if (typeof window.cafforaShowAll === "function") window.cafforaShowAll();
            });
          }
        }, true);

        document.querySelectorAll('a[href="#menuSection"]').forEach(function (link) {
          link.addEventListener("click", function () {
            if (typeof window.cafforaShowAll === "function") {
              setTimeout(window.cafforaShowAll, 50);
            }
          });
        });
      })();
    </script>

    <!-- OPTIONAL: contoh panggil toast cart dari JS -->
    <script>
      window.showCartToast = function () {
        const el = document.getElementById("cartToast");
        if (!el) return;
        el.style.display = "block";
        const t = new bootstrap.Toast(el, { delay: 2500 });
        t.show();
      };
    </script>
    
    <!-- KILLER PATCH: blokir toast "Silakan login" lama (tanpa ubah desain lain) -->
<script>
(function(){
  // 1) Cegah event Bootstrap Toast untuk #loginToast (fase capture biar menang)
  document.addEventListener('show.bs.toast', function(ev){
    try{
      if (ev && ev.target && ev.target.id === 'loginToast') {
        ev.stopImmediatePropagation();
        ev.stopPropagation();
        if (typeof ev.preventDefault === 'function') ev.preventDefault();
      }
    }catch(e){}
  }, true);

  // 2) Patch method show() bawaan Bootstrap khusus elemen #loginToast
  if (window.bootstrap && window.bootstrap.Toast && window.bootstrap.Toast.prototype) {
    const _show = window.bootstrap.Toast.prototype.show;
    window.bootstrap.Toast.prototype.show = function(){
      try{
        const el = this && this._element ? this._element : null;
        if (el && el.id === 'loginToast') {
          // blokir total
          return;
        }
      }catch(e){}
      return _show.apply(this, arguments);
    };
  }

  // 3) Sembunyikan / lepas elemen loginToast bila ada (opsional, aman)
  const lt = document.getElementById('loginToast');
  if (lt) {
    lt.style.setProperty('display','none','important');
    // Kalau mau benar-benar aman dari script lama:
    // lt.parentNode && lt.parentNode.removeChild(lt);
  }

  // 4) Netralisir fallback global yang kadang dipakai script lama
  // (kalau ada fungsi global yang memaksa login)
  const neutral = function(){ return true; };
  ['requireLogin','needLogin','mustLogin','ensureLogin'].forEach(fn=>{
    if (typeof window[fn] === 'function') {
      try { window[fn] = neutral; } catch(e){}
    }
  });
})();
</script>


    <!-- SAFE LOGOUT REDIRECT (FIX 404) -->
    <script>
      (function(){
        // Deteksi base proyek secara otomatis: .../public/...
        const PUBLIC_SPLIT = "/public/";
        const idx = location.pathname.indexOf(PUBLIC_SPLIT);
        const PROJECT_BASE = idx > -1 ? location.pathname.slice(0, idx) : "";
        const LOGIN_URL  = PROJECT_BASE + "/public/login.html";
        const LOGOUT_URL = PROJECT_BASE + "/backend/logout.php";

        document.querySelectorAll('[data-logout]').forEach(a => {
          // Pastikan fallback href menuju login bila JS mati
          a.setAttribute('href', LOGIN_URL);

          a.addEventListener('click', function(e){
            e.preventDefault();
            // Panggil backend untuk hapus sesi, lalu apapun hasilnya redirect ke login
            fetch(LOGOUT_URL, { credentials: "same-origin", cache: "no-store" })
              .finally(() => { window.location.replace(LOGIN_URL); });
          });
        });
      })();
    </script>
  </body>
</html>

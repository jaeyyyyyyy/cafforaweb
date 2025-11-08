<?php 
// public/customer/faq.php
declare(strict_types=1);
require_once __DIR__ . '/../../backend/auth_guard.php';
require_login(['customer']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <title>FAQ — Caffora</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- Fonts & Framework -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />

  <style>
    :root{
      --gold:#ffd54f;
      --ink:#222;
      --brown:#4b3f36;
      --bg:#fafbfc;
      --line:#eceff3;
    }
    *{
      font-family:"Poppins",system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif !important;
      box-sizing:border-box;
    }
    html,body{
      background:var(--bg);
      color:var(--ink);
      margin:0;
    }

    /* ===== TOPBAR (seragam) ===== */
    .topbar{
      position:sticky;
      top:0;
      z-index:20;
      background:#fff;
      border-bottom:1px solid rgba(0,0,0,.04);
    }
    .topbar .inner{
      max-width:1200px;
      margin:0 auto;
      padding:12px 16px;
      min-height:52px;
      display:flex;
      align-items:center;
      gap:10px;
    }
     .back-link {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: transparent;
  border: 0;
  color: var(--brown);
  font-weight: 600;
  font-size: 16px;          /* ← ukuran font teks */
  line-height: 1.3;
  cursor: pointer;
  outline: none;
}

.back-link i {
  font-size: 18px !important; /* ← ukuran ikon 18x18 */
  width: 18px;
  height: 18px;
  line-height: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
    /* ===== Layout ===== */
    .wrap{
      max-width:1200px;
      margin:10px auto 32px;
      padding:0 16px;
    }

    /* ===== FAQ ===== */
    .faq-list{
      background:#fff;
      border-radius:0px;
      overflow:hidden;
      box-shadow:0 1px 3px rgba(0,0,0,.02);
    }
    .faq-item{ border-bottom:1px solid var(--line); }
    .faq-item:last-child{ border-bottom:0; }
    .faq-q{
      width:100%;
      text-align:left;
      background:transparent;
      border:0;
      padding:12px 10px;
      display:flex;
      align-items:center;
      justify-content:space-between;
      font-weight:500;
      font-size:.95rem;
      color:var(--ink);
      transition:background .15s ease;
    }
    .faq-q:hover{ background:#fffbea; }
    .faq-q[aria-expanded="true"] .chev{ transform:rotate(180deg); }
    .faq-a{
      padding:0 10px 12px;
      color:var(--ink);
      font-size:.9rem;
      line-height:1.56;
    }

    /* ===== Kontak ===== */
    .contact{
      margin-top:14px;
      background:#fff;
      border-radius:14px;
      padding:4px 10px;
      box-shadow:0 1px 3px rgba(0,0,0,.02);
    }
    .c-row{
      display:grid;
      grid-template-columns:24px 1fr 16px;
      align-items:center;
      gap:10px;
      padding:10px 2px;
      text-decoration:none;
      color:var(--ink);
      font-weight:500;
      font-size:.9rem;
      border:none; /* hilangkan line antar baris */
    }
    .c-row .bi{ font-size:1.05rem; color:var(--brown); }
    .c-row .chev{ color:#a3a3a3; }
    .c-row.static{ grid-template-columns:24px 1fr; }

    @media (max-width:576px){
      .topbar .inner,
      .wrap{
        max-width:100%;
        padding:12px 14px;
      }
      .wrap{ margin:8px auto 28px; }
      .faq-q{ font-size:.88rem; }
      .faq-a{ font-size:.86rem; }
      .c-row{ font-size:.88rem; padding:8px 0; }
    }
  </style>
</head>
<body>

  <!-- Header -->
  <div class="topbar">
    <div class="inner">
      <button class="back-link" onclick="history.back()" aria-label="Kembali">
        <i class="bi bi-arrow-left"></i>
        <span>Kembali</span>
      </button>
    </div>
  </div>

  <main class="wrap">

    <!-- FAQ -->
    <div class="faq-list" id="faq">
      <div class="faq-item">
        <button class="faq-q" data-bs-toggle="collapse" data-bs-target="#a1" aria-expanded="false" aria-controls="a1">
          <span>Bagaimana cara melakukan pemesanan?</span>
          <i class="bi bi-chevron-down chev"></i>
        </button>
        <div id="a1" class="collapse">
          <div class="faq-a">
            Pilih menu di halaman <a href="index.php#menuSection">Product</a>, lalu tambah ke keranjang.
            Lanjutkan proses checkout di halaman keranjang.
          </div>
        </div>
      </div>

      <div class="faq-item">
        <button class="faq-q" data-bs-toggle="collapse" data-bs-target="#a2" aria-expanded="false" aria-controls="a2">
          <span>Apakah tersedia layanan pengantaran?</span>
          <i class="bi bi-chevron-down chev"></i>
        </button>
        <div id="a2" class="collapse">
          <div class="faq-a">
            Saat ini layanan kami hanya <strong>Dine-In</strong> dan <strong>Take-Away</strong>.
          </div>
        </div>
      </div>

      <div class="faq-item">
        <button class="faq-q" data-bs-toggle="collapse" data-bs-target="#a3" aria-expanded="false" aria-controls="a3">
          <span>Di mana saya bisa melihat status pesanan?</span>
          <i class="bi bi-chevron-down chev"></i>
        </button>
        <div id="a3" class="collapse">
          <div class="faq-a">
            Semua pesanan aktif & riwayat bisa kamu lihat di <a href="history.php">Riwayat Pesanan</a>.
          </div>
        </div>
      </div>

      <div class="faq-item">
        <button class="faq-q" data-bs-toggle="collapse" data-bs-target="#a4" aria-expanded="false" aria-controls="a4">
          <span>Bagaimana cara mengubah profil atau password?</span>
          <i class="bi bi-chevron-down chev"></i>
        </button>
        <div id="a4" class="collapse">
          <div class="faq-a">
            Kamu bisa mengubah nama, nomor HP, foto, dan password di halaman <a href="profile.php">Profil</a>.
          </div>
        </div>
      </div>
    </div>

    <!-- Kontak -->
    <div class="contact">
      <a class="c-row" href="https://wa.me/6287823023379" target="_blank" rel="noopener" aria-label="WhatsApp Caffora">
        <i class="bi bi-whatsapp"></i>
        <span>WhatsApp</span>
        <i class="bi bi-chevron-right chev"></i>
      </a>

      <a class="c-row" href="mailto:cafforaproject@gmail.com" aria-label="Kirim Email ke Caffora">
        <i class="bi bi-envelope"></i>
        <span>Email</span>
        <i class="bi bi-chevron-right chev"></i>
      </a>

      <a class="c-row" href="https://maps.app.goo.gl/Fznq9KLusdNXH2RE7" target="_blank" rel="noopener" aria-label="Buka lokasi di Google Maps">
        <i class="bi bi-geo-alt"></i>
        <span>Jl. Kopi No. 123, Purwokerto</span>
        <i class="bi bi-chevron-right chev"></i>
      </a>

      <div class="c-row static" role="group" aria-label="Jam operasional">
        <i class="bi bi-clock"></i>
        <span>08.00–22.00</span>
      </div>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

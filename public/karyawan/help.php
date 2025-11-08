<?php
// public/karyawan/help.php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../../backend/auth_guard.php';
require_login(['karyawan','admin']);
require_once __DIR__ . '/../../backend/config.php';

$userName = $_SESSION['user_name'] ?? 'Staff';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <title>FAQ — Caffora</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- Fonts & CSS -->
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
      --faq-active:#fff7dd;
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

    /* TOPBAR */
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
  font-size: 16px;         /* ← teks 16px */
  line-height: 1.3;
  cursor: pointer;
  text-decoration: none;
  outline: none;
}

.back-link i {
  font-size: 18px !important;  /* ← ikon 18px */
  width: 18px;
  height: 18px;
  line-height: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}



    /* LAYOUT */
    .wrap{
      max-width:1200px;
      margin:10px auto 32px;
      padding:0 16px;
    }
/* FAQ LIST */
.faq-list{
  background:#fff;
  border-radius:0px;
  overflow:hidden;
  box-shadow:0 1px 3px rgba(0,0,0,.02);
}
.faq-item{
  border-bottom:1px solid var(--line);
  background:#fff;
  transition:background .15s ease;
}
.faq-item:last-child{ border-bottom:0; }

.faq-q{
  width:100%;
  text-align:left;
  background:#fff;
  border:0;
  padding:12px 12px;
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:16px;
  font-weight:500;
  font-size:.95rem;
  color:var(--ink);
  cursor:pointer;
  transition:background .2s ease;
}

/* efek hover & aktif hanya untuk pertanyaan */
.faq-q:hover{
  background:#fffbea;
}


/* isi jawaban tetap putih */
.faq-body{
  display:none;
  background:#fff;   /* tetap putih */
  padding:0 12px 12px;
  font-size:.9rem;
  line-height:1.6;
  color:#374151;
  transition:all .2s ease;
}

/* tampilkan jawaban saat item terbuka */
.faq-item.open .faq-body{
  display:block;
  background:#fff; /* tetap putih walau open */
}

.faq-item.open .chev{
  transform:rotate(180deg);
}
.chev{
  transition:transform .15s ease;
}

    /* CONTACT LIST */
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
      .faq-body{ font-size:.86rem; }
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

    <div class="faq-list" id="faq">
      <!-- 1 -->
      <div class="faq-item open">
        <button class="faq-q" type="button">
          <span>Pesanan baru masuk, langkah kerja yang benar gimana?</span>
          <i class="bi bi-chevron-down chev"></i>
        </button>
        <div class="faq-body">
          Buka menu <strong>Orders</strong>. Pesanan baru akan muncul dengan status <strong>New</strong> di paling atas. <br>  
          • Ubah ke <strong>Processing</strong> kalau kamu sudah mulai buat minuman / makanan. <br>
          • Ubah ke <strong>Ready</strong> kalau pesanan sudah siap diambil / diantar. <br>
          • Terakhir ubah ke <strong>Completed</strong> supaya laporan harian rapi.  
          Jangan langsung Completed ya, biar admin bisa lacak progresnya.
        </div>
      </div>

      <!-- 2 -->
      <div class="faq-item">
        <button class="faq-q" type="button">
          <span>Pelanggan bilang sudah bayar tapi status di sistem masih “Pending”.</span>
          <i class="bi bi-chevron-down chev"></i>
        </button>
        <div class="faq-body">
          Pertama cek dulu ke kasir / mutasi pembayaran apakah memang uangnya sudah masuk.  
          Kalau benar sudah masuk, buka pesanan itu di dashboard dan pilih aksi <strong>“Tandai Lunas”</strong>.  
          Setelah status pembayaran jadi <strong>Paid/Lunas</strong>, tombol cetak struk akan aktif.  
          Kalau kasus pending ini sering banget kejadian untuk metode tertentu (misal QRIS tertentu), laporkan ke admin supaya dicek koneksinya.
        </div>
      </div>

      <!-- 3 -->
      <div class="faq-item">
        <button class="faq-q" type="button">
          <span>Menu sudah diatur “Sold Out”, tapi customer masih bisa pesan.</span>
          <i class="bi bi-chevron-down chev"></i>
        </button>
        <div class="faq-body">
          Pastikan kamu set-nya dari halaman <strong>Menu Stock</strong> lalu klik <strong>Set Sold Out</strong>, bukan cuma ganti nama.  
          Sistem customer kadang butuh beberapa detik buat sync. Suruh customer <strong>refresh / buka ulang halaman</strong>.  
          Kalau tetap muncul: cek apakah ada menu kembar (misal “Latte” dan “Latte Signature”) dan yang kamu sold out itu bukan yang dipesan customer.  
          Kalau kamu yakin semua sudah benar, lapor ke admin supaya dicek cache-nya.
        </div>
      </div>

      <!-- 4 -->
      <div class="faq-item">
        <button class="faq-q" type="button">
          <span>Kenapa ada pesanan yang dobel?</span>
          <i class="bi bi-chevron-down chev"></i>
        </button>
        <div class="faq-body">
          Biasanya karena customer menekan tombol bayar dua kali waktu koneksi sedang lambat.  
          Yang harus kamu lakukan: <br>
          1) Cocokkan <strong>nomor invoice</strong>-nya, <br>
          2) Tanyakan ke customer: memang pesan dua kali atau baru sekali, <br>
          3) Kalau ternyata hanya salah satu yang benar, batalkan pesanan yang salah lewat tombol <strong>Batal</strong>.  
          Jangan lupa catat di grup / buku kas supaya admin tau kenapa ada pesanan dibatalkan.
        </div>
      </div>

      <!-- 5 -->
      <div class="faq-item">
        <button class="faq-q" type="button">
          <span>Tombol cetak struk tidak bisa diklik.</span>
          <i class="bi bi-chevron-down chev"></i>
        </button>
        <div class="faq-body">
          Struk hanya aktif kalau status pembayaran di pesanan itu sudah <strong>Lunas</strong>.  
          Jadi kalau customer bayar tunai / transfer dan sudah diterima, tandai dulu statusnya jadi lunas.  
          Baru setelah itu klik lagi tombol <strong>Struk</strong>.  
          Kalau tetap tidak keluar, cek pop-up di browser dan pastikan printer kasir sedang menyala.
        </div>
      </div>

      <!-- 6 -->
      <div class="faq-item">
        <button class="faq-q" type="button">
          <span>Saya mau ubah data akun tapi menunya terkunci.</span>
          <i class="bi bi-chevron-down chev"></i>
        </button>
        <div class="faq-body">
          Beberapa data memang dikunci dari pusat (misal nama akun, role, atau outlet) supaya tidak sembarang berubah.  
          Kamu tetap bisa ganti <strong>password</strong> sendiri dari menu profil / settings.  
          Untuk ganti nomor HP, nama, atau pindah outlet, minta ke <strong>admin utama</strong> atau pemilik toko.
        </div>
      </div>

      <!-- 7 -->
      <div class="faq-item">
        <button class="faq-q" type="button">
          <span>Dashboard tidak update atau datanya masih lama.</span>
          <i class="bi bi-chevron-down chev"></i>
        </button>
        <div class="faq-body">
          Ini biasanya karena tab dashboard sudah lama terbuka. Coba klik <strong>refresh</strong> atau <strong>logout lalu login lagi</strong>.  
          Kalau tetap sama, cek koneksi wifi/outlet. Kalau di tempat lain normal tapi di outlet kamu lambat, laporkan ke admin supaya dicek servernya.
        </div>
      </div>
    </div>

    <!-- Kontak -->
    <div class="contact">
      <a class="c-row" href="https://wa.me/6285905446517" target="_blank" rel="noopener" aria-label="WhatsApp Caffora">
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

  <!-- JS Bootstrap (kalau nanti butuh komponen lain) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // accordion manual
    const items = document.querySelectorAll('.faq-item');
    items.forEach(item => {
      const btn = item.querySelector('.faq-q');
      btn.addEventListener('click', () => {
        // tutup semua dulu
        items.forEach(i => {
          if (i !== item) i.classList.remove('open');
        });
        // toggle yang diklik
        item.classList.toggle('open');
      });
    });
  </script>
</body>
</html>

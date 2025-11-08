<?php
// public/admin/help.php
declare(strict_types=1);

require_once __DIR__ . '/../../backend/auth_guard.php';
require_login(['admin']); // hanya admin
require_once __DIR__ . '/../../backend/config.php';

$userName = $_SESSION['user_name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <title>FAQ — Caffora (Admin)</title>
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
    html,body{ background:var(--bg); color:var(--ink); margin:0; }

   /* TOPBAR (samakan dengan settings) */
.topbar{
  position: sticky;
  top: 0;
  z-index: 20;
  background: #fff;
  border-bottom: 1px solid rgba(0,0,0,.04);
  padding-right: 40px;            /* ⬅️ ruang kanan untuk ikon */
}
.topbar .inner{
  max-width: 1200px;
  margin: 0 auto;
  padding: 12px 24px;             /* ⬅️ padding seperti settings */
  min-height: 52px;
  display: flex;
  align-items: center;
  gap: 8px;
}

/* Back link — beri jarak ke input search/teks */
.back-link{
  display: inline-flex;
  align-items: center;
  gap: 10px;
  color: var(--brown);
  text-decoration: none;
  font-weight: 600;
  font-size: 16px;
  line-height: 1.3;
  padding-right: 50px;            /* ⬅️ sedikit geser ke kanan */
}
.back-link i{
  font-size: 18px !important;
  width: 18px; height: 18px; line-height: 1;
  display: inline-flex; align-items: center; justify-content: center;
}

/* Responsif tetap seperti semula */
@media (max-width:576px){
  .topbar .inner{ max-width:100%; padding:30px 30px; }
}

    /* LAYOUT */
    .wrap{ max-width:1200px; margin:10px auto 10px; padding:0 16px; }

    /* FAQ LIST (sama seperti karyawan) */
    .faq-list{ background:#fff; border-radius:0; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.02); }
    .faq-item{ border-bottom:1px solid var(--line); background:#fff; transition:background .15s ease; }
    .faq-item:last-child{ border-bottom:0; }
    .faq-q{
      width:100%; text-align:left; background:#fff; border:0; padding:12px 12px;
      display:flex; align-items:center; justify-content:space-between; gap:16px;
      font-weight:500; font-size:.95rem; color:var(--ink); cursor:pointer; transition:background .2s ease;
    }
    .faq-q:hover{ background:#fffbea; }
    .faq-body{ display:none; background:#fff; padding:0 12px 12px; font-size:.9rem; line-height:1.6; color:#374151; transition:all .2s ease; }
    .faq-item.open .faq-body{ display:block; background:#fff; }
    .faq-item.open .chev{ transform:rotate(180deg); }
    .chev{ transition:transform .15s ease; }

    /* CONTACT LIST (disalin) */
    .contact{ margin-top:14px; background:#fff; border-radius:14px; padding:4px 10px; box-shadow:0 1px 3px rgba(0,0,0,.02); }
    .c-row{
      display:grid; grid-template-columns:24px 1fr 16px; align-items:center; gap:10px;
      padding:10px 2px; text-decoration:none; color:var(--ink); font-weight:500; font-size:.9rem;
    }
    .c-row .bi{ font-size:1.05rem; color:var(--brown); }
    .c-row .chev{ color:#a3a3a3; }
    .c-row.static{ grid-template-columns:24px 1fr; }

    @media (max-width:576px){
      .topbar .inner, .wrap{ max-width:100%; padding:10px 10px; }
      .wrap{ margin:8px auto 28px; }
      .faq-q{ font-size:.88rem; }
      .faq-body{ font-size:.86rem; }
      .c-row{ font-size:.88rem; padding:8px 0; }
    }

    /* ===== Samakan lebar isi dengan padding topbar seperti di settings ===== */
@media (min-width: 992px){
  .wrap {
    max-width: 1400px;       /* dari 1200px jadi lebih lebar */
    padding-left: 30px;      /* tambah ruang kiri */
    padding-right: 30px;     /* tambah ruang kanan */
  }
}


   /* ====== kode responsif ipad rotasi ====== */
    @media screen and (orientation:landscape) and (min-width:1024px) and (max-width:1366px){
  .topbar .inner,
  .page-inner, .settings-inner{
    max-width: 100%;
    padding-left: max(30px, env(safe-area-inset-left));
    padding-right: 24px;
  }
}
    
@media screen and (max-width: 768px) {
  /* atur jarak kiri kanan topbar ini di hp ngga rotasi y gaes */
  .topbar {
    padding-left: 12px;
    padding-right: 12px;
  }
  </style>
</head>
<body>

  <!-- Header -->
  <div class="topbar">
    <div class="inner">
      <a class="back-link" href="<?= BASE_URL ?>/public/admin/index.php" aria-label="Kembali">
        <i class="bi bi-arrow-left"></i><span>Kembali</span>
      </a>
    </div>
  </div>

  <main class="wrap">

   <div class="faq-list" id="faq">
  <!-- 1 -->
  <div class="faq-item open">
    <button class="faq-q" type="button">
      <span>Alur verifikasi & rekonsiliasi pembayaran yang benar?</span>
      <i class="bi bi-chevron-down chev"></i>
    </button>
    <div class="faq-body">
      Buka <strong>Orders → Payments</strong> lalu cek transaksi <em>Pending</em>.<br>
      • Cocokkan <strong>nominal</strong>, <strong>metode</strong>, dan <strong>waktu</strong> dengan mutasi bank/Midtrans.<br>
      • Jika valid, klik <strong>Mark as Paid</strong>; jika tidak, pilih <strong>Fail/Refund</strong> dengan catatan.<br>
      • Untuk rekonsiliasi harian, unduh <strong>Export CSV</strong> dan cocokkan dengan laporan bank (settlement).  
      Tip: pastikan jam perangkat & zona waktu toko sudah sesuai agar grafik harian sinkron.
    </div>
  </div>

  <!-- 2 -->
  <div class="faq-item">
    <button class="faq-q" type="button">
      <span>Grafik revenue 7 hari tidak muncul penuh / tanggal kosong.</span>
      <i class="bi bi-chevron-down chev"></i>
    </button>
    <div class="faq-body">
      • Periksa <strong>rentang tanggal</strong> yang dipilih (Today / 7 days / 30 days / Custom).<br>
      • Pastikan <strong>zona waktu</strong> toko/perangkat benar, lalu <strong>refresh</strong> halaman Finance.<br>
      • Transaksi <em>failed/void/refunded</em> tidak dihitung revenue—cek ringkasan status bila angkanya janggal.
    </div>
  </div>

  <!-- 3 -->
<div class="faq-item">
  <button class="faq-q" type="button">
    <span>Bisakah admin mengubah pajak, biaya layanan, atau pembulatan?</span>
    <i class="bi bi-chevron-down chev"></i>
  </button>
  <div class="faq-body">
    Pengaturan pajak dan biaya layanan bersifat tetap dan ditentukan oleh kebijakan toko.  
    Admin tidak dapat mengubahnya langsung melalui tampilan sistem.  
    Jika perlu penyesuaian tarif pajak, pembulatan harga, atau biaya layanan,  
    koordinasikan dengan <strong>pihak developer</strong> agar disesuaikan secara resmi.  
    Semua perubahan tersebut akan otomatis tercermin pada laporan keuangan dan struk setelah disetujui.
  </div>
</div>


  <!-- 4 -->
  <div class="faq-item">
    <button class="faq-q" type="button">
      <span>Status pesanan di Orders berbeda dengan status pembayaran di Payments.</span>
      <i class="bi bi-chevron-down chev"></i>
    </button>
    <div class="faq-body">
      Buka pesanan terkait lalu gunakan <strong>Sinkronkan Status</strong> untuk menyamakan <em>order</em> & <em>payment</em>.  
      Jika integrasi pihak ketiga sempat bermasalah, jalankan <strong>Recheck Payment</strong> pada transaksi tersebut.
    </div>
  </div>
   
  <!-- 5 -->
<div class="faq-item">
  <button class="faq-q" type="button">
    <span>Bagaimana cara mengelola menu di Catalog?</span>
    <i class="bi bi-chevron-down chev"></i>
  </button>
  <div class="faq-body">
    Buka <strong>Catalog</strong> untuk menambahkan, mengubah, atau menghapus menu.<br>
    • Gunakan tombol <strong>Add Menu</strong> untuk menambah item baru lengkap dengan harga dan kategori.<br>
    • Klik <strong>Edit</strong> jika ingin memperbarui nama, foto produk, atau harga menu.<br>
    • Pilih <strong>Hapus</strong> untuk menghapus menu yang sudah tidak tersedia.<br><br>
    Semua perubahan akan langsung terlihat oleh karyawan dan customer setelah disimpan.
  </div>
</div>


  <!-- 6 -->
  <div class="faq-item">
    <button class="faq-q" type="button">
      <span>Membuat, mengubah, atau menonaktifkan akun pengguna & role</span>
      <i class="bi bi-chevron-down chev"></i>
    </button>
    <div class="faq-body">
      Masuk <strong>Users</strong> → <em>Add User</em> untuk akun baru (role: admin/karyawan/customer).  
      Batasi akses dengan <strong>Permissions</strong> per modul bila tersedia.  
      Nonaktifkan user via <strong>Deactivate</strong>; data historis tetap tersimpan.
    </div>
  </div>

  <!-- 7 -->
  <div class="faq-item">
    <button class="faq-q" type="button">
      <span>Refund/void transaksi & dampaknya ke laporan</span>
      <i class="bi bi-chevron-down chev"></i>
    </button>
    <div class="faq-body">
      Dari <strong>Orders → Payments</strong>, pilih transaksi → <strong>Refund</strong> atau <strong>Void</strong> sesuai kebijakan.  
      Revenue periode terkait akan menyesuaikan; pesanan ditandai <em>cancelled/failed</em> sesuai alur.
    </div>
  </div>

  <!-- 8 -->
  <div class="faq-item">
    <button class="faq-q" type="button">
      <span>Ekspor laporan (CSV/Excel) berdasarkan periode & filter</span>
      <i class="bi bi-chevron-down chev"></i>
    </button>
    <div class="faq-body">
      Di <strong>Finance</strong>, pilih rentang tanggal (Today/7 days/30 days/Custom), lalu klik <strong>Export CSV</strong>.  
      Gunakan filter <em>status</em> untuk memisahkan <em>paid/failed/refunded</em> saat analisis di spreadsheet.
    </div>
  </div>

  <!-- 9 -->
  <div class="faq-item">
    <button class="faq-q" type="button">
      <span>Kirim pengumuman/flash sale via notifikasi</span>
      <i class="bi bi-chevron-down chev"></i>
    </button>
    <div class="faq-body">
      Buka <strong>Kirim Notifikasi</strong>, isi judul & konten, pilih target (<em>semua</em>/role/segmen), lalu <strong>Kirim</strong>.  
      Lihat riwayat & metrik baca di <strong>Notifications</strong>.
    </div>
  </div>

  <!-- 10 -->
  <div class="faq-item">
    <button class="faq-q" type="button">
      <span>Mengelola harga: langsung atau terjadwal?</span>
      <i class="bi bi-chevron-down chev"></i>
    </button>
    <div class="faq-body">
      Di <strong>Catalog → Edit Menu</strong> pilih <em>Update Now</em> untuk langsung, atau <em>Schedule</em> untuk aktif pada waktu tertentu.  
      Perubahan terjadwal terekam di audit log.
    </div>
  </div>

 <!-- 11 -->
<div class="faq-item">
  <button class="faq-q" type="button">
    <span>Apa fungsi menu Settings?</span>
    <i class="bi bi-chevron-down chev"></i>
  </button>
  <div class="faq-body">
    Menu <strong>Settings</strong> digunakan untuk mengelola profil admin, seperti memperbarui nama, foto, email, kata sandi, dan nomor kontak.  
    Fitur ini berfokus pada pengaturan akun dan identitas pengguna.
  </div>
</div>

    <!-- Kontak -->
    <div class="contact">
      <a class="c-row" href="https://wa.me/628782302337" target="_blank" rel="noopener" aria-label="WhatsApp Caffora">
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
  <script>
    // accordion manual (sama seperti karyawan)
    const items = document.querySelectorAll('.faq-item');
    items.forEach(item => {
      const btn = item.querySelector('.faq-q');
      btn.addEventListener('click', () => {
        items.forEach(i => { if (i !== item) i.classList.remove('open'); });
        item.classList.toggle('open');
      });
    });
  </script>
</body>
</html>

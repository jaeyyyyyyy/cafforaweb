<?php  
// public/customer/receipt.php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../../backend/auth_guard.php';
require_login(['customer']);
require_once __DIR__ . '/../../backend/config.php'; // $conn, BASE_URL, h()

function rp(float $n): string { return 'Rp ' . number_format($n, 0, ',', '.'); }

$orderId = (int)($_GET['order'] ?? 0);
$userId  = (int)($_SESSION['user_id'] ?? 0);

/* --- ambil data pesanan + item + invoice --- */
$ord   = null; 
$items = []; 
$inv   = null;

if ($orderId && $userId) {
  // ambil order
  $stmt = $conn->prepare("SELECT * FROM orders WHERE id=? AND user_id=? LIMIT 1");
  $stmt->bind_param('ii', $orderId, $userId);
  $stmt->execute();
  $ord = $stmt->get_result()?->fetch_assoc(); 
  $stmt->close();

  if ($ord) {
    // ambil item
    $stmt = $conn->prepare("
      SELECT oi.qty, oi.price, m.name AS menu_name
      FROM order_items oi
      LEFT JOIN menu m ON m.id=oi.menu_id
      WHERE oi.order_id=? ORDER BY oi.id
    ");
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $items = $stmt->get_result()?->fetch_all(MYSQLI_ASSOC) ?? [];
    $stmt->close();

    // ambil invoice
    $stmt = $conn->prepare("SELECT amount, issued_at FROM invoices WHERE order_id=? LIMIT 1");
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $inv = $stmt->get_result()?->fetch_assoc();
    $stmt->close();
  }
}

/* --- guard akses --- */
if (!$ord) { 
  http_response_code(403); 
  echo 'Tidak boleh mengakses'; 
  exit; 
}

/* =========================================================
   CASE 1: STRUK BELUM BISA DITAMPILKAN (BELUM LUNAS)
   ========================================================= */
if ($ord['payment_status'] !== 'paid') {
  ?>
  <!doctype html>
  <html lang="id">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Struk belum tersedia — Caffora</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>

    <style>
      :root{
        --gold:#FFD54F;
        --brown:#4B3F36;
        --line:#e5e7eb;
        --ink:#111827;
        --muted:#6b7280;
        --bg-page:#fafafa;
        --bg-card:#ffffff;
      }

      *{
        box-sizing:border-box;
        font-family: "Poppins", system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
      }

      html, body {
        margin:0;
        padding:0;
        background:var(--bg-page);
        color:var(--ink);
        -webkit-font-smoothing:antialiased;
      }

      /* ===== topbar seragam ===== */
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
      
      /* konten */
      .page-wrapper{
        max-width:1200px;
        margin:14px auto 40px;
        padding:0 16px;
      }
      .status-card{
        background:var(--bg-card);
        border-radius:14px;
        border:1px solid rgba(0,0,0,.03);
        box-shadow:0 6px 18px rgba(0,0,0,.03);
        padding:18px 16px 20px;
      }
      .status-title{
        margin:0 0 10px;
        font-weight:600;
        font-size:1.05rem;
      }
      .status-desc{
        margin:0 0 10px;
        color:var(--muted);
      }

      @media (min-width:768px){
        .topbar .inner{ padding:12px 24px; }
        .page-wrapper{ padding:0 24px; }
        .status-card{ padding:20px 20px 22px; }
      }
    </style>
  </head>
  <body>

    <div class="topbar">
      <div class="inner">
        <a class="back-link" href="<?= h(BASE_URL) ?>/public/customer/history.php">
          <i class="bi bi-arrow-left"></i>
          <span>Kembali</span>
        </a>
      </div>
    </div>

    <div class="page-wrapper">
      <section class="status-card">
        <h2 class="status-title">Struk belum tersedia</h2>
        <p class="status-desc">
          Struk hanya dapat diunduh setelah pembayaran <b>lunas</b>.
        </p>
        <p class="status-invoice">
          Invoice: <b><?= h($ord['invoice_no']) ?></b>
        </p>
      </section>
    </div>

  </body>
  </html>
  <?php
  exit;
}

/* =========================================================
   CASE 2: LUNAS → STRUK LENGKAP
   ========================================================= */

/* siapkan angka supaya aman kalau backend lama belum punya kolom pajak */
$subtotal    = isset($ord['subtotal'])     ? (float)$ord['subtotal']     : (float)($ord['total'] ?? 0);
$taxAmount   = isset($ord['tax_amount'])   ? (float)$ord['tax_amount']   : 0.0;
$grandTotal  = isset($ord['grand_total'])  ? (float)$ord['grand_total']  : ($subtotal + $taxAmount);
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Struk — <?= h($ord['invoice_no']) ?> | Caffora</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <script>
    function loadScript(src){
      return new Promise((res,rej)=>{
        const s=document.createElement('script');
        s.src=src;
        s.async=true;
        s.crossOrigin='anonymous';
        s.onload=res;
        s.onerror=()=>rej(new Error('Gagal memuat '+src));
        document.head.appendChild(s);
      });
    }
    async function ensureHtml2Canvas(){
      if (window.html2canvas) return;
      try{
        await loadScript('https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js');
      }catch(e){
        await loadScript('https://unpkg.com/html2canvas@1.4.1/dist/html2canvas.min.js');
      }
    }
  </script>

  <style>
    :root{
      --ink:#111827;
      --muted:#6b7280;
      --line:#e5e7eb;
      --bg-page:#fafafa;
      --bg-card:#ffffff;
      --gold:#FFD54F;
      --brown:#4B3F36;
    }

    *{ 
      font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; 
      box-sizing:border-box; 
    }

    html,body{
      background:var(--bg-page);
      color:var(--ink);
      margin:0; 
      padding:0;
      -webkit-font-smoothing:antialiased;
    }

    /* ===== TOPBAR SERAGAM ===== */
    .topbar{
      position:sticky;
      top:0;
      background:#fff;
      border-bottom:1px solid rgba(0,0,0,.04);
      z-index:20;
    }
    .topbar .inner{
      max-width:1200px;
      margin:0 auto;
      padding:12px 16px;
      min-height:52px;
      display:flex;
      align-items:center;
      gap:10px;
      justify-content:space-between;
    }
        .back-link {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  color: var(--brown);
  text-decoration: none;
  font-weight: 600;
  font-size: 16px;     
  line-height: 1.3;
}


.back-link .bi {
  font-size: 18px !important;  
  width: 18px;
  height: 18px;
  line-height: 1;              
  display: inline-flex;       
  align-items: center;
  justify-content: center;
}

    /* tombol download (tetap style kamu) */
    .btn-download {
      background-color: var(--gold);
      color: var(--brown) !important;
      border: 0;
      border-radius: 12px;
      padding: 10px 14px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      text-decoration: none !important;
      box-shadow: none;
      cursor: pointer;
      white-space: nowrap;
    }
    .btn-download,
    .btn-download * {
      font-family: Arial, sans-serif !important;
      font-weight: 600;
      font-size: 13.3px;
      line-height: 1.2;
      color: var(--brown) !important;
    }

    /* ===== KONTEN STRUK ===== */
    .page-wrapper { 
      max-width: 1200px;
      margin: 14px auto 40px;
      padding: 0 16px;
      display: flex; 
      justify-content: center; 
    }
    .paper {
      background: var(--bg-card);
      width: 100%; 
      max-width: 320px;
      box-shadow: 0 2px 6px rgba(0,0,0,.05);
      padding: 18px 20px;
      border: 1px solid var(--line);
    }

    .paper,
    .paper * {
      font-family: Arial, sans-serif !important;
      color: #4b5563 !important;
    }

    .brand { 
      font-weight: 600; 
      font-size: 16px; 
      margin-bottom: 4px;
    }
    .meta {
      display: flex; 
      justify-content: space-between; 
      gap: 10px;
      font-size: .8rem; 
      line-height: 1.4;
    }
    .to { text-align: right; }

    .rule { 
      border-top: 2px dashed #aaa; 
      margin: 10px 0; 
      opacity: .8; 
    }
    .head-row { 
      display: flex; 
      justify-content: space-between; 
      font-weight: 500; 
      padding: 4px 0 6px; 
      font-size: .8rem; 
    }

    .item { 
      display: grid; 
      grid-template-columns: 1fr auto; 
      gap: 12px; 
      align-items: flex-start; 
      padding: 8px 0; 
    }
    .item-name { font-weight: 500; font-size: .85rem; }
    .item-sub { font-size: .75rem; line-height: 1.4; margin-top: 1px; }
    .item-subtotal { font-weight: 500; white-space: nowrap; font-size: .8rem; text-align: right; }

    .row { 
      display: flex; 
      justify-content: space-between; 
      padding: 6px 0; 
      font-size: .8rem; 
    }
    .row.total { 
      font-weight: 600; 
      font-size: .9rem; 
      border-top: 2px dotted var(--line); 
      margin-top: 4px; 
      padding-top: 8px; 
    }

    .status-line { 
      display: flex; 
      flex-wrap: wrap; 
      justify-content: space-between; 
      gap: 8px 12px; 
      padding-top: 8px; 
      font-size: .8rem; 
    }
    .pill { 
      padding: 0; 
      border: none !important; 
      background: transparent !important;
      font-weight: 700 !important; 
      letter-spacing: .18rem; 
      font-size: .75rem; 
    }

    .footer-note { 
      font-size: .7rem; 
      line-height: 1.5; 
    }

    @media (min-width: 768px){
      .topbar .inner,
      .page-wrapper{ padding:12px 24px; }
      .page-wrapper{ margin:16px auto 50px; }
    }

    @keyframes spin{from{transform:rotate(0)}to{transform:rotate(360deg)}}
    .spin{
      width:14px; 
      height:14px;
      border:2px solid currentColor; 
      border-right-color:transparent;
      border-radius:50%;
      display:inline-block;
      animation:spin .7s linear infinite;
    }

    @media print {
      .topbar { display:none !important; }
      body { background:#fff; }
      .page-wrapper{ margin:0; padding:0; }
      .paper{ box-shadow:none; border:0; max-width:100%; }
    }
  </style>
</head>
<body>

  <div class="topbar">
    <div class="inner">
      <a class="back-link" href="<?= h(BASE_URL) ?>/public/customer/history.php">
        <i class="bi bi-arrow-left chev"></i>
        <span>Kembali</span>
      </a>
      <button id="btnDownload" class="btn-download">
        <svg viewBox="0 0 24 24" fill="currentColor">
          <path d="M12 3a1 1 0 011 1v8.586l2.293-2.293a1 1 0 111.414 1.414l-4.001 4a1 1 0 01-1.414 0l-4.001-4a1 1 0 111.414-1.414L11 12.586V4a1 1 0 011-1z"></path>
          <path d="M5 19a1 1 0 011-1h12a1 1 0 110 2H6a1 1 0 01-1-1z"></path>
        </svg>
        <span>Download</span>
      </button>
    </div>
  </div>

  <div class="page-wrapper">
    <main class="paper" id="receiptContent">
      <div class="brand">Caffora</div>

      <div class="meta">
        <div>
          <div>Invoice: <span style="font-weight:500"><?= h($ord['invoice_no']) ?></span></div>
          <div>Tanggal: <?= h(date('d M Y H:i', strtotime($ord['created_at']))) ?></div>
        </div>
        <div class="to">
          <div style="color:#9ca3af">Kepada:</div>
          <div style="font-weight:500"><?= h($ord['customer_name']) ?></div>
          <div>
            <?= h($ord['service_type']==='dine_in' ? 'Dine In' : 'Take Away') ?>
            <?= $ord['table_no'] ? ', Meja '.h($ord['table_no']) : '' ?>
          </div>
        </div>
      </div>

      <div class="rule"></div>

      <div class="head-row">
        <div>Item</div>
        <div>Subtotal</div>
      </div>

      <div class="items">
        <?php foreach($items as $it): 
          $sub = (float)$it['qty'] * (float)$it['price']; ?>
          <div class="item">
            <div>
              <div class="item-name"><?= h($it['menu_name'] ?? 'Menu') ?></div>
              <div class="item-sub">
                Qty: <?= (int)$it['qty'] ?> × <?= rp((float)$it['price']) ?>
              </div>
            </div>
            <div class="item-subtotal"><?= rp($sub) ?></div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- RINGKASAN TOTAL SESUAI CHECKOUT -->
      <div class="row" style="margin-top:6px">
        <div>Subtotal</div>
        <div class="muted"><?= rp($subtotal) ?></div>
      </div>
      <div class="row">
        <div>Pajak 11%</div>
        <div class="muted"><?= rp($taxAmount) ?></div>
      </div>
      <div class="row total">
        <div>Total</div>
        <div><?= rp($grandTotal) ?></div>
      </div>

      <div class="status-line">
        <div class="pill">L U N A S</div>
        <div>
          Metode Pembayaran:
          <b><?= h(strtoupper(str_replace('_',' ',$ord['payment_method'] ?? '-'))) ?></b>
        </div>
      </div>

      <div class="rule"></div>

      <div class="footer-note">
        <?php if ($inv): ?>
          Tagihan: <b><?= rp((float)$inv['amount']) ?></b><br>
          Diterbitkan: <?= h(date('d M Y H:i', strtotime($inv['issued_at']))) ?><br>
        <?php endif; ?>
        * Harga sudah termasuk PPN 11%. Terima kasih telah berbelanja di <b>Caffora</b>.
      </div>
    </main>
  </div>

  <script>
    (async function(){
      const btn = document.getElementById('btnDownload');
      await ensureHtml2Canvas();

      btn.addEventListener('click', async () => {
        if (btn.disabled) return;
        const original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spin"></span> Mengunduh...';

        try{
          const srcNode = document.getElementById('receiptContent');
          const clone = srcNode.cloneNode(true);

          const wrapper = document.createElement('div');
          wrapper.style.position='fixed';
          wrapper.style.left='-10000px';
          wrapper.style.background='#fff';
          wrapper.style.width='320px';
          wrapper.appendChild(clone);
          document.body.appendChild(wrapper);

          const canvas = await html2canvas(wrapper,{
            background:'#fff',
            scale:2,
            useCORS:true,
            windowWidth:320
          });

          document.body.removeChild(wrapper);

          canvas.toBlob(function(blob){
            if (!blob){
              alert('Gagal membuat gambar');
              return;
            }
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'Struk_<?= h($ord['invoice_no']) ?>.png';
            document.body.appendChild(a);
            a.click();
            a.remove();
            setTimeout(()=>URL.revokeObjectURL(url),1000);
          }, 'image/png', 1.0);

        }catch(err){
          alert('Checkout berhasil tapi unduh struk gagal: ' + (err?.message || err));
        }finally{
          btn.disabled=false;
          btn.innerHTML=original;
        }
      });
    })();
  </script>
</body>
</html>

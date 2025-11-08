<?php 
// public/karyawan/receipt.php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../../backend/auth_guard.php';
// ⬇⬇⬇ REVISI: karyawan saja
require_login(['karyawan']);
require_once __DIR__ . '/../../backend/config.php'; // $conn, BASE_URL, h()

function rp(float $n): string { return 'Rp ' . number_format($n, 0, ',', '.'); }

$orderId = (int)($_GET['order'] ?? 0);

/* --- ambil data pesanan + item + invoice --- */
$ord   = null;
$items = [];
$inv   = null;

if ($orderId) {
  // karyawan boleh lihat semua order
  $stmt = $conn->prepare("SELECT * FROM orders WHERE id=? LIMIT 1");
  $stmt->bind_param('i', $orderId);
  $stmt->execute();
  $ord = $stmt->get_result()?->fetch_assoc();
  $stmt->close();

  if ($ord) {
    // item
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

    // invoice
    $stmt = $conn->prepare("SELECT amount, issued_at FROM invoices WHERE order_id=? LIMIT 1");
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $inv = $stmt->get_result()?->fetch_assoc();
    $stmt->close();
  }
}

/* --- guard akses --- */
if (!$ord) {
  http_response_code(404);
  echo 'Order tidak ditemukan';
  exit;
}

/* siapkan angka supaya aman kalau backend lama belum punya kolom pajak */
$subtotal    = isset($ord['subtotal'])     ? (float)$ord['subtotal']     : (float)($ord['total'] ?? 0);
$taxAmount   = isset($ord['tax_amount'])   ? (float)$ord['tax_amount']   : 0.0;
$grandTotal  = isset($ord['grand_total'])  ? (float)$ord['grand_total']  : ($subtotal + $taxAmount);

/* =========================================================
   CASE 1: BELUM LUNAS
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
      }
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
      .back-link{
        display:inline-flex;
        align-items:center;
        gap:8px;
        color:var(--ink);
        text-decoration:none;
        font-weight:600;
        font-size:.95rem;
      }
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
      @media (min-width:768px){
        .topbar .inner{ padding:12px 24px; }
        .page-wrapper{ padding:0 24px; }
      }
    </style>
  </head>
  <body>
    <div class="topbar">
      <div class="inner">
        <a class="back-link" href="<?= h(BASE_URL) ?>/public/karyawan/orders.php">
          <i class="bi bi-arrow-left"></i>
          <span>Kembali</span>
        </a>
      </div>
    </div>

    <div class="page-wrapper">
      <section class="status-card">
        <h2 style="margin:0 0 10px;font-weight:600;">Struk belum tersedia</h2>
        <p style="color:var(--muted);margin:0 0 6px">
          Struk hanya bisa dicetak kalau pembayaran <b>lunas</b>.
        </p>
        <p style="margin:0;">
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
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Struk — <?= h($ord['invoice_no']) ?> | Caffora</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

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
    }
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
    .back-link{
      display:inline-flex;
      align-items:center;
      gap:8px;
      color:var(--ink);
      text-decoration:none;
      font-weight:600;
      font-size:.95rem;
      line-height:1.3;
      font-family: Arial, Helvetica, sans-serif !important;
    }
    .btn-print {
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
    .btn-print,
    .btn-print * {
      font-family: Arial, sans-serif !important;
      font-weight: 600;
      font-size: 13.3px;
      line-height: 1.2;
      color: var(--brown) !important;
    }
    .page-wrapper{
      max-width: 1200px;
      margin: 14px auto 40px;
      padding: 0 16px;
      display:flex;
      justify-content:center;
    }
    .paper{
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
    .brand{ font-weight: 600; font-size: 16px; margin-bottom: 4px; }
    .meta{
      display:flex;
      justify-content:space-between;
      gap:10px;
      font-size:.8rem;
      line-height:1.4;
    }
    .to{ text-align:right; }
    .rule{
      border-top: 2px dashed #aaa;
      margin: 10px 0;
      opacity: .8;
    }
    .head-row{
      display:flex;
      justify-content:space-between;
      font-weight:500;
      padding:4px 0 6px;
      font-size:.8rem;
    }
    .item{
      display:grid;
      grid-template-columns:1fr auto;
      gap:12px;
      align-items:flex-start;
      padding:8px 0;
    }
    .item-name{ font-weight:500; font-size:.85rem; }
    .item-sub{ font-size:.75rem; line-height:1.4; margin-top:1px; }
    .item-subtotal{ font-weight:500; white-space:nowrap; font-size:.8rem; text-align:right; }
    .row{
      display:flex;
      justify-content:space-between;
      padding:6px 0;
      font-size:.8rem;
    }
    .row.total{
      font-weight:600;
      font-size:.9rem;
      border-top:2px dotted var(--line);
      margin-top:4px;
      padding-top:8px;
    }
    .status-line{
      display:flex;
      flex-wrap:wrap;
      justify-content:space-between;
      gap:8px 12px;
      padding-top:8px;
      font-size:.8rem;
    }
    .pill{
      padding:0;
      border:none !important;
      background:transparent !important;
      font-weight:700 !important;
      letter-spacing:.18rem;
      font-size:.75rem;
    }
    .footer-note{
      font-size:.7rem;
      line-height:1.5;
    }
    @media (min-width:768px){
      .topbar .inner,
      .page-wrapper{ padding:12px 24px; }
      .page-wrapper{ margin:16px auto 50px; }
    }

    /* PRINT 58mm DENGAN MARGIN */
    @media print {
      @page {
        size: 58mm auto;
        margin: 3mm;
      }
      html, body {
        width: 58mm;
        background: #fff !important;
        margin: 0;
        padding: 0;
      }
      .topbar { display: none !important; }
      .page-wrapper {
        margin: 0;
        padding: 0;
        display: block;
        width: 58mm;
      }
      .paper {
        width: calc(58mm - 6mm);
        max-width: calc(58mm - 6mm);
        border: 0;
        box-shadow: none;
        padding: 6px 6px 10px;
      }
      .brand { font-size: 13px; }
      .meta { font-size: 10px; }
      .head-row { font-size: 10px; }
      .item-name { font-size: 10px; }
      .item-sub { font-size: 9px; }
      .item-subtotal { font-size: 10px; }
      .row { font-size: 10px; }
      .row.total { font-size: 11px; }
      .footer-note { font-size: 9px; }
      body::before,
      body::after {
        display: none !important;
      }
    }
  </style>
</head>
<body>

  <div class="topbar">
    <div class="inner">
      <a class="back-link" href="<?= h(BASE_URL) ?>/public/karyawan/orders.php">
        <i class="bi bi-arrow-left"></i>
        <span>Kembali</span>
      </a>
      <button id="btnPrint" class="btn-print" type="button">
        <svg viewBox="0 0 24 24" fill="currentColor">
          <path d="M6 9V3h12v6h1a3 3 0 013 3v4h-4v4H6v-4H2v-4a3 3 0 013-3h1zm2 0h8V5H8zm8 10v-4H8v4zM6 13h2v2H6z"></path>
        </svg>
        <span>Print</span>
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
          <div style="color:#9ca3af">Customer:</div>
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

      <!-- pakai subtotal & pajak baru -->
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
        * Harga sudah termasuk PPN 11%. Terima kasih sudah belanja di <b>Caffora</b>.
      </div>
    </main>
  </div>

  <script>
    document.getElementById('btnPrint')?.addEventListener('click', function () {
      window.print();
    });
  </script>
</body>
</html>

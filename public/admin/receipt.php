<?php
// public/admin/receipt.php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../../backend/auth_guard.php';
require_login(['admin']); // ← ADMIN SAJA
require_once __DIR__ . '/../../backend/config.php';

function rp(float $n): string { return 'Rp ' . number_format($n, 0, ',', '.'); }

$orderId = (int)($_GET['order'] ?? 0);

$ord = null;
$items = [];
$inv = null;

if ($orderId) {
  $stmt = $conn->prepare("SELECT * FROM orders WHERE id=? LIMIT 1");
  $stmt->bind_param('i', $orderId);
  $stmt->execute();
  $ord = $stmt->get_result()?->fetch_assoc();
  $stmt->close();

  if ($ord) {
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

    $stmt = $conn->prepare("SELECT amount, issued_at FROM invoices WHERE order_id=? LIMIT 1");
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $inv = $stmt->get_result()?->fetch_assoc();
    $stmt->close();
  }
}

if (!$ord) {
  http_response_code(404);
  echo 'Order tidak ditemukan';
  exit;
}

$subtotal   = isset($ord['subtotal'])    ? (float)$ord['subtotal']    : (float)($ord['total'] ?? 0);
$taxAmount  = isset($ord['tax_amount'])  ? (float)$ord['tax_amount']  : 0.0;
$grandTotal = isset($ord['grand_total']) ? (float)$ord['grand_total'] : ($subtotal + $taxAmount);

if ($ord['payment_status'] !== 'paid') {
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Struk belum tersedia — Caffora</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
  <style>
    :root{
      --ink:#111827;--muted:#6b7280;--bg:#fafafa;
    }
    *{box-sizing:border-box;font-family:Poppins,Arial,sans-serif;}
    body{margin:0;background:var(--bg);color:var(--ink);}
    .topbar{position:sticky;top:0;background:#fff;border-bottom:1px solid rgba(0,0,0,.04);}
    .inner{max-width:1200px;margin:0 auto;padding:12px 16px;display:flex;gap:10px;align-items:center;}
    .back-link{display:inline-flex;gap:8px;align-items:center;text-decoration:none;color:var(--ink);font-weight:600;font-family:Arial,Helvetica,sans-serif !important;}
    .page{max-width:1200px;margin:14px auto 40px;padding:0 16px;}
    .card{background:#fff;border-radius:14px;padding:18px 16px;}
  </style>
</head>
<body>
  <div class="topbar">
    <div class="inner">
      <a class="back-link" href="<?= h(BASE_URL) ?>/public/admin/orders.php"><i class="bi bi-arrow-left"></i><span>Kembali</span></a>
    </div>
  </div>
  <div class="page">
    <div class="card">
      <h2 style="margin:0 0 10px;font-weight:600;">Struk belum tersedia</h2>
      <p style="margin:0 0 8px;color:var(--muted);">Struk bisa dicetak setelah pembayaran <b>lunas</b>.</p>
      <p style="margin:0;">Invoice: <b><?= h($ord['invoice_no']) ?></b></p>
    </div>
  </div>
</body>
</html>
<?php
  exit;
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Struk — <?= h($ord['invoice_no']) ?> | Caffora</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root{
      --ink:#111827;--muted:#6b7280;--line:#e5e7eb;
      --bg:#fafafa;--card:#fff;--gold:#FFD54F;--brown:#4B3F36;
    }
    *{box-sizing:border-box;font-family:Inter,system-ui,Arial,sans-serif;}
    body{margin:0;background:var(--bg);color:var(--ink);}
    .topbar{position:sticky;top:0;background:#fff;border-bottom:1px solid rgba(0,0,0,.04);z-index:20;}
    .topbar .inner{max-width:1200px;margin:0 auto;padding:12px 16px;display:flex;align-items:center;justify-content:space-between;gap:10px;}
    .back-link{display:inline-flex;align-items:center;gap:8px;text-decoration:none;color:var(--ink);font-weight:600;font-size:.95rem;font-family:Arial,Helvetica,sans-serif !important;}
    .btn-print{background:var(--gold);color:var(--brown) !important;border:0;border-radius:12px;padding:10px 14px;display:inline-flex;gap:8px;align-items:center;cursor:pointer;font-family:Arial,Helvetica,sans-serif !important;font-weight:600;}
    .page{max-width:1200px;margin:14px auto 40px;padding:0 16px;display:flex;justify-content:center;}
    .paper{background:var(--card);width:100%;max-width:320px;border:1px solid var(--line);padding:18px 20px;box-shadow:0 2px 6px rgba(0,0,0,.05);}
    .paper, .paper *{font-family:Arial,Helvetica,sans-serif !important;color:#4b5563 !important;}
    .brand{font-weight:600;font-size:16px;margin-bottom:4px;}
    .meta{display:flex;justify-content:space-between;gap:10px;font-size:.8rem;}
    .to{text-align:right;}
    .rule{border-top:2px dashed #aaa;margin:10px 0;opacity:.8;}
    .head-row{display:flex;justify-content:space-between;font-weight:500;font-size:.8rem;padding:4px 0 6px;}
    .item{display:grid;grid-template-columns:1fr auto;gap:12px;padding:8px 0;}
    .item-name{font-weight:500;font-size:.85rem;}
    .item-sub{font-size:.75rem;margin-top:1px;}
    .item-subtotal{text-align:right;font-weight:500;font-size:.8rem;}
    .row{display:flex;justify-content:space-between;padding:6px 0;font-size:.8rem;}
    .row.total{border-top:2px dotted var(--line);margin-top:4px;padding-top:8px;font-weight:600;font-size:.9rem;}
    .status-line{display:flex;justify-content:space-between;gap:12px;padding-top:8px;font-size:.8rem;}
    .pill{background:transparent !important;border:none !important;font-weight:700 !important;letter-spacing:.18rem;font-size:.75rem;}
    .footer-note{font-size:.7rem;line-height:1.5;}
    @media print{
      @page{size:58mm auto;margin:3mm;}
      html,body{width:58mm;background:#fff !important;margin:0;padding:0;}
      .topbar{display:none !important;}
      .page{margin:0;padding:0;display:block;width:58mm;}
      .paper{width:calc(58mm - 6mm);max-width:calc(58mm - 6mm);border:0;box-shadow:none;padding:6px 6px 10px;}
      .brand{font-size:13px;}
      .meta{font-size:10px;}
      .head-row{font-size:10px;}
      .item-name{font-size:10px;}
      .item-sub{font-size:9px;}
      .item-subtotal{font-size:10px;}
      .row{font-size:10px;}
      .row.total{font-size:11px;}
      .footer-note{font-size:9px;}
    }
  </style>
</head>
<body>
  <div class="topbar">
    <div class="inner">
      <a class="back-link" href="<?= h(BASE_URL) ?>/public/admin/orders.php">
        <i class="bi bi-arrow-left"></i><span>Kembali</span>
      </a>
      <button id="btnPrint" class="btn-print" type="button">
        <i class="bi bi-printer"></i><span>Print</span>
      </button>
    </div>
  </div>

  <div class="page">
    <main class="paper">
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
        <div>Metode Pembayaran: <b><?= h(strtoupper(str_replace('_',' ',$ord['payment_method'] ?? '-'))) ?></b></div>
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
    document.getElementById('btnPrint')?.addEventListener('click', () => window.print());
  </script>
</body>
</html>

<?php 
// public/customer/history.php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../../backend/auth_guard.php';
require_login(['customer']);
require_once __DIR__ . '/../../backend/config.php'; // $conn, BASE_URL, h()

$userId = (int)($_SESSION['user_id'] ?? 0);

function rupiah(float $n): string {
  return 'Rp ' . number_format($n, 0, ',', '.');
}

/* -------- Orders milik user -------- */
$orders = [];
if ($userId > 0) {
  $sql  = "SELECT id, invoice_no, customer_name, service_type, table_no,
                  total, order_status, payment_status, payment_method, created_at
           FROM orders WHERE user_id = ? ORDER BY created_at DESC, id DESC";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $orders = $stmt->get_result()?->fetch_all(MYSQLI_ASSOC) ?? [];
  $stmt->close();
}

/* -------- Items & Invoices (bulk) -------- */
$orderIds     = array_column($orders, 'id');
$itemsByOrder = [];
$invByOrder   = [];

if ($orderIds) {
  $place = implode(',', array_fill(0, count($orderIds), '?'));
  $types = str_repeat('i', count($orderIds));

  // Items
  $sqlI  = "SELECT oi.order_id, oi.menu_id, oi.qty, oi.price,
                   m.name AS menu_name, m.image AS menu_image
            FROM order_items oi
            LEFT JOIN menu m ON m.id=oi.menu_id
            WHERE oi.order_id IN ($place)
            ORDER BY oi.order_id, oi.id";
  $stmtI = $conn->prepare($sqlI);
  $stmtI->bind_param($types, ...$orderIds);
  $stmtI->execute();
  $resI = $stmtI->get_result();
  while ($row = $resI->fetch_assoc()) {
    $itemsByOrder[(int)$row['order_id']][] = $row;
  }
  $stmtI->close();

  // Invoices
  $sqlV  = "SELECT order_id, amount, issued_at
            FROM invoices
            WHERE order_id IN ($place)";
  $stmtV = $conn->prepare($sqlV);
  $stmtV->bind_param($types, ...$orderIds);
  $stmtV->execute();
  $resV = $stmtV->get_result();
  while ($row = $resV->fetch_assoc()) {
    $invByOrder[(int)$row['order_id']] = $row;
  }
  $stmtV->close();
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Riwayat Pesanan — Caffora</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    :root {
      --ink:#2b2b2b;
      --muted:#6b7280;
      --line:#e5e7eb;
      --bg:#ffffff;
      --chip-bg:#f9fafb;
      --radius-card:16px;
      --radius-chip:10px;
    }

    *{
      box-sizing:border-box;
      font-family:Poppins,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;
    }

    body{
      background:var(--bg);
      color:var(--ink);
      -webkit-font-smoothing:antialiased;
    }

    /* TOP BAR */
    .topbar{
      background:#fff;
      border-bottom:1px solid var(--line);
    }
    .topbar-inner{
      max-width:1200px;     /* disamakan dengan cart/checkout */
      margin:0 auto;
      padding:12px 24px;
      display:flex;
      align-items:center;
      min-height:48px;
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


    /* PAGE WRAP */
    .page{
      max-width:1200px;     /* disamakan juga */
      margin:20px auto 32px;
      padding:0 24px;
    }

    /* ORDER CARD */
    .order-card{
      border:1px solid var(--line);
      border-radius:var(--radius-card);
      background:#fff;
      overflow:hidden;
      box-shadow:0 8px 20px rgba(0,0,0,.03);
    }
    .order-card + .order-card{
      margin-top:16px;
    }

    /* HEADER */
    .order-head{
      display:flex;
      flex-wrap:wrap;
      justify-content:space-between;
      align-items:flex-start;
      row-gap:6px;
      padding:16px 16px 12px;
    }
    .head-left{
      font-size:.95rem;
      line-height:1.3;
      font-weight:600;
      color:var(--ink);
    }
    .head-right{
      display:flex;
      align-items:center;
      gap:6px;
      font-size:.8rem;
      line-height:1.2;
      color:var(--muted);
      font-weight:500;
    }

    /* CHIPS */
    .chips{
      display:flex;
      flex-wrap:wrap;
      gap:8px;
      padding:12px 16px;
      background:#fafafa;
    }
    .chip{
      background:var(--chip-bg);
      border:1px solid var(--line);
      border-radius:var(--radius-chip);
      padding:6px 10px;
      font-size:.75rem;
      font-weight:600;
      display:inline-flex;
      align-items:center;
      gap:6px;
      white-space:nowrap;
    }

    .chip--order.pending,
    .chip--pay.pending{
      background:#fff7ed;
      border-color:#fde68a;
      color:#92400e;
    }
    .chip--order.completed,
    .chip--pay.paid{
      background:#ecfdf5;
      border-color:#bbf7d0;
      color:#065f46;
    }
    .chip--order.cancelled,
    .chip--pay.failed{
      background:#fee2e2;
      border-color:#fecaca;
      color:#991b1b;
    }

    /* ITEMS */
    .items-block{
      padding:12px 16px 0;
    }
    .item-row{
      display:flex;
      align-items:flex-start;
      justify-content:space-between;
      gap:12px;
      padding:12px 0;
      border-bottom:1px dashed var(--line);
    }
    .item-left{
      display:flex;
      gap:12px;
      flex:1;
      min-width:0;
    }
    .thumb{
      width:52px;
      height:52px;
      border-radius:10px;
      background:#fff;
      border:1px solid var(--line);
      object-fit:cover;
      flex-shrink:0;
    }
    .item-meta{ min-width:0; }
    .item-name{
      font-weight:600;
      font-size:.9rem;
      color:var(--ink);
      word-break:break-word;
    }
    .item-sub{
      font-size:.8rem;
      color:var(--muted);
    }
    .item-line-total{
      font-weight:600;
      font-size:.9rem;
      color:var(--ink);
      text-align:right;
      min-width:70px;
      white-space:nowrap;
    }

    /* TOGGLE */
    .toggle-wrap{ padding:12px 0; }
    .toggle-btn{
      width:100%;
      border:1px solid var(--line);
      background:#fff;
      border-radius:999px;
      padding:8px 12px;
      font-weight:600;
      font-size:.8rem;
      display:flex;
      align-items:center;
      justify-content:center;
      gap:6px;
    }
    .toggle-btn:hover{ background:#f9fafb; }

    /* FOOTER */
    .order-foot{
      padding:14px 16px 16px;
      background:#fff;
    }
    .inv-summary{
      font-size:.8rem;
      color:var(--muted);
      margin-bottom:12px;
    }
    .inv-summary strong{ color:var(--ink); }

    .foot-bottom{
      display:flex;
      flex-wrap:wrap;
      align-items:center;
      justify-content:space-between;
      row-gap:10px;
    }
    .btn-receipt{
      border:1px solid var(--line);
      background:#fff;
      border-radius:999px;
      padding:7px 12px;
      font-weight:600;
      font-size:.8rem;
      display:inline-flex;
      align-items:center;
      gap:6px;
      text-decoration:none;
      color:var(--ink);
    }
    .btn-receipt:hover{ background:#f9fafb; }
    .total-amount{
      font-weight:600;
      font-size:1rem;
      white-space:nowrap;
    }

    /* MOBILE */
    @media(max-width:600px){
      .topbar-inner,
      .page {
        padding:0 16px;
      }
    }
  </style>
</head>
<body>
  <!-- Topbar -->
  <div class="topbar">
    <div class="topbar-inner">
      <a class="back-link" href="<?= h(BASE_URL) ?>/public/customer/index.php">
        <i class="bi bi-arrow-left"></i>
        <span>Kembali</span>
      </a>
    </div>
  </div>

  <main class="page">
    <?php if (!$orders): ?>
      <div class="alert alert-light border" role="alert" style="font-size:.9rem; line-height:1.4; color:var(--ink);">
        Belum ada pesanan.
      </div>
    <?php else: ?>
      <?php foreach ($orders as $ord):
        $oid   = (int)$ord['id'];
        $items = $itemsByOrder[$oid] ?? [];
        $inv   = $invByOrder[$oid]   ?? null;

        // ikon status pesanan
        $orderIcon = [
          'new'        => 'bi-plus-lg',
          'processing' => 'bi-hourglass-split',
          'ready'      => 'bi-clipboard-check',
          'completed'  => 'bi-check-circle',
          'cancelled'  => 'bi-x-circle',
          'pending'    => 'bi-hourglass-split'
        ][$ord['order_status']] ?? 'bi-receipt';

        // ikon pembayaran
        $payIcon = [
          'pending'  => 'bi-hourglass-split',
          'paid'     => 'bi-check-circle',
          'failed'   => 'bi-x-circle',
          'refunded' => 'bi-arrow-counterclockwise',
          'overdue'  => 'bi-exclamation-triangle'
        ][$ord['payment_status']] ?? 'bi-cash-coin';

        $first = $items[0] ?? null;
        $restCount = max(0, count($items) - 1);
        $createdStr = date('d M Y H:i', strtotime($ord['created_at']));
        $invoiceAmount = $inv['amount'] ?? $ord['total'];
      ?>

      <section class="order-card">
        <!-- HEADER -->
        <div class="order-head">
          <div class="head-left"><?= h($ord['invoice_no']) ?></div>
          <div class="head-right">
            <i class="bi bi-clock"></i>
            <span><?= h($createdStr) ?></span>
          </div>
        </div>

        <!-- STATUS / META -->
        <div class="chips">
          <span class="chip chip--order <?= h($ord['order_status']) ?>">
            <i class="bi <?= $orderIcon ?>"></i>
            <span><?= h($ord['order_status']) ?></span>
          </span>

          <span class="chip chip--pay <?= h($ord['payment_status']) ?>">
            <i class="bi <?= $payIcon ?>"></i>
            <span><?= h($ord['payment_status']) ?></span>
          </span>

          <?php if (!empty($ord['payment_method'])): ?>
            <span class="chip">
              <i class="bi bi-wallet2"></i>
              <span><?= h(strtoupper(str_replace('_',' ',$ord['payment_method']))) ?></span>
            </span>
          <?php endif; ?>

          <span class="chip">
            <i class="bi bi-shop"></i>
            <span><?= h($ord['service_type']==='dine_in'?'Dine In':'Take Away') ?></span>
          </span>

          <?php if (!empty($ord['table_no'])): ?>
            <span class="chip">
              <i class="bi bi-upc-scan"></i>
              <span>Meja <?= h($ord['table_no']) ?></span>
            </span>
          <?php endif; ?>
        </div>

        <!-- ITEMS -->
        <div class="items-block">
          <?php if ($first): ?>
            <div class="item-row">
              <div class="item-left">
                <?php if (!empty($first['menu_image'])): ?>
                  <img class="thumb" src="<?= h(BASE_URL . '/public/' . ltrim($first['menu_image'],'/')) ?>" alt="">
                <?php else: ?>
                  <div class="thumb d-flex align-items-center justify-content-center text-muted">—</div>
                <?php endif; ?>

                <div class="item-meta">
                  <div class="item-name"><?= h($first['menu_name'] ?? 'Menu') ?></div>
                  <div class="item-sub">
                    Qty: <?= (int)$first['qty'] ?> × <?= rupiah((float)$first['price']) ?>
                  </div>
                </div>
              </div>

              <div class="item-line-total">
                <?= rupiah((float)$first['qty'] * (float)$first['price']) ?>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($restCount > 0): ?>
            <div class="collapse" id="items-<?= $oid ?>">
              <?php for ($i=1; $i<count($items); $i++): $it = $items[$i]; ?>
                <div class="item-row">
                  <div class="item-left">
                    <?php if (!empty($it['menu_image'])): ?>
                      <img class="thumb" src="<?= h(BASE_URL . '/public/' . ltrim($it['menu_image'],'/')) ?>" alt="">
                    <?php else: ?>
                      <div class="thumb d-flex align-items-center justify-content-center text-muted">—</div>
                    <?php endif; ?>

                    <div class="item-meta">
                      <div class="item-name"><?= h($it['menu_name'] ?? 'Menu') ?></div>
                      <div class="item-sub">
                        Qty: <?= (int)$it['qty'] ?> × <?= rupiah((float)$it['price']) ?>
                      </div>
                    </div>
                  </div>

                  <div class="item-line-total">
                    <?= rupiah((float)$it['qty'] * (float)$it['price']) ?>
                  </div>
                </div>
              <?php endfor; ?>
            </div>

            <div class="toggle-wrap">
              <button
                class="toggle-btn"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#items-<?= $oid ?>"
                aria-expanded="false"
                aria-controls="items-<?= $oid ?>">
                <i class="bi bi-chevron-down"></i>
                <span class="toggle-text-<?= $oid ?>">Tampilkan <?= $restCount ?> item lainnya</span>
              </button>
            </div>
          <?php endif; ?>
        </div>

        <!-- FOOTER -->
        <div class="order-foot">
          <div class="inv-summary">
            Tagihan <strong><?= rupiah((float)$invoiceAmount) ?></strong>
            <?php if ($ord['payment_status'] === 'paid'): ?>
              • <strong>Lunas</strong>
            <?php endif; ?>
          </div>

          <div class="foot-bottom">
            <a
              class="btn-receipt"
              href="<?= h(BASE_URL) ?>/public/customer/receipt.php?order=<?= $oid ?>">
              <i class="bi bi-receipt"></i>
              <span>Struk</span>
            </a>

            <div class="total-amount"><?= rupiah((float)$ord['total']) ?></div>
          </div>
        </div>
      </section>

      <?php endforeach; ?>
    <?php endif; ?>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Ubah label tombol collapse saat buka/tutup
    document.querySelectorAll('[data-bs-toggle="collapse"]').forEach((btn) => {
      const target = document.querySelector(btn.dataset.bsTarget);
      if (!target) return;

      const oid    = btn.dataset.bsTarget.replace('#items-', '');
      const textEl = document.querySelector('.toggle-text-' + oid);
      const iconEl = btn.querySelector('i');

      if (!textEl || !iconEl) return;

      target.addEventListener('show.bs.collapse', () => {
        textEl.textContent = 'Sembunyikan rincian';
        iconEl.className   = 'bi bi-chevron-up';
      });

      target.addEventListener('hide.bs.collapse', () => {
        const count = target.querySelectorAll('.item-row').length;
        textEl.textContent = 'Tampilkan ' + count + ' item lainnya';
        iconEl.className   = 'bi bi-chevron-down';
      });
    });
  </script>
</body>
</html>

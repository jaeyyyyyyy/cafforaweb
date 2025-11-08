<?php
// public/customer/receipt.php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../../backend/auth_guard.php';
require_login(['customer']);
require_once __DIR__ . '/../../backend/config.php';

function rp(float $n): string { return 'Rp ' . number_format($n, 0, ',', '.'); }

$orderId = (int)($_GET['order'] ?? 0);
$userId  = (int)($_SESSION['user_id'] ?? 0);

$ord = null; $items = []; $inv = null;

if ($orderId && $userId) {
  // order
  $stmt = $conn->prepare("SELECT * FROM orders WHERE id=? AND user_id=? LIMIT 1");
  $stmt->bind_param('ii', $orderId, $userId);
  $stmt->execute();
  $ord = $stmt->get_result()?->fetch_assoc();
  $stmt->close();

  if ($ord) {
    // items
    $stmt = $conn->prepare(
      "SELECT oi.qty, oi.price, m.name AS menu_name
       FROM order_items oi
       LEFT JOIN menu m ON m.id=oi.menu_id
       WHERE oi.order_id=? ORDER BY oi.id"
    );
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $items = $stmt->get_result()?->fetch_all(MYSQLI_ASSOC) ?? [];
    $stmt->close();

    // invoice
    $stmt = $conn->prepare(
      "SELECT amount, issued_at FROM invoices
       WHERE order_id=? LIMIT 1"
    );
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $inv = $stmt->get_result()?->fetch_assoc();
    $stmt->close();
  }
}

if (!$ord) { http_response_code(403); echo 'Tidak boleh mengakses'; exit; }

function head_shared(string $title): void { ?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?= h($title) ?></title>

  <!-- Bootstrap & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    :root{
      --gold:#FFD54F; --brown:#4B3F36; --line:#e5e7eb;
      --ink:#111827;  --muted:#6b7280; --bgc:#fff;
    }
    *{
      box-sizing:border-box;
      font-family:Poppins,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;
    }
    body{ background:#fff; color:var(--ink); -webkit-font-smoothing:antialiased; }

    /* ===== TOP BAR ===== */
    .topbar{ background:#fff; border-bottom:1px solid var(--line); }
    .topbar-inner{
      max-width:1200px; margin:0 auto; padding:12px 24px;
      display:flex; align-items:center; min-height:48px;
    }
    /* mobile: sempitkan padding biar sama dengan history.php */
    @media (max-width:600px){
      .topbar-inner{ padding:12px 16px; }
    }

    .back-link{
      display:inline-flex; align-items:center; gap:10px;
      color:var(--brown); text-decoration:none; font-weight:600;
      font-size:16px; line-height:1.3;
    }
    .back-link .bi{
      font-size:18px !important; width:18px; height:18px; line-height:1;
      display:inline-flex; align-items:center; justify-content:center;
    }

    /* ===== Download button ===== */
    .btn-dl{
      background:var(--gold); color:var(--brown)!important; border:0;
      border-radius:12px; padding:10px 14px; display:inline-flex;
      align-items:center; justify-content:center; gap:8px; cursor:pointer;
      text-decoration:none!important; white-space:nowrap;
      font-family:Arial,sans-serif!important; font-weight:600; font-size:13.3px;
    }
    .btn-dl .bi{ font-size:18px; line-height:1; display:inline-flex; }

    /* ===== layout ===== */
    .wrap{ max-width:1200px; margin:14px auto 40px; padding:0 16px; }
    @media (min-width:768px){ .wrap{ padding:0 24px; } }

    /* ===== card notice (belum lunas) — seperti sebelumnya ===== */
    .card{
      background:var(--bgc);
      border:1px solid rgba(0,0,0,.05);
      border-radius:14px;
      box-shadow:0 6px 18px rgba(0,0,0,.03);
      padding:18px 16px 20px;
      max-width:680px;
      margin:0 auto;
    }
    .muted{ color:var(--muted); }

    /* ===== receipt (lunas) ===== */
    .paper{
      background:#fff; width:100%; max-width:320px; margin:0 auto;
      padding:18px 20px; border:1px solid var(--line);
      box-shadow:0 2px 6px rgba(0,0,0,.05);
    }
    .paper, .paper *{ font-family:Arial,sans-serif!important; color:#4b5563!important; }
    .brand{ font-weight:600; font-size:16px; margin-bottom:4px; }
    .meta{ display:flex; justify-content:space-between; gap:10px; font-size:.8rem; line-height:1.4; }
    .to{ text-align:right; }
    .rule{ border-top:2px dashed #aaa; margin:10px 0; opacity:.8; }
    .head{ display:flex; justify-content:space-between; font-weight:500; padding:4px 0 6px; font-size:.8rem; }
    .item{ display:grid; grid-template-columns:1fr auto; gap:12px; align-items:flex-start; padding:8px 0; }
    .iname{ font-weight:500; font-size:.85rem; }
    .isub{ font-size:.75rem; margin-top:1px; }
    .isubt{ font-weight:500; white-space:nowrap; font-size:.8rem; text-align:right; }
    .row{ display:flex; justify-content:space-between; padding:6px 0; font-size:.8rem; }
    .total{ font-weight:600; font-size:.9rem; border-top:2px dotted var(--line); margin-top:4px; padding-top:8px; }
    .status{ display:flex; justify-content:space-between; gap:12px; padding-top:8px; font-size:.8rem; }
    .pill{ padding:0; border:none; background:transparent; font-weight:700; letter-spacing:.18rem; font-size:.75rem; }
  </style>
</head>
<?php } ?>

<?php
/* ================== BELUM LUNAS ================== */
if (($ord['payment_status'] ?? '') !== 'paid') {
  head_shared('Struk belum tersedia — Caffora'); ?>
<body>
  <div class="topbar">
    <div class="topbar-inner">
      <a class="back-link" href="<?= h(BASE_URL) ?>/public/customer/history.php">
        <i class="bi bi-arrow-left"></i><span>Kembali</span>
      </a>
      <!-- Tidak ada tombol Download di mode belum lunas -->
    </div>
  </div>

  <div class="wrap">
    <section class="card">
      <h2 style="margin:0 0 10px;font-weight:600;font-size:1.05rem">
        Struk belum tersedia
      </h2>
      <p class="muted" style="margin:0 0 10px">
        Struk hanya dapat diunduh setelah pembayaran <b>lunas</b>.
      </p>
      <p style="margin:0">Invoice: <b><?= h($ord['invoice_no']) ?></b></p>
    </section>
  </div>
</body>
</html>
<?php exit; } ?>

z

    </main>
  </div>

  <script>
  (async()=>{
    const btn = document.getElementById('btnDownload');
    await ensureHtml2Canvas();

    btn.addEventListener('click', async () => {
      if (btn.disabled) return;
      const original = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<span style="font-family:Arial">Memproses…</span>';

      try {
        const src = document.getElementById('receiptContent');
        const wrap = document.createElement('div');
        wrap.style.position='fixed';
        wrap.style.left='-9999px';
        wrap.style.background='#fff';
        wrap.style.width='320px';
        wrap.appendChild(src.cloneNode(true));
        document.body.appendChild(wrap);

        const canvas = await html2canvas(wrap, { background:'#fff', scale:2 });
        document.body.removeChild(wrap);

        canvas.toBlob(blob => {
          if (!blob) return alert('Gagal membuat berkas.');
          const url = URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          a.download = 'Struk_<?= h($ord['invoice_no']) ?>.png';
          a.click();
          URL.revokeObjectURL(url);
        }, 'image/png', 1.0);
      } catch {
        alert('Gagal unduh struk');
      } finally {
        btn.disabled = false;
        btn.innerHTML = original;
      }
    });
  })();
  </script>
</body>
</html>

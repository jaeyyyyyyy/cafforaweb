<?php
// backend/checkout_place_order.php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_guard.php';
require_login(['customer']); // hanya customer yang boleh place order
header('Content-Type: application/json; charset=utf-8');

/* ====== Audit helper (pakai lib bila ada, fallback inline) ====== */
$__auditHelper = __DIR__ . '/lib/audit_helper.php';
if (is_file($__auditHelper)) {
  require_once $__auditHelper;
}
if (!function_exists('audit_log')) {
  /**
   * Minimal helper untuk tabel audit_logs
   */
  function audit_log(
    mysqli $db,
    int    $actorId,
    string $entity,       // 'order' | 'payment' | 'menu' | 'user'
    int    $entityId,
    string $action,       // 'create' | 'cancel' | 'paid' | ...
    string $fromVal = '',
    string $toVal   = '',
    string $remark  = ''
  ): bool {
    // batasi panjang remark agar aman
    if (mb_strlen($remark) > 255) $remark = mb_substr($remark, 0, 255);

    $sql  = "INSERT INTO audit_logs (actor_id, entity, entity_id, action, from_val, to_val, remark)
             VALUES (?,?,?,?,?,?,?)";
    $stmt = $db->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param('isissss', $actorId, $entity, $entityId, $action, $fromVal, $toVal, $remark);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
  }
}

try {
  $actorId        = (int)($_SESSION['user_id'] ?? 0);
  $customer_name  = trim((string)($_POST['customer_name'] ?? ''));
  $service_type   = trim((string)($_POST['service_type'] ?? 'dine_in')); // dine_in|takeaway|delivery
  $table_no       = trim((string)($_POST['table_no'] ?? ''));
  $payment_method = trim((string)($_POST['payment_method'] ?? 'cash'));

  // Keranjang dari session
  $cart = $_SESSION['cart'] ?? [];
  if (!$cart) throw new Exception('Keranjang kosong.');

  // Generate invoice incremental sederhana
  $res  = $conn->query("SELECT invoice_no FROM orders ORDER BY id DESC LIMIT 1");
  $last = $res?->fetch_assoc()['invoice_no'] ?? null;
  $n    = 1;
  if ($last && preg_match('~(\d+)~', $last, $m)) $n = (int)$m[1] + 1;
  $invoice_no = sprintf('INV-%03d', $n);

  $conn->begin_transaction();

  // Hitung total dari DB (anti manipulasi harga)
  $grand = 0;
  $items = [];
  $stmtM = $conn->prepare("SELECT id, name, price FROM menu WHERE id=?");
  foreach ($cart as $c) {
    $menu_id = (int)($c['menu_id'] ?? 0);
    $qty     = max(1, (int)($c['qty'] ?? 1));
    $stmtM->bind_param('i', $menu_id);
    $stmtM->execute();
    $r = $stmtM->get_result()->fetch_assoc();
    if (!$r) throw new Exception("Menu $menu_id tidak ditemukan.");
    $price = (int)$r['price'];
    $sub   = $price * $qty;
    $grand += $sub;
    $items[] = [
      'menu_id'  => $menu_id,
      'name'     => (string)$r['name'],
      'qty'      => $qty,
      'price'    => $price,
      'subtotal' => $sub
    ];
  }
  $stmtM->close();

  // Simpan orders
  $order_status   = 'new';
  $payment_status = 'pending';

  $stmtO = $conn->prepare("INSERT INTO orders
    (invoice_no, customer_name, service_type, table_no, total, payment_method, order_status, payment_status, created_at)
    VALUES (?,?,?,?,?,?,?,?, NOW())");
  $stmtO->bind_param(
    'ssssisss',
    $invoice_no,
    $customer_name,
    $service_type,
    $table_no,
    $grand,
    $payment_method,
    $order_status,
    $payment_status
  );
  if (!$stmtO->execute()) throw new Exception('Gagal menyimpan pesanan.');
  $order_id = (int)$conn->insert_id;
  $stmtO->close();

  // Simpan order_items
  $stmtI = $conn->prepare("INSERT INTO order_items (order_id, menu_id, qty, price, subtotal) VALUES (?,?,?,?,?)");
  foreach ($items as $it) {
    $stmtI->bind_param('iiiii', $order_id, $it['menu_id'], $it['qty'], $it['price'], $it['subtotal']);
    if (!$stmtI->execute()) throw new Exception('Gagal menyimpan item.');
  }
  $stmtI->close();

  /* ===== AUDIT: hanya event penting — ORDER CREATED ===== */
  $toVal = json_encode([
    'invoice'        => $invoice_no,
    'customer'       => $customer_name,
    'service_type'   => $service_type,
    'table_no'       => $table_no,
    'payment_method' => $payment_method,
    'total'          => $grand,
    'items_count'    => count($items)
  ], JSON_UNESCAPED_UNICODE);

  audit_log($conn, $actorId, 'order', $order_id, 'create', '', $toVal, 'place order');

  // Catatan: TIDAK mencatat "payment intent/pending" agar audit bersih.
  // Nanti saat pembayaran sukses, endpoint pembayaran akan mencatat 'paid'.

  // Kosongkan keranjang
  unset($_SESSION['cart']);

  $conn->commit();
  echo json_encode([
    'ok'       => true,
    'invoice'  => $invoice_no,
    'redirect' => BASE_URL . '/public/customer/history.php'
  ]);
} catch (Throwable $e) {
  if ($conn && $conn->errno) { $conn->rollback(); }
  http_response_code(400);
  echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
}

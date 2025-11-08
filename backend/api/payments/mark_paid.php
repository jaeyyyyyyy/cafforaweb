<?php
require_once __DIR__.'/../../config.php';
require_admin_or_staff(); // cek role

$orderId = (int)($_POST['order_id'] ?? 0);
if ($orderId<=0) json_error('order_id invalid');

$conn->begin_transaction();

# lock payment terbaru
$pay = $conn->query("
  SELECT id, status FROM payments
  WHERE order_id={$orderId}
  ORDER BY created_at DESC
  LIMIT 1 FOR UPDATE
")->fetch_assoc();

if (!$pay) {
  // belum ada -> buat pending dulu
  $conn->query("
    INSERT INTO payments(order_id, method, status, amount_gross)
    SELECT id, COALESCE(NULLIF(payment_method,''),'cash'), 'pending', total
    FROM orders WHERE id={$orderId}
  ");
  $payId = $conn->insert_id;
} else {
  $payId = (int)$pay['id'];
}

# set paid
$conn->query("UPDATE payments SET status='paid', paid_at=NOW() WHERE id={$payId}");
# sinkron ke orders (opsional sebagai cache)
$conn->query("UPDATE orders SET payment_status='paid' WHERE id={$orderId}");

$conn->commit();
json_ok(['paid'=>true]);

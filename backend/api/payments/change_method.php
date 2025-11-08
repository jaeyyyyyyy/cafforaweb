<?php
require_once __DIR__.'/../../config.php';
require_admin_or_staff();

$orderId = (int)($_POST['order_id'] ?? 0);
$method  = $_POST['method'] ?? 'cash'; // 'cash','bank_transfer','qris','ewallet'
if (!in_array($method, ['cash','bank_transfer','qris','ewallet'], true)) json_error('metode invalid');

$conn->begin_transaction();

# payment terbaru
$pay = $conn->query("
  SELECT id, status FROM payments
  WHERE order_id={$orderId}
  ORDER BY created_at DESC
  LIMIT 1 FOR UPDATE
")->fetch_assoc();

if ($pay && $pay['status']==='pending') {
  // update pending yang ada
  $stmt = $conn->prepare("UPDATE payments SET method=? WHERE id=?");
  $stmt->bind_param('si', $method, $pay['id']);
  $stmt->execute();
} else {
  // buat attempt baru (pending)
  $stmt = $conn->prepare("
    INSERT INTO payments(order_id, method, status, amount_gross)
    SELECT id, ?, 'pending', total FROM orders WHERE id=?
  ");
  $stmt->bind_param('si', $method, $orderId);
  $stmt->execute();
}

# cache ke orders (opsional)
$stmt = $conn->prepare("UPDATE orders SET payment_method=? WHERE id=?");
$stmt->bind_param('si', $method, $orderId);
$stmt->execute();

$conn->commit();
json_ok(['updated'=>true]);

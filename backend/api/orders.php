<?php
// backend/api/orders.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
session_start();

require_once __DIR__ . '/../config.php';

$BASE = rtrim(BASE_URL, '/');

/* ==== AUDIT HELPER (robust loader) ==== */
$__candidates = [
  __DIR__ . '/../lib/audit_helper.php',  // lokasi normal
  __DIR__ . '/lib/audit_helper.php',     // fallback jika helper diletakkan di /api/lib
];
foreach ($__candidates as $__p) {
  if (is_file($__p)) { require_once $__p; break; }
}
if (!function_exists('audit_log')) {
  // fallback aman bila helper belum ada agar API tidak fatal
  function audit_log(mysqli $db, int $actorId, string $entity, int $entityId, string $action, string $fromVal = '', string $toVal = '', string $remark = ''): bool {
    error_log("[AUDIT_FALLBACK] $actorId $entity#$entityId $action ($remark)");
    return true;
  }
}

$mysqli = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_errno) {
  echo json_encode(['ok'=>false,'error'=>'DB connect failed: '.$mysqli->connect_error], JSON_UNESCAPED_SLASHES);
  exit;
}
$mysqli->set_charset('utf8mb4');

/* -------------------- helpers -------------------- */
function out(array $arr): void { echo json_encode($arr, JSON_UNESCAPED_SLASHES); exit; }
function bad(string $msg, int $code=400): void { http_response_code($code); out(['ok'=>false,'error'=>$msg]); }

/** Normalisasi role ke 3 varian resmi */
function norm_role(?string $r): ?string {
  if ($r === null) return null;
  $r = strtolower(trim($r));
  if ($r === 'admin') return 'admin';
  if (in_array($r, ['karyawan','pegawai','employee','staff','barista'], true)) return 'karyawan';
  if (in_array($r, ['customer','pelanggan'], true)) return 'customer';
  return 'customer'; // fallback aman
}

/** Simpan notifikasi (tidak dicatat ke audit) */
function create_notification(mysqli $db, ?int $userId, ?string $role, string $message, ?string $link = null): void {
  $role = norm_role($role);
  $roleParam = $role ?? '';
  $uid = $userId ?? 0;
  $sql = "INSERT INTO notifications (user_id, role, message, status, created_at, link)
          VALUES (NULLIF(?,0), NULLIF(?,''), ?, 'unread', NOW(), ?)";
  if (!$stmt = $db->prepare($sql)) return;
  $stmt->bind_param('isss', $uid, $roleParam, $message, $link);
  $stmt->execute();
  $stmt->close();
}

/** CFR + tanggal + bulan (3 huruf) + kode jam + random */
function generateInvoiceNo(): string {
  $prefix   = 'CFR';
  $day      = strtoupper(date('d'));
  $mon      = strtoupper(date('M'));
  $hourCode = chr(65 + (int)date('G')); // 0->A, 1->B, ...
  $rand     = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
  return $prefix.$day.$mon.$hourCode.$rand;
}

/* -------------------- const & enums -------------------- */
$ALLOWED_ORDER_STATUS   = ['new','processing','ready','completed','cancelled'];
$ALLOWED_PAYMENT_STATUS = ['pending','paid','failed','refunded','overdue'];
$ALLOWED_METHOD         = ['cash','bank_transfer','qris','ewallet'];
$ALLOWED_SERVICE        = ['dine_in','take_away'];
const TAX_RATE = 0.11; // 11%

$action  = $_GET['action'] ?? 'list';
$actorId = (int)($_SESSION['user_id'] ?? 0); // dipakai untuk audit

/* ======================================================
   LIST
====================================================== */
if ($action === 'list') {
  $q              = trim((string)($_GET['q'] ?? ''));
  $order_status   = trim((string)($_GET['order_status'] ?? ''));
  $payment_status = trim((string)($_GET['payment_status'] ?? ''));

  $where=[]; $types=''; $params=[];
  if ($q !== '') {
    $where[]='(invoice_no LIKE ? OR customer_name LIKE ?)';
    $like='%'.$q.'%'; $params[]=$like; $params[]=$like; $types.='ss';
  }
  if ($order_status !== '') {
    if (!in_array($order_status, $ALLOWED_ORDER_STATUS, true)) bad('Invalid order_status');
    $where[]='order_status=?'; $params[]=$order_status; $types.='s';
  }
  if ($payment_status !== '') {
    if (!in_array($payment_status, $ALLOWED_PAYMENT_STATUS, true)) bad('Invalid payment_status');
    $where[]='payment_status=?'; $params[]=$payment_status; $types.='s';
  }

  $sql="SELECT id,user_id,invoice_no,customer_name,service_type,table_no,
               total,subtotal,tax_amount,grand_total,
               order_status,payment_status,payment_method,
               created_at,updated_at
        FROM orders";
  if ($where) $sql.=' WHERE '.implode(' AND ',$where);
  $sql.=' ORDER BY created_at DESC, id DESC';

  if ($params) {
    $stmt=$mysqli->prepare($sql); if(!$stmt) bad('Prepare failed: '.$mysqli->error,500);
    $stmt->bind_param($types, ...$params); $stmt->execute();
    $res=$stmt->get_result();
  } else {
    $res=$mysqli->query($sql); if(!$res) bad('Query failed: '.$mysqli->error,500);
  }

  $items=[];
  while($row=$res->fetch_assoc()){
    $row['id']          = (int)$row['id'];
    $row['user_id']     = $row['user_id'] !== null ? (int)$row['user_id'] : null;
    $row['total']       = (float)$row['total'];
    $row['subtotal']    = (int)$row['subtotal'];
    $row['tax_amount']  = (int)$row['tax_amount'];
    $row['grand_total'] = (int)$row['grand_total'];
    $items[]=$row;
  }
  out(['ok'=>true,'items'=>$items]);
}

/* ======================================================
   CREATE (checkout)
====================================================== */
if ($action === 'create') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') bad('Use POST', 405);

  $data = json_decode(file_get_contents('php://input'), true);
  if (!is_array($data)) bad('Invalid JSON body');

  $customer_name = trim((string)($data['customer_name'] ?? ''));
  $service_type  = (string)($data['service_type'] ?? '');
  $table_no      = trim((string)($data['table_no'] ?? ''));
  $pay_method    = (string)($data['payment_method'] ?? '');
  $pay_status    = (string)($data['payment_status'] ?? 'pending');
  $items         = $data['items'] ?? [];

  if ($customer_name === '') bad('Nama customer wajib');
  if (!in_array($service_type, $ALLOWED_SERVICE, true)) bad('Invalid service_type');

  if ($pay_method === '') $pay_method = 'cash';
  if (!in_array($pay_method, $ALLOWED_METHOD, true)) bad('Invalid payment_method');

  if (!in_array($pay_status, $ALLOWED_PAYMENT_STATUS, true)) bad('Invalid payment_status');
  if (!is_array($items) || !count($items)) bad('Items kosong');

  // hitung harga
  $subtotal = 0;
  foreach ($items as $it) {
    $qty = (int)($it['qty'] ?? 0);
    $price = (float)($it['price'] ?? 0);
    if ($qty <= 0 || $price < 0) continue;
    $subtotal += $qty * $price;
  }
  if ($subtotal <= 0) bad('Total invalid');

  $tax_amount   = (int) round($subtotal * TAX_RATE);
  $grand_total  = $subtotal + $tax_amount;
  $total_legacy = $grand_total;

  // untuk payments
  $amount_gross = $grand_total;
  $amount_net   = $grand_total;
  $paid_at      = ($pay_status === 'paid') ? date('Y-m-d H:i:s') : null;

  $invoice_no   = generateInvoiceNo();
  $user_id      = $_SESSION['user_id'] ?? null;

  if ($service_type === 'take_away') $table_no = '';

  $mysqli->begin_transaction();
  try {
    // 1) orders
    $stmt = $mysqli->prepare("
      INSERT INTO orders
        (user_id, invoice_no, customer_name, service_type, table_no,
         total, order_status, payment_status, payment_method,
         created_at, updated_at, subtotal, tax_amount, grand_total)
      VALUES
        (?, ?, ?, ?, ?, ?, 'new', ?, ?, NOW(), NOW(), ?, ?, ?)
    ");
    if (!$stmt) throw new Exception('Prepare(order) failed: '.$mysqli->error);

    $stmt->bind_param(
      'issssdssiii',
      $user_id,
      $invoice_no,
      $customer_name,
      $service_type,
      $table_no,
      $total_legacy,
      $pay_status,
      $pay_method,
      $subtotal,
      $tax_amount,
      $grand_total
    );
    if (!$stmt->execute()) throw new Exception('Insert order failed: '.$stmt->error);
    $order_id = (int)$stmt->insert_id;
    $stmt->close();

    // 2) order_items
    $stmtItem = $mysqli->prepare("
      INSERT INTO order_items (order_id, menu_id, qty, price, discount, cogs_unit)
      VALUES (?, ?, ?, ?, 0.00, NULL)
    ");
    if (!$stmtItem) throw new Exception('Prepare(item) failed: '.$mysqli->error);
    foreach ($items as $it) {
      $menu_id = (int)($it['menu_id'] ?? $it['id'] ?? 0);
      $qty     = (int)($it['qty'] ?? 0);
      $price   = (float)($it['price'] ?? 0);
      if ($menu_id <= 0 || $qty <= 0) continue;
      $stmtItem->bind_param('iiid', $order_id, $menu_id, $qty, $price);
      if (!$stmtItem->execute()) throw new Exception('Insert item failed: '.$stmtItem->error);
    }
    $stmtItem->close();

    // 3) invoices (opsional)
    $stmtInv = $mysqli->prepare("INSERT INTO invoices (order_id, amount) VALUES (?, ?)");
    if (!$stmtInv) throw new Exception('Prepare(invoice) failed: '.$mysqli->error);
    $stmtInv->bind_param('id', $order_id, $grand_total);
    if (!$stmtInv->execute()) throw new Exception('Insert invoice failed: '.$stmtInv->error);
    $stmtInv->close();

    // 4) payments (AUTO)
    $stmtPay = $mysqli->prepare("
      INSERT INTO payments
        (order_id, method, status, amount_gross, discount, tax, shipping, amount_net, paid_at, note)
      VALUES
        (?, ?, ?, ?, 0, ?, 0, ?, ?, ?)
      ON DUPLICATE KEY UPDATE
        method       = VALUES(method),
        status       = VALUES(status),
        amount_gross = VALUES(amount_gross),
        tax          = VALUES(tax),
        amount_net   = VALUES(amount_net),
        paid_at      = VALUES(paid_at),
        note         = VALUES(note)
    ");
    if (!$stmtPay) throw new Exception('Prepare(payment) failed: '.$mysqli->error);
    $note = 'auto import from orders #'.$invoice_no;
    $stmtPay->bind_param(
      'issdddss',
      $order_id,
      $pay_method,
      $pay_status,
      $amount_gross,
      $tax_amount,
      $amount_net,
      $paid_at,
      $note
    );
    if (!$stmtPay->execute()) throw new Exception('Insert payment failed: '.$stmtPay->error);
    $stmtPay->close();

    /* ===== AUDIT (FOKUS: order & uang) ===== */
    // order.create (ringkas, non-teknis)
    $toOrder = json_encode([
      'invoice'        => $invoice_no,
      'customer'       => $customer_name,
      'service_type'   => $service_type,
      'table_no'       => $table_no,
      'payment_method' => $pay_method,
      'subtotal'       => $subtotal,
      'tax'            => $tax_amount,
      'grand_total'    => $grand_total,
      'items_count'    => count($items)
    ], JSON_UNESCAPED_UNICODE);
    audit_log($mysqli, $actorId, 'order', $order_id, 'create', '', $toOrder, 'create via API');

    // catat uang hanya saat langsung LUNAS
    if ($pay_status === 'paid') {
      audit_log($mysqli, $actorId, 'payment', $order_id, 'update_status', 'pending', 'paid', 'paid at create');
    }

    // 5) NOTIFIKASI (tidak dicatat ke audit)
    if ($user_id) {
      $msg  = 'Pesanan kamu sudah diterima. Invoice: '.$invoice_no;
      $link = $BASE.'/public/customer/history.php?invoice='.$invoice_no;
      create_notification($mysqli, (int)$user_id, 'customer', $msg, $link);
    }
    $msgStaff  = 'Pesanan baru dari '.$customer_name.' total Rp '.number_format($grand_total,0,',','.').' ('.$invoice_no.')';
    create_notification($mysqli, null, 'karyawan', $msgStaff, $BASE.'/public/karyawan/orders.php');
    $msgAdmin  = '[ADMIN] Pesanan baru: '.$customer_name.' — Rp '.number_format($grand_total,0,',','.').' ('.$invoice_no.')';
    create_notification($mysqli, null, 'admin', $msgAdmin, $BASE.'/public/admin/orders.php');

    $mysqli->commit();
    out([
      'ok'=>true,'id'=>$order_id,'invoice_no'=>$invoice_no,
      'subtotal'=>$subtotal,'tax_amount'=>$tax_amount,'grand_total'=>$grand_total,
      'payment_method'=>$pay_method
    ]);
  } catch (Throwable $e) {
    $mysqli->rollback();
    bad($e->getMessage(), 500);
  }
}

/* ======================================================
   UPDATE (dipakai karyawan/admin: next status, bayar, metode, cancel)
====================================================== */
if ($action === 'update') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') bad('Use POST', 405);
  $data = json_decode(file_get_contents('php://input'), true);
  if (!is_array($data)) bad('Invalid JSON');

  $id = (int)($data['id'] ?? 0);
  if ($id <= 0) bad('Missing id');

  // baca order sekarang
  $stmt = $mysqli->prepare("
    SELECT id, user_id, invoice_no, customer_name,
           grand_total, tax_amount, payment_method, payment_status, order_status
    FROM orders WHERE id=? LIMIT 1
  ");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $order = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$order) bad('Order not found', 404);

  $oldOrderStatus = (string)$order['order_status'];
  $oldPayStatus   = (string)$order['payment_status'];
  $userIdOrder    = $order['user_id'] !== null ? (int)$order['user_id'] : null;
  $invoiceNo      = (string)$order['invoice_no'];
  $customerName   = (string)$order['customer_name'];

  $fields=[]; $params=[]; $types='';
  $newOrderStatus=null; $newPayStatus=null; $newPayMethod=null; $cancelReason=null;

  // order_status
  if (isset($data['order_status'])) {
    $val = (string)$data['order_status'];
    if (!in_array($val, $GLOBALS['ALLOWED_ORDER_STATUS'], true)) bad('Invalid order_status');
    if ($val === 'processing' && $oldPayStatus !== 'paid') bad('Pembayaran belum Lunas. Set ke paid dulu.');
    if ($val === 'cancelled'  && $oldPayStatus !== 'pending') bad('Pembatalan hanya untuk pesanan yang belum dibayar.');

    $fields[]="order_status=?"; $params[]=$val; $types.='s'; $newOrderStatus=$val;

    if ($val === 'cancelled') {
      $cancelReason = trim((string)($data['cancel_reason'] ?? ''));
      if ($cancelReason !== '') { $fields[]="cancel_reason=?"; $params[]=$cancelReason; $types.='s'; }
      $fields[]="canceled_by_id=?"; $params[]=(int)($_SESSION['user_id'] ?? 0); $types.='i';
      $fields[]="canceled_at=NOW()";
    }
  }

  // payment_status
  if (isset($data['payment_status'])) {
    $val = (string)$data['payment_status'];
    if (!in_array($val, $GLOBALS['ALLOWED_PAYMENT_STATUS'], true)) bad('Invalid payment_status');
    if ($oldPayStatus === 'paid' && $val === 'pending') bad('Pembayaran sudah Lunas dan tidak bisa kembali ke Pending.');

    $fields[]="payment_status=?"; $params[]=$val; $types.='s'; $newPayStatus=$val;
  }

  // payment_method
  if (array_key_exists('payment_method', $data)) {
    $val = $data['payment_method'];
    if ($val !== null && $val !== '') {
      if (!in_array($val, $GLOBALS['ALLOWED_METHOD'], true)) bad('Invalid payment_method');
      $fields[]="payment_method=?"; $params[]=$val; $types.='s'; $newPayMethod=$val;
    } else {
      $fields[]="payment_method=NULL";
    }
  }

  if (!$fields) bad('No fields to update');

  // update orders
  $sql="UPDATE orders SET ".implode(', ',$fields).", updated_at=NOW() WHERE id=?";
  $params[]=$id; $types.='i';
  $stmt=$mysqli->prepare($sql); if(!$stmt) bad('Prepare failed: '.$mysqli->error,500);
  $stmt->bind_param($types, ...$params);
  if(!$stmt->execute()) bad('Update failed: '.$stmt->error,500);
  $stmt->close();

  // ===== sinkron ke payments
  $grand=(float)$order['grand_total'];
  $tax  =(float)$order['tax_amount'];
  $pm   =$newPayMethod ?? $order['payment_method'] ?? 'cash';
  $ps   =$newPayStatus ?? $order['payment_status'] ?? 'pending';
  $os   =$newOrderStatus ?? $order['order_status'];

  $stmt=$mysqli->prepare("SELECT id, status FROM payments WHERE order_id=? LIMIT 1");
  $stmt->bind_param('i',$id);
  $stmt->execute();
  $payRow=$stmt->get_result()->fetch_assoc();
  $stmt->close();

  if ($os === 'cancelled') {
    // payments -> failed
    $noteCancel='cancelled: '.($cancelReason ?: '-').' | from orders #'.$invoiceNo;
    if ($payRow) {
      $stmt=$mysqli->prepare("UPDATE payments SET status='failed', amount_gross=0, discount=0, tax=0, shipping=0, amount_net=0, paid_at=NULL, note=?, method=? WHERE order_id=?");
      $stmt->bind_param('ssi',$noteCancel,$pm,$id); $stmt->execute(); $stmt->close();
    } else {
      $stmt=$mysqli->prepare("INSERT INTO payments (order_id, method, status, amount_gross, discount, tax, shipping, amount_net, paid_at, note) VALUES (?,?, 'failed', 0,0,0,0,0, NULL, ?)");
      $stmt->bind_param('iss',$id,$pm,$noteCancel); $stmt->execute(); $stmt->close();
    }
  } else {
    // resync normal
    if ($payRow) {
      $stmt=$mysqli->prepare("UPDATE payments SET method=?, status=?, amount_gross=?, tax=?, amount_net=?, paid_at = CASE WHEN ?='paid' THEN NOW() ELSE paid_at END, note=CONCAT('auto resync from orders #', ?) WHERE order_id=?");
      $stmt->bind_param('ssdddssi',$pm,$ps,$grand,$tax,$grand,$ps,$invoiceNo,$id); $stmt->execute(); $stmt->close();
    } else {
      $stmt=$mysqli->prepare("INSERT INTO payments (order_id, method, status, amount_gross, discount, tax, shipping, amount_net, paid_at, note) VALUES (?,?,?, ?,0, ?,0, ?, CASE WHEN ?='paid' THEN NOW() ELSE NULL END, CONCAT('auto resync from orders #', ?))");
      $stmt->bind_param('issdddsss',$id,$pm,$ps,$grand,$tax,$grand,$ps,$invoiceNo); $stmt->execute(); $stmt->close();
    }
  }

  /* ===== AUDIT (FOKUS: order & payment) ===== */
  // 1) order_status berubah
  if ($newOrderStatus !== null && $newOrderStatus !== $oldOrderStatus) {
    $from = json_encode(['order_status'=>$oldOrderStatus], JSON_UNESCAPED_UNICODE);
    $to   = json_encode(['order_status'=>$newOrderStatus] + ($newOrderStatus==='cancelled' ? ['reason'=>$cancelReason] : []), JSON_UNESCAPED_UNICODE);
    audit_log($mysqli, $actorId, 'order', $id, 'update_status', $from, $to, 'order status changed');
  }
  // 2) payment_status berubah (uang)
  if ($newPayStatus !== null && $newPayStatus !== $oldPayStatus) {
    audit_log($mysqli, $actorId, 'payment', $id, 'update_status', $oldPayStatus, $newPayStatus, 'payment status changed');
  }

  /* ===== NOTIF (tetap, TIDAK diaudit) ===== */
  if ($os === 'cancelled') {
    create_notification($mysqli, null, 'karyawan', 'Pesanan dibatalkan: '.$customerName.' ('.$invoiceNo.')'.($cancelReason?' — '.$cancelReason:''), $BASE.'/public/karyawan/orders.php');
    create_notification($mysqli, null, 'admin',    '[ADMIN] Pesanan dibatalkan: '.$customerName.' ('.$invoiceNo.')'.($cancelReason?' — '.$cancelReason:''), $BASE.'/public/admin/orders.php');
  } else {
    if ($newPayStatus === 'paid' && $oldPayStatus !== 'paid') {
      $msgPaid = 'Pembayaran LUNAS untuk '.$customerName.' ('.$invoiceNo.').';
      create_notification($mysqli, null, 'karyawan', $msgPaid, $BASE.'/public/karyawan/orders.php');
      create_notification($mysqli, null, 'admin',    '[ADMIN] '.$msgPaid, $BASE.'/public/admin/orders.php');
    }
    if ($newOrderStatus !== null && $newOrderStatus !== $oldOrderStatus) {
      $msgFlow = 'Status pesanan '.$invoiceNo.' → '.$newOrderStatus.'.';
      create_notification($mysqli, null, 'karyawan', $msgFlow, $BASE.'/public/karyawan/orders.php');
    }
  }

  // notif customer
  if ($userIdOrder && $newOrderStatus !== null && $newOrderStatus !== $oldOrderStatus) {
    $historyLink = $BASE.'/public/customer/history.php?invoice='.$invoiceNo;
    if     ($newOrderStatus === 'ready')
      create_notification($mysqli, $userIdOrder, 'customer', 'Pesanan kamu sudah ready ('.$invoiceNo.').', $historyLink);
    elseif ($newOrderStatus === 'completed')
      create_notification($mysqli, $userIdOrder, 'customer', 'Pesanan kamu sudah selesai ('.$invoiceNo.'). Terima kasih.', $historyLink);
    elseif ($newOrderStatus === 'cancelled')
      create_notification($mysqli, $userIdOrder, 'customer', 'Pesanan kamu dibatalkan ('.$invoiceNo.').'.($cancelReason ? ' Alasan: '.$cancelReason : ''), $historyLink);
  }

  out(['ok'=>true]);
}

/* ======================================================
   DEFAULT
====================================================== */
bad('Invalid action', 404);

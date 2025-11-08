<?php
// backend/api/finance.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php'; // ini harusnya sudah ada $conn (mysqli)

// kalau kamu pakai session guard, bisa aktifkan ini:
// session_start();
// if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
//   http_response_code(403);
//   echo json_encode(['ok' => false, 'error' => 'forbidden']);
//   exit;
// }

/**
 * helper buat respon error
 */
function send_error(string $msg, int $code = 400): void {
  http_response_code($code);
  echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
  exit;
}

if (!isset($conn) || $conn->connect_errno) {
  send_error('db not connected', 500);
}

/* ============================================================
   BACA PARAMETER RANGE
   ============================================================ */
$range = $_GET['range'] ?? '7d';

$today   = new DateTime('today');
$endDate = clone $today;
$startDate = clone $today;

if ($range === 'today') {
  // dari pagi sampai malam hari ini
  $startDate = clone $today;
} elseif ($range === '30d') {
  $startDate->modify('-29 day');
} elseif ($range === 'custom') {
  $from = $_GET['from'] ?? '';
  $to   = $_GET['to']   ?? '';
  if ($from && $to) {
    $tmpStart = DateTime::createFromFormat('Y-m-d', $from);
    $tmpEnd   = DateTime::createFromFormat('Y-m-d', $to);
    if ($tmpStart && $tmpEnd) {
      $startDate = $tmpStart;
      $endDate   = $tmpEnd;
    } else {
      // kalau format salah fallback ke 7 hari
      $startDate->modify('-6 day');
      $range = '7d';
    }
  } else {
    // ga dikirim from/to → fallback 7 hari
    $startDate->modify('-6 day');
    $range = '7d';
  }
} else {
  // default 7 hari
  $startDate->modify('-6 day');
}

$startStr = $startDate->format('Y-m-d 00:00:00');
$endStr   = $endDate->format('Y-m-d 23:59:59');

/* ============================================================
   BIKIN LABEL TANGGAL
   ============================================================ */
$labels = [];
$mapRevenue = [];

$period = new DatePeriod($startDate, new DateInterval('P1D'), (clone $endDate)->modify('+1 day'));
foreach ($period as $d) {
  $key = $d->format('Y-m-d');
  $labels[] = $key;
  $mapRevenue[$key] = 0.0;
}

/* ============================================================
   QUERY REVENUE DARI ORDERS
   hanya payment_status = 'paid'
   ============================================================ */
$sql = "
  SELECT DATE(created_at) AS d, SUM(total) AS s
  FROM orders
  WHERE created_at BETWEEN ? AND ?
    AND payment_status = 'paid'
  GROUP BY DATE(created_at)
  ORDER BY d ASC
";
$stmt = $conn->prepare($sql);
if (!$stmt) {
  send_error('failed to prepare revenue query: '.$conn->error, 500);
}
$stmt->bind_param('ss', $startStr, $endStr);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
  $mapRevenue[$row['d']] = (float)$row['s'];
}
$stmt->close();

/* susun ulang sesuai urutan labels */
$revenue = [];
foreach ($labels as $d) {
  $revenue[] = $mapRevenue[$d] ?? 0.0;
}
$totalRevenue = array_sum($revenue);

/* ============================================================
   TOTAL ORDER PAID
   ============================================================ */
$stmt2 = $conn->prepare("
  SELECT COUNT(*) AS c 
  FROM orders 
  WHERE created_at BETWEEN ? AND ? 
    AND payment_status='paid'
");
$stmt2->bind_param('ss', $startStr, $endStr);
$stmt2->execute();
$res2 = $stmt2->get_result();
$row2 = $res2->fetch_assoc();
$ordersPaid = (int)($row2['c'] ?? 0);
$stmt2->close();

$avgOrder = $ordersPaid > 0 ? ($totalRevenue / $ordersPaid) : 0;

/* ============================================================
   TOP MENU TERLARIS
   ============================================================ */
$topMenus = [];
$sqlTop = "
  SELECT 
    m.id,
    m.name,
    m.image,
    COALESCE(SUM(oi.qty),0)       AS sold_qty,
    COALESCE(SUM(oi.subtotal),0)  AS sold_amount
  FROM order_items oi
  INNER JOIN orders o ON o.id = oi.order_id
  INNER JOIN menu   m ON m.id = oi.menu_id
  WHERE o.created_at BETWEEN ? AND ?
    AND o.payment_status = 'paid'
  GROUP BY m.id, m.name, m.image
  ORDER BY sold_qty DESC, sold_amount DESC
  LIMIT 10
";
$stmt3 = $conn->prepare($sqlTop);
$stmt3->bind_param('ss', $startStr, $endStr);
$stmt3->execute();
$resTop = $stmt3->get_result();
while ($r = $resTop->fetch_assoc()) {
  // tambahkan URL gambar absolut biar frontend enak
  $imgRaw = (string)($r['image'] ?? '');
  $imgUrl = BASE_URL . '/' . ltrim($imgRaw, '/');
  $topMenus[] = [
    'id'          => (int)$r['id'],
    'name'        => $r['name'],
    'image'       => $r['image'],
    'image_url'   => $imgUrl,
    'sold_qty'    => (int)$r['sold_qty'],
    'sold_amount' => (float)$r['sold_amount'],
  ];
}
$stmt3->close();

/* ============================================================
   RESPON
   ============================================================ */
echo json_encode([
  'ok' => true,
  'range' => $range,
  'period' => [
    'from' => $startDate->format('Y-m-d'),
    'to'   => $endDate->format('Y-m-d'),
  ],
  'summary' => [
    'total_revenue' => $totalRevenue,
    'orders_paid'   => $ordersPaid,
    'avg_order'     => $avgOrder,
  ],
  'chart' => [
    'labels'  => $labels,
    'revenue' => $revenue,
  ],
  'top_menus' => $topMenus,
], JSON_UNESCAPED_UNICODE);

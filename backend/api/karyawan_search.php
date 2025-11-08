<?php
// backend/api/karyawan_search.php
declare(strict_types=1);

session_start();

// lokasinya: /backend/api/karyawan_search.php
// jadi ke config.php cukup naik 1 folder
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_guard.php';

/**
 * Karena ini endpoint API, kita TIDAK mau di-redirect ke login.html
 * kalau tidak punya session. Kita balikin JSON 401 saja.
 */
if (!isset($_SESSION['user_id']) || (($_SESSION['user_role'] ?? '') !== 'karyawan')) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => 'Unauthorized',
        'message' => 'Silakan login sebagai karyawan.'
    ]);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if ($q === '' || mb_strlen($q) < 2) {
    echo json_encode([
        'ok' => true,
        'results' => []
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$like = '%' . $q . '%';
$results = [];

/* ========= CARI ORDERS =========
   orders: id, invoice_no, customer_name, total, order_status
*/
$sqlOrder = "SELECT id, invoice_no, customer_name, total, order_status
             FROM orders
             WHERE invoice_no LIKE ? OR customer_name LIKE ?
             ORDER BY created_at DESC
             LIMIT 6";
if ($stmt = $conn->prepare($sqlOrder)) {
    $stmt->bind_param('ss', $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $results[] = [
            'type'  => 'order',
            'key'   => $row['invoice_no'] ?: ('#ORD-' . $row['id']),
            'label' => $row['invoice_no'] ?: ('Order #' . $row['id']),
            'sub'   => ($row['customer_name'] ? $row['customer_name'] . ' • ' : '') .
                       'Status: ' . ($row['order_status'] ?: '-'),
        ];
    }
    $stmt->close();
}

/* ========= CARI MENU =========
   menu: id, name, stock_status, price
*/
$sqlMenu = "SELECT id, name, stock_status, price
            FROM menu
            WHERE name LIKE ?
            ORDER BY name
            LIMIT 6";
if ($stmt = $conn->prepare($sqlMenu)) {
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $results[] = [
            'type'  => 'menu',
            'key'   => $row['name'],
            'label' => $row['name'],
            'sub'   => 'Stok: ' . ($row['stock_status'] ?? '-') .
                       ($row['price'] !== null ? ' • Rp ' . number_format((float)$row['price'], 0, ',', '.') : ''),
        ];
    }
    $stmt->close();
}

echo json_encode([
    'ok' => true,
    'results' => $results,
], JSON_UNESCAPED_UNICODE);

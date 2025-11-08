<?php
// backend/api/admin_search.php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_guard.php';

/*
 * ADMIN ONLY
 * beda sama karyawan_search.php yang cuma bolehin karyawan.
 */
if (!isset($_SESSION['user_id']) || (($_SESSION['user_role'] ?? '') !== 'admin')) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => 'Unauthorized',
        'message' => 'Silakan login sebagai admin.'
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

/* 1. CARI ORDERS (sama kaya karyawan, tapi admin boleh lihat semua) */
$sqlOrder = "
    SELECT id, invoice_no, customer_name, total, order_status
    FROM orders
    WHERE invoice_no LIKE ? OR customer_name LIKE ?
    ORDER BY created_at DESC
    LIMIT 8
";
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

/* 2. CARI MENU / CATALOG */
$sqlMenu = "
    SELECT id, name, stock_status, price
    FROM menu
    WHERE name LIKE ?
    ORDER BY name
    LIMIT 6
";
if ($stmt = $conn->prepare($sqlMenu)) {
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $results[] = [
            'type'  => 'menu',
            'key'   => (string)$row['id'],
            'label' => $row['name'],
            'sub'   => 'Stok: ' . ($row['stock_status'] ?? '-') .
                       ($row['price'] !== null ? ' • Rp ' . number_format((float)$row['price'], 0, ',', '.') : ''),
        ];
    }
    $stmt->close();
}

/* 3. CARI USERS (ini bedanya admin) */
$sqlUsers = "
    SELECT id, name, email, role, status
    FROM users
    WHERE name LIKE ? OR email LIKE ?
    ORDER BY name
    LIMIT 6
";
if ($stmt = $conn->prepare($sqlUsers)) {
    $stmt->bind_param('ss', $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $results[] = [
            'type'  => 'user',
            'key'   => (string)$row['id'],
            'label' => $row['name'] ?: $row['email'],
            'sub'   => ($row['email'] ? $row['email'] . ' • ' : '') .
                       'Role: ' . ($row['role'] ?? '-') .
                       ' • Status: ' . ($row['status'] ?? '-'),
        ];
    }
    $stmt->close();
}

echo json_encode([
    'ok'      => true,
    'results' => $results,
], JSON_UNESCAPED_UNICODE);

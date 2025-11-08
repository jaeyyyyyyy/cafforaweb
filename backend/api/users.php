<?php
// backend/api/users.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';

session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
  http_response_code(403);
  echo json_encode(['ok'=>false,'error'=>'forbidden']); exit;
}

$q      = trim((string)($_GET['q'] ?? ''));
$role   = trim((string)($_GET['role'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));

$where = []; $params = []; $types = '';

if ($q !== '') {
  $where[] = '(name LIKE ? OR email LIKE ?)';
  $like = '%'.$q.'%'; $params[]=$like; $params[]=$like; $types .= 'ss';
}
if (in_array($role, ['admin','customer','karyawan'], true)) {
  $where[] = 'role = ?'; $params[] = $role; $types .= 's';
}
if (in_array($status, ['pending','active'], true)) {
  $where[] = 'status = ?'; $params[] = $status; $types .= 's';
}

$sql = "SELECT id,name,email,role,status,created_at FROM users";
if ($where) $sql .= ' WHERE '.implode(' AND ', $where);
$sql .= " ORDER BY created_at DESC";

$items = [];
$stmt = $conn->prepare($sql);
if ($stmt) {
  if ($params) $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($r = $res->fetch_assoc()) {
    $items[] = [
      'id'         => (int)$r['id'],
      'name'       => (string)$r['name'],
      'email'      => (string)$r['email'],
      'role'       => (string)$r['role'],
      'status'     => (string)$r['status'],
      'created_at' => (string)$r['created_at'],
    ];
  }
  $stmt->close();
}

echo json_encode(['ok'=>true,'items'=>$items], JSON_UNESCAPED_SLASHES);
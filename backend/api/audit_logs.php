<?php
// backend/api/audit_logs.php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_guard.php';
require_login(['admin']); // audit untuk admin

header('Content-Type: application/json; charset=utf-8');

/* =========================
   KONEKSI
========================= */
if (!isset($conn) || !($conn instanceof mysqli)) {
  $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
  if ($conn->connect_errno) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'db connect failed']);
    exit;
  }
  $conn->set_charset('utf8mb4');
}

/* =========================
   PARAM & DEFAULT
========================= */
$q        = trim((string)($_GET['q'] ?? ''));
$entity   = strtolower(trim((string)($_GET['entity'] ?? '')));       // order|payment
$actor_id = (int)($_GET['actor_id'] ?? 0);
$from     = trim((string)($_GET['from'] ?? ''));                     // YYYY-MM-DD
$to       = trim((string)($_GET['to'] ?? ''));                       // YYYY-MM-DD

$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
$importantOnly = (int)($_GET['important_only'] ?? 1);                // 1 = hanya penting (default)

$sort     = $_GET['sort'] ?? 'created_at_desc';
$sortMap  = [
  'created_at_desc' => 'a.created_at DESC, a.id DESC',
  'created_at_asc'  => 'a.created_at ASC, a.id ASC',
  'actor_asc'       => 'a.actor_id ASC, a.created_at DESC',
  'actor_desc'      => 'a.actor_id DESC, a.created_at DESC',
];
$orderBy = $sortMap[$sort] ?? $sortMap['created_at_desc'];

/* =========================
   BANGUN WHERE DINAMIS
========================= */
$where  = [];
$types  = '';
$params = [];

/* 1) Batasi entitas ke order & payment secara default */
$allowedEntities = ['order','payment'];
if ($entity !== '' && in_array($entity, $allowedEntities, true)) {
  $where[] = 'a.entity = ?';
  $types  .= 's';
  $params[] = $entity;
} else {
  $where[] = "(a.entity IN ('order','payment'))";
}

/* 2) Important-only rules */
if ($importantOnly === 1) {
  $where[] = "(
      a.entity = 'order'
      OR (
        a.entity = 'payment' AND (
          a.action IN ('paid','mark_paid','refund','update_status')
          OR a.to_val LIKE '%paid%'
          OR a.to_val LIKE '%\"paid\"%'
        )
      )
    )";
}

/* 3) Actor */
if ($actor_id > 0) {
  $where[] = 'a.actor_id = ?';
  $types  .= 'i';
  $params[] = $actor_id;
}

/* 4) Rentang tanggal (pakai created_at) */
$fromOk = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from);
$toOk   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $to);
if ($fromOk && $toOk) {
  $where[] = 'a.created_at BETWEEN ? AND ?';
  $types  .= 'ss';
  $params[] = $from . ' 00:00:00';
  $params[] = $to   . ' 23:59:59';
} elseif ($fromOk) {
  $where[] = 'a.created_at >= ?';
  $types  .= 's';
  $params[] = $from . ' 00:00:00';
} elseif ($toOk) {
  $where[] = 'a.created_at <= ?';
  $types  .= 's';
  $params[] = $to . ' 23:59:59';
}

/* 5) Query text (remark/action/from/to + entity_id angka) */
if ($q !== '') {
  $like = '%' . $q . '%';
  $sub = '(a.remark LIKE ? OR a.action LIKE ? OR a.from_val LIKE ? OR a.to_val LIKE ?';
  $types  .= 'ssss';
  $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;

  if (ctype_digit($q)) {
    $sub .= ' OR a.entity_id = ?';
    $types  .= 'i';
    $params[] = (int)$q;
  }
  $sub .= ')';
  $where[] = $sub;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/* =========================
   COUNT TOTAL
   (Join ke users cukup; join ke orders tidak dibutuhkan untuk hitung)
========================= */
$countSql = "SELECT COUNT(*) AS c
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.actor_id
             $whereSql";
$st = $conn->prepare($countSql);
if ($types !== '') $st->bind_param($types, ...$params);
$st->execute();
$totalRows = (int)($st->get_result()->fetch_assoc()['c'] ?? 0);
$st->close();

$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

/* =========================
   AMBIL DATA
   >>> Tambahan penting: JOIN ke orders untuk ambil invoice_no
   >>> Berlaku untuk entity 'order' dan 'payment' (keduanya pakai order_id=entity_id)
========================= */
$selectSql = "
  SELECT
    a.id, a.created_at,
    a.actor_id,
    COALESCE(u.name, '')  AS actor_name,
    COALESCE(u.role, '')  AS actor_role,
    a.entity, a.entity_id, a.action,
    a.from_val, a.to_val, a.remark,
    COALESCE(o.invoice_no, '') AS invoice
  FROM audit_logs a
  LEFT JOIN users  u ON u.id = a.actor_id
  LEFT JOIN orders o
         ON o.id = a.entity_id
        AND a.entity IN ('order','payment')
  $whereSql
  ORDER BY $orderBy
  LIMIT ? OFFSET ?
";
$st2 = $conn->prepare($selectSql);

$types2 = $types . 'ii';
$args2  = $params;
$args2[] = $perPage;
$args2[] = $offset;

$st2->bind_param($types2, ...$args2);
$st2->execute();
$res = $st2->get_result();

$data = [];
while ($r = $res->fetch_assoc()) {
  $data[] = [
    'id'         => (int)$r['id'],
    'created_at' => (string)$r['created_at'],
    'actor_id'   => $r['actor_id'] !== null ? (int)$r['actor_id'] : null,
    'actor_name' => (string)$r['actor_name'],
    'actor_role' => (string)$r['actor_role'],
    'entity'     => (string)$r['entity'],
    'entity_id'  => (int)$r['entity_id'],
    'action'     => (string)$r['action'],
    'from_val'   => (string)($r['from_val'] ?? ''),
    'to_val'     => (string)($r['to_val']   ?? ''),
    'remark'     => (string)($r['remark']   ?? ''),
    // selalu tersedia (atau string kosong) karena JOIN orders
    'invoice'    => (string)$r['invoice'],
  ];
}
$st2->close();

/* =========================
   RESPON
========================= */
echo json_encode([
  'ok' => true,
  'filters' => [
    'q' => $q, 'entity' => $entity, 'actor_id' => $actor_id,
    'from' => $from, 'to' => $to, 'sort' => $sort,
    'important_only' => $importantOnly,
  ],
  'pagination' => [
    'page' => $page,
    'per_page' => $perPage,
    'total_rows' => $totalRows,
    'total_pages' => $totalPages,
  ],
  'data' => $data,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

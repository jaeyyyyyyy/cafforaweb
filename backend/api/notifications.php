<?php
// backend/api/notifications.php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
  echo json_encode(['ok'=>false,'error'=>'DB connect failed']);
  exit;
}
$conn->set_charset('utf8mb4');

/* ---------- UTIL ---------- */
/**
 * Normalisasi role: HANYA admin, karyawan, customer.
 * Default fallback -> customer.
 */
function norm_role(string $r): string {
  $r = strtolower(trim($r));
  if ($r === 'admin')     return 'admin';
  if ($r === 'karyawan')  return 'karyawan';
  if ($r === 'customer')  return 'customer';
  return 'customer';
}
function jexit(array $arr): void {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($arr, JSON_UNESCAPED_SLASHES);
  exit;
}

/**
 * Simpan notifikasi baru.
 * - user_id NULL → broadcast
 * - role NULL   → broadcast global (semua role)
 * - status default: unread
 */
function insert_notif(mysqli $db, ?int $userId, ?string $role, string $message, string $link=''): bool {
  $role = $role ? norm_role($role) : null;
  $sql = "INSERT INTO notifications (user_id, role, message, status, link, created_at)
          VALUES (NULLIF(?,0), ?, ?, 'unread', ?, NOW())";
  if (!$stmt = $db->prepare($sql)) return false;
  $uid = $userId ?? 0;
  $stmt->bind_param('isss', $uid, $role, $message, $link);
  $ok = $stmt->execute();
  $stmt->close();
  return $ok;
}

/* ---------- SESSION USER ---------- */
$userId = (int)($_SESSION['user_id'] ?? 0);
$userRoleRaw = (string)($_SESSION['user_role'] ?? '');
if ($userId && $userRoleRaw === '') {
  if ($res = $conn->query("SELECT role FROM users WHERE id={$userId} LIMIT 1")) {
    $row = $res->fetch_assoc();
    $userRoleRaw = (string)($row['role'] ?? '');
    $res->close();
  }
}
$userRole = norm_role($userRoleRaw);

/* ---------- ACTION ---------- */
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

/* ---------- BASE WHERE ---------- */
/* Semua role dapat notifikasi dari:
   - user_id = saya (personal)
   - broadcast global (user_id IS NULL AND role IS NULL)
   - broadcast ke role saya
*/
$roleEsc   = $conn->real_escape_string($userRole);
$baseWhere = "(user_id = {$userId}
               OR (user_id IS NULL AND (role IS NULL OR role = '{$roleEsc}')))";

/* =====================================================
   CREATE (khusus admin kirim manual)
===================================================== */
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $sessionRole = norm_role($_SESSION['user_role'] ?? '');
  if ($sessionRole !== 'admin') jexit(['ok'=>false,'error'=>'Unauthorized']);

  $targetType = $_POST['target_type'] ?? '';
  $targetRole = norm_role((string)($_POST['target_role'] ?? ''));
  $targetUser = (int)($_POST['target_user'] ?? 0);
  $message    = trim((string)($_POST['message'] ?? ''));
  $link       = trim((string)($_POST['link'] ?? ''));

  if ($message === '') jexit(['ok'=>false,'error'=>'Pesan wajib diisi']);

  if ($targetType === 'role') {
    insert_notif($conn, null, $targetRole, $message, $link);
  } elseif ($targetType === 'all') {
    insert_notif($conn, null, null, $message, $link);
  } elseif ($targetType === 'user' && $targetUser > 0) {
    insert_notif($conn, $targetUser, null, $message, $link);
  } else {
    jexit(['ok'=>false,'error'=>'Target tidak valid']);
  }

  jexit(['ok'=>true,'message'=>'Notifikasi berhasil dikirim']);
}

/* =====================================================
   UNREAD COUNT (badge untuk SEMUA role: admin/karyawan/customer)
===================================================== */
if ($action === 'unread_count') {
  // Jika belum login, kembalikan 0 agar aman
  if ($userId < 1) jexit(['ok'=>true,'count'=>0]);

  $sql = "SELECT COUNT(*) AS c
          FROM notifications
          WHERE status='unread' AND (
            user_id = {$userId}
            OR (user_id IS NULL AND (role IS NULL OR role = '{$roleEsc}'))
          )";
  $res = $conn->query($sql);
  $count = 0;
  if ($res) {
    $row = $res->fetch_assoc();
    $count = (int)($row['c'] ?? 0);
    $res->close();
  }
  jexit(['ok'=>true,'count'=>$count]);
}

/* =====================================================
   LIST
===================================================== */
if ($action === 'list') {
  $sql = "SELECT id, message, status, created_at, link
          FROM notifications
          WHERE {$baseWhere}
          ORDER BY created_at DESC
          LIMIT 100";
  $res = $conn->query($sql);
  $items = [];
  if ($res) {
    while ($row = $res->fetch_assoc()) {
      $items[] = [
        'id'         => (int)$row['id'],
        'message'    => $row['message'],
        'status'     => $row['status'],
        'is_read'    => $row['status'] === 'read',
        'link'       => $row['link'],
        'created_at' => $row['created_at']
      ];
    }
    $res->close();
  }
  jexit(['ok'=>true,'items'=>$items]);
}

/* =====================================================
   MARK ALL READ
===================================================== */
if ($action === 'mark_all_read') {
  $conn->query("UPDATE notifications SET status='read'
                WHERE {$baseWhere} AND status='unread'");
  jexit(['ok'=>true]);
}

/* =====================================================
   MARK ONE READ
===================================================== */
if ($action === 'mark_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = (int)($_POST['id'] ?? 0);
  if ($id > 0) {
    $conn->query("UPDATE notifications SET status='read'
                  WHERE id={$id} AND {$baseWhere}");
  }
  jexit(['ok'=>true]);
}

/* =====================================================
   SYSTEM NOTIF (auto dari orders)
===================================================== */
if ($action === 'system_new_order' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $orderId      = (int)($_POST['order_id'] ?? 0);
  $customerId   = (int)($_POST['customer_id'] ?? 0);
  $customerName = trim((string)($_POST['customer_name'] ?? 'Customer'));
  $total        = (float)($_POST['total'] ?? 0);
  $staffLink    = trim((string)($_POST['staff_link'] ?? ''));
  $custLink     = trim((string)($_POST['customer_link'] ?? ''));

  if ($orderId < 1) jexit(['ok'=>false,'error'=>'Param tidak lengkap']);

  // ke customer (personal)
  if ($customerId > 0) {
    insert_notif(
      $conn,
      $customerId,
      'customer',
      "Pesanan #{$orderId} berhasil dibuat. Terima kasih, {$customerName}!",
      $custLink
    );
  }

  // broadcast ke karyawan & admin
  $msg = "Pesanan baru #{$orderId} dari {$customerName} (Rp " . number_format($total, 0, ',', '.') . ")";
  insert_notif($conn, null, 'karyawan', $msg, $staffLink);
  insert_notif($conn, null, 'admin',    $msg, $staffLink);

  jexit(['ok'=>true,'message'=>'System notif dikirim ke semua role']);
}

jexit(['ok'=>false,'error'=>'Invalid action']);

<?php
// backend/auth_guard.php
declare(strict_types=1);

require_once __DIR__ . '/config.php'; // BASE_URL, $conn, redirect()

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

/**
 * Normalisasi ketat ke 3 role saja:
 * - admin
 * - karyawan
 * - customer
 * Selain itu dianggap "customer".
 */
function cf_role_strict(?string $raw): string {
  $r = strtolower(trim($raw ?? ''));
  if ($r === 'admin')     return 'admin';
  if ($r === 'karyawan')  return 'karyawan';
  return 'customer';
}

/**
 * Pastikan user sudah login (opsional: batasi role).
 * @param array $allowedRoles contoh: ['customer'] atau ['admin','karyawan']
 * @return array data user (id,name,email,status,role,avatar,phone)
 */
function require_login(array $allowedRoles = []) : array {
  global $conn;

  // 1) Belum login → ke login
  if (empty($_SESSION['user_id'])) {
    redirect('/public/login.html?err=' . urlencode('Silakan login dulu.'));
  }

  $userId = (int) $_SESSION['user_id'];

  // 2) Ambil user dari DB
  $stmt = $conn->prepare('SELECT id, name, email, status, role, avatar, phone FROM users WHERE id=? LIMIT 1');
  if (!$stmt) {
    http_response_code(500);
    exit('Database prepare failed.');
  }
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $res = $stmt->get_result();
  $currentUser = $res->fetch_assoc();
  $stmt->close();

  // 3) User tidak ditemukan → paksa logout
  if (!$currentUser) {
    session_unset();
    session_destroy();
    redirect('/public/login.html?err=' . urlencode('Sesi berakhir. Silakan login ulang.'));
  }

  // 4) Status belum aktif → minta verifikasi
  if (($currentUser['status'] ?? 'pending') !== 'active') {
    redirect('/public/verify_otp.html?email=' . urlencode((string)$currentUser['email']));
  }

  // 5) Role ketat (hanya 3)
  $normRole = cf_role_strict($currentUser['role'] ?? 'customer');

  // Simpan ke session agar konsisten
  $_SESSION['user_name']   = (string)$currentUser['name'];
  $_SESSION['user_email']  = (string)$currentUser['email'];
  $_SESSION['user_role']   = $normRole;
  $_SESSION['user_phone']  = (string)($currentUser['phone']  ?? '');
  $_SESSION['user_avatar'] = (string)($currentUser['avatar'] ?? '');

  // 6) Batasi role jika diminta
  if ($allowedRoles) {
    // Hanya terima nilai persis admin/karyawan/customer
    $allowedStrict = array_map('cf_role_strict', $allowedRoles);
    if (!in_array($normRole, $allowedStrict, true)) {
      // Arahkan ke dashboard sesuai role aktual
      if ($normRole === 'admin') {
        redirect('/public/admin/index.php');
      } elseif ($normRole === 'karyawan') {
        redirect('/public/karyawan/index.php');
      } else {
        redirect('/public/customer/index.php');
      }
    }
  }

  // Kembalikan data user dengan role yang sudah dipastikan
  $currentUser['role'] = $normRole;
  return $currentUser;
}

/** Helper untuk halaman yang hanya boleh 1 role */
function require_role(string $role) : array {
  return require_login([$role]);
}

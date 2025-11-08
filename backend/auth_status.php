<?php
// backend/auth_status.php
declare(strict_types=1);
session_start();
header('Content-Type: application/json');

$logged = isset($_SESSION['user_id']); // atau logika yang kamu pakai
$role = $_SESSION['user_role'] ?? null;

echo json_encode([
  'logged_in' => $logged,
  'role' => $role
]);
\<?php
// backend/check_email.php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

$email = trim((string)($_GET['email'] ?? ''));
$exists = false;

if ($email !== '' && isset($conn) && ($conn instanceof mysqli)) {
  @$conn->set_charset('utf8mb4');
  $stmt = $conn->prepare('SELECT 1 FROM users WHERE email=? LIMIT 1');
  if ($stmt) {
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();
  }
}

echo json_encode(['exists' => $exists], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

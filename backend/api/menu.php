<?php
// backend/api/menu.php
declare(strict_types=1);

// Hanya GET
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
  http_response_code(405);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok'=>false,'error'=>'Method not allowed']);
  exit;
}

// JSON + no-cache
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../config.php';

$q        = trim((string)($_GET['q'] ?? ''));
$category = strtolower(trim((string)($_GET['category'] ?? ''))); // food|pastry|drink
$status   = ($_GET['status'] ?? '') === 'Ready' ? 'Ready' : '';

$where  = [];
$params = [];
$types  = '';

if ($status) { $where[] = 'stock_status = ?'; $params[] = $status; $types .= 's'; }
if ($q !== '') {
  $where[] = '(name LIKE ? OR category LIKE ?)';
  $like = '%'.$q.'%'; $params[] = $like; $params[] = $like; $types .= 'ss';
}
if (in_array($category, ['food','pastry','drink'], true)) {
  $where[] = 'LOWER(category) = ?'; $params[] = $category; $types .= 's';
}

$sql = 'SELECT id, name, category, image, price, stock_status, created_at FROM menu';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY created_at DESC';

function build_image_url(?string $img, string $baseUrl): string {
  $img = trim((string)$img);
  if ($img === '') {
    return rtrim($baseUrl, '/') . '/public/assets/img/placeholder-1x1.png';
  }
  if (preg_match('~^https?://~i', $img) || str_starts_with($img, 'data:')) {
    return $img;
  }
  $rel = ltrim($img, '/');
  if (str_starts_with($rel, 'public/')) $rel = substr($rel, 7);

  $parts = $rel !== '' ? explode('/', $rel) : [];
  $file  = $parts ? array_pop($parts) : '';
  $dirRel = $parts ? implode('/', $parts) : '';

  $publicFs = realpath(__DIR__ . '/../../public') ?: (__DIR__ . '/../../public');
  $fsPath = rtrim($publicFs, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
          . ($dirRel ? $dirRel . DIRECTORY_SEPARATOR : '')
          . $file;

  if (!is_file($fsPath) && $file !== '') {
    $dirFs = rtrim($publicFs, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $dirRel;
    if (is_dir($dirFs)) {
      $lower = strtolower($file);
      foreach (scandir($dirFs) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        if (strtolower($entry) === $lower) { $file = $entry; break; }
      }
    }
  }

  $safeRel = ($dirRel ? $dirRel . '/' : '') . rawurlencode($file);
  return rtrim($baseUrl, '/') . '/public/' . ltrim($safeRel, '/');
}

$items = [];
$stmt = $conn->prepare($sql);
if ($stmt) {
  if ($params) { $stmt->bind_param($types, ...$params); }
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) {
    $items[] = [
      'id'         => (int)$row['id'],
      'name'       => (string)$row['name'],
      'category'   => strtolower((string)$row['category']),
      'price'      => number_format((float)$row['price'], 0, ',', '.'),
      'price_int'  => (int)round((float)$row['price']),
      'stock'      => (string)$row['stock_status'],
      'image_url'  => build_image_url($row['image'] ?? '', BASE_URL),
      'created_at' => (string)$row['created_at'],
    ];
  }
  $stmt->close();
}

echo json_encode(['ok'=>true, 'items'=>$items], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);

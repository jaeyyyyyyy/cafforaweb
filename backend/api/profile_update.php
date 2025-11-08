<?php
// backend/api/profile_update.php
declare(strict_types=1);

require_once __DIR__ . '/../auth_guard.php';
require_once __DIR__ . '/../config.php';

// ⬇⬇⬇ INI YANG PENTING: izinkan 3 role
$user = require_login(['admin','karyawan','customer']);

header('Content-Type: application/json; charset=utf-8');

$uid   = (int) $user['id'];
$role  = strtolower($user['role'] ?? '');
$ok    = false;
$msg   = 'Tidak ada perubahan.';
$data  = [];

/**
 * helper kirim respon
 */
function send($success, $message, $extra = []) {
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $extra));
    exit;
}

/**
 * upload avatar sederhana
 */
function handle_upload(string $field, int $uid): ?string {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $f = $_FILES[$field];

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
    $mime    = mime_content_type($f['tmp_name']);
    if (!isset($allowed[$mime])) {
        return null;
    }

    $ext   = $allowed[$mime];
    $fname = 'avatar_' . $uid . '_' . time() . '.' . $ext;

    // simpan ke /public/uploads/avatars
    $targetDir = dirname(__DIR__) . '/public/uploads/avatars';
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0775, true);
    }
    $targetPath = $targetDir . '/' . $fname;

    if (!move_uploaded_file($f['tmp_name'], $targetPath)) {
      return null;
    }

    // path relatif utk disimpan di DB
    return '/public/uploads/avatars/' . $fname;
}

try {

    // ====== 1. UPLOAD FOTO ======
    if (!empty($_FILES['profile_picture'])) {
        $newPath = handle_upload('profile_picture', $uid);
        if ($newPath) {
            $stmt = $conn->prepare("UPDATE users SET avatar=? WHERE id=? LIMIT 1");
            $stmt->bind_param('si', $newPath, $uid);
            $stmt->execute();
            $stmt->close();

            // sync ke session
            $_SESSION['user_avatar'] = $newPath;

            send(true, 'Foto profil berhasil diperbarui', [
                'data' => ['profile_picture' => $newPath]
            ]);
        } else {
            send(false, 'Gagal mengunggah foto. Pastikan format JPG/PNG dan ukuran tidak terlalu besar.');
        }
    }

    // karena frontend kita kirim via FormData, dia bisa kirim salah satu saja.
    $updatedFields = 0;

    // ====== 2. UBAH NAMA ======
    if (isset($_POST['name'])) {
        $name = trim((string)$_POST['name']);
        if ($name !== '') {
            $stmt = $conn->prepare("UPDATE users SET name=? WHERE id=? LIMIT 1");
            $stmt->bind_param('si', $name, $uid);
            $stmt->execute();
            $stmt->close();

            $_SESSION['user_name'] = $name;
            $data['name'] = $name;
            $updatedFields++;
        }
    }

    // ====== 3. UBAH PHONE ======
    if (isset($_POST['phone'])) {
        $phone = trim((string)$_POST['phone']);
        $stmt = $conn->prepare("UPDATE users SET phone=? WHERE id=? LIMIT 1");
        $stmt->bind_param('si', $phone, $uid);
        $stmt->execute();
        $stmt->close();

        $_SESSION['user_phone'] = $phone;
        $data['phone'] = $phone;
        $updatedFields++;
    }

    // ====== 4. GANTI PASSWORD ======
    if (isset($_POST['old_password']) && isset($_POST['password'])) {
        $old = (string)$_POST['old_password'];
        $new = (string)$_POST['password'];

        // ambil password lama
        $stmt = $conn->prepare("SELECT password FROM users WHERE id=? LIMIT 1");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $res  = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$res) {
            send(false, 'User tidak ditemukan.');
        }

        $currentHash = $res['password'];

        // cek password lama
        // kalau di DB kamu plain-text, ganti bagian ini
        if (!password_verify($old, $currentHash)) {
            send(false, 'Password lama salah.');
        }

        if (strlen($new) < 6) {
            send(false, 'Password baru minimal 6 karakter.');
        }

        $newHash = password_hash($new, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=? LIMIT 1");
        $stmt->bind_param('si', $newHash, $uid);
        $stmt->execute();
        $stmt->close();

        $updatedFields++;
        $data['password_changed'] = true;
    }

    if ($updatedFields > 0) {
        send(true, 'Profil berhasil diperbarui.', ['data' => $data]);
    } else {
        send(false, 'Tidak ada data yang dikirim.');
    }

} catch (Throwable $e) {
    send(false, 'Terjadi kesalahan: ' . $e->getMessage());
}

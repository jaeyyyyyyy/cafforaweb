<?php
/**
 * backend/lib/audit_helper.php
 * Helper ringan untuk menulis log ke tabel `audit_logs`.
 *
 * Struktur tabel saat ini:
 *  - id INT AI
 *  - actor_id INT NULL
 *  - entity ENUM('order','payment','menu','user') NOT NULL
 *  - entity_id INT NOT NULL
 *  - action VARCHAR(50) NOT NULL
 *  - from_val TEXT NULL
 *  - to_val   TEXT NULL
 *  - remark   VARCHAR(255) NULL
 *  - created_at TIMESTAMP DEFAULT current_timestamp()
 *
 * Pemakaian cepat:
 *   require_once __DIR__ . '/../lib/audit_helper.php';
 *   audit_order($conn, $actorId, $orderId, 'cancel', ['order_status'=>'new'], ['order_status'=>'cancelled'], 'cancel via API');
 *
 * Catatan:
 * - Hanya melakukan INSERT; pembacaan dilakukan oleh endpoint API kamu.
 * - Helper ini aman dipakai dari admin/karyawan/customer—tinggal isi actor_id sesuai session.
 */

declare(strict_types=1);

/**
 * Normalisasi entity agar sesuai enum tabel.
 */
function audit_normalize_entity(string $entity): string {
    $e = strtolower(trim($entity));
    // daftar entity yang diizinkan oleh enum
    $allowed = ['order','payment','menu','user'];
    return in_array($e, $allowed, true) ? $e : 'user';
}

/**
 * Ubah nilai from/to ke string yang aman disimpan (TEXT).
 * - array/object => JSON (tanpa escape unicode)
 * - skalar => cast ke string
 * - batasi panjang ekstrem (TEXT ~ 65k), kita batasi 60k char untuk aman
 */
function audit_to_str(mixed $val): ?string {
    if ($val === null) return null;

    if (is_array($val) || is_object($val)) {
        $s = json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
        $s = (string)$val;
    }

    // clamp panjang agar tidak melebihi TEXT
    if (strlen($s) > 60000) {
        $s = substr($s, 0, 59990) . '…';
    }
    return $s;
}

/**
 * Core function: tulis 1 baris audit.
 *
 * @param mysqli|null $db        Koneksi. Bila null, coba pakai $GLOBALS['conn'].
 * @param int|null    $actor_id  ID user pelaku (boleh null).
 * @param string      $entity    'order'|'payment'|'menu'|'user'
 * @param int         $entity_id ID entitas terkait.
 * @param string      $action    Nama aksi (maks 50 char), mis: 'create','cancel','update_status','mark_paid'
 * @param mixed       $from_val  Nilai sebelum (akan di-json-kan bila array/object).
 * @param mixed       $to_val    Nilai sesudah (akan di-json-kan bila array/object).
 * @param string|null $remark    Catatan singkat (maks 255 char).
 *
 * @return int|false  ID insert bila sukses, atau false bila gagal.
 */
function audit_log(
    ?mysqli $db,
    ?int $actor_id,
    string $entity,
    int $entity_id,
    string $action,
    mixed $from_val = null,
    mixed $to_val   = null,
    ?string $remark = null
): int|false {
    // fallback koneksi dari config
    if ($db === null) {
        $db = $GLOBALS['conn'] ?? null;
        if (!$db instanceof mysqli) {
            return false;
        }
    }

    // Normalisasi & pembatasan
    $entity = audit_normalize_entity($entity);
    $entity_id = max(0, (int)$entity_id);

    $action = trim($action);
    if ($action === '') $action = 'change';
    if (mb_strlen($action, 'UTF-8') > 50) {
        $action = mb_substr($action, 0, 50, 'UTF-8');
    }

    $remark = $remark !== null ? trim($remark) : null;
    if ($remark !== null && mb_strlen($remark, 'UTF-8') > 255) {
        $remark = mb_substr($remark, 0, 255, 'UTF-8');
    }

    $from_s = audit_to_str($from_val);
    $to_s   = audit_to_str($to_val);

    // Siapkan statement
    $sql = "INSERT INTO audit_logs (actor_id, entity, entity_id, action, from_val, to_val, remark)
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $st = $db->prepare($sql);
    if (!$st) return false;

    // Bind:
    // actor_id (i, bisa NULL), entity (s), entity_id (i), action (s), from_val (s), to_val (s), remark (s)
    $st->bind_param(
        'isissss',
        $actor_id,      // NULL OK untuk ON DELETE SET NULL jika kelak pakai FK
        $entity,
        $entity_id,
        $action,
        $from_s,
        $to_s,
        $remark
    );

    $ok = $st->execute();
    if (!$ok) {
        $st->close();
        return false;
    }
    $id = $st->insert_id;
    $st->close();

    return (int)$id;
}

/* =========================
 *  Convenience wrappers
 * ========================= */

/**
 * Audit khusus ORDER.
 */
function audit_order(
    ?mysqli $db,
    ?int $actor_id,
    int $order_id,
    string $action,
    mixed $from_val = null,
    mixed $to_val   = null,
    ?string $remark = null
): int|false {
    return audit_log($db, $actor_id, 'order', $order_id, $action, $from_val, $to_val, $remark);
}

/**
 * Audit khusus PAYMENT.
 */
function audit_payment(
    ?mysqli $db,
    ?int $actor_id,
    int $payment_id,
    string $action,
    mixed $from_val = null,
    mixed $to_val   = null,
    ?string $remark = null
): int|false {
    return audit_log($db, $actor_id, 'payment', $payment_id, $action, $from_val, $to_val, $remark);
}

/**
 * Audit khusus MENU (opsional, tetap tersedia).
 */
function audit_menu(
    ?mysqli $db,
    ?int $actor_id,
    int $menu_id,
    string $action,
    mixed $from_val = null,
    mixed $to_val   = null,
    ?string $remark = null
): int|false {
    return audit_log($db, $actor_id, 'menu', $menu_id, $action, $from_val, $to_val, $remark);
}

/**
 * Audit khusus USER (opsional).
 */
function audit_user(
    ?mysqli $db,
    ?int $actor_id,
    int $user_id,
    string $action,
    mixed $from_val = null,
    mixed $to_val   = null,
    ?string $remark = null
): int|false {
    return audit_log($db, $actor_id, 'user', $user_id, $action, $from_val, $to_val, $remark);
}

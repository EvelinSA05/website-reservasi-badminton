<?php

require_once 'auth.php';

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function ensure_login_and_role(array $allowedRoles = []) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    try {
        try_login_from_remember_cookie();
    } catch (Throwable $e) {
        set_flash('error', 'Sesi login tidak valid. Silakan login ulang.');
        header('Location: ../login.php');
        exit;
    }

    $user = current_user();
    if (!$user) {
        set_flash('error', 'Silakan login terlebih dahulu.');
        header('Location: ../login.php');
        exit;
    }

    if (!empty($allowedRoles) && !in_array($user['role'], $allowedRoles, true)) {
        set_flash('error', 'Akses ditolak untuk role Anda.');
        header('Location: dashboard.php');
        exit;
    }

    return $user;
}

function role_permission_map() {
    return [
        'owner' => [
            'dashboard.view',
            'reservation.read_all',
            'payment.read_all',
            'report.read',
            'court.read',
        ],
        'admin' => [
            'dashboard.view',
            'reservation.read_all',
            'reservation.create_internal',
            'reservation.status.update',
            'reservation.delete_any',
            'payment.read_all',
            'payment.create_internal',
            'payment.update',
            'payment.delete',
            'report.read',
            'court.read',
            'court.create',
            'court.update',
            'court.delete',
        ],
        'kasir' => [
            'dashboard.view',
            'reservation.read_all',
            'reservation.create_internal',
            'payment.read_all',
            'payment.create_internal',
            'payment.update',
            'report.read',
        ],
        'pelanggan' => [
            'dashboard.view',
            'reservation.read_own',
            'reservation.create_own',
            'reservation.update_own',
            'reservation.delete_own',
            'payment.read_own',
            'payment.create_own',
        ],
    ];
}

function role_has_permission($role, $permission) {
    $role = strtolower(trim((string) $role));
    $permission = trim((string) $permission);
    $map = role_permission_map();
    if ($permission === '' || !isset($map[$role])) {
        return false;
    }
    return in_array($permission, $map[$role], true);
}

function user_has_permission($user, $permission) {
    if (is_array($user)) {
        return role_has_permission((string) ($user['role'] ?? ''), $permission);
    }
    return role_has_permission((string) $user, $permission);
}

function ensure_permission($user, $permission, $redirect = 'dashboard.php', $message = 'Aksi ini tidak diizinkan untuk role Anda.') {
    if (user_has_permission($user, $permission)) {
        return true;
    }

    set_flash('error', $message);
    header('Location: ' . $redirect);
    exit;
}

function role_capability_groups($role) {
    $role = strtolower(trim((string) $role));
    $groups = [
        'reservation' => [
            'create' => role_has_permission($role, 'reservation.create_own') || role_has_permission($role, 'reservation.create_internal'),
            'read' => role_has_permission($role, 'reservation.read_own') || role_has_permission($role, 'reservation.read_all'),
            'update' => role_has_permission($role, 'reservation.update_own') || role_has_permission($role, 'reservation.status.update'),
            'delete' => role_has_permission($role, 'reservation.delete_own') || role_has_permission($role, 'reservation.delete_any'),
        ],
        'payment' => [
            'create' => role_has_permission($role, 'payment.create_own') || role_has_permission($role, 'payment.create_internal'),
            'read' => role_has_permission($role, 'payment.read_own') || role_has_permission($role, 'payment.read_all'),
            'update' => role_has_permission($role, 'payment.update'),
            'delete' => role_has_permission($role, 'payment.delete'),
        ],
        'court' => [
            'create' => role_has_permission($role, 'court.create'),
            'read' => role_has_permission($role, 'court.read'),
            'update' => role_has_permission($role, 'court.update'),
            'delete' => role_has_permission($role, 'court.delete'),
        ],
        'report' => [
            'read' => role_has_permission($role, 'report.read'),
        ],
    ];

    return $groups;
}

function redirect_with_flash($url, $type, $message) {
    set_flash($type, $message);
    header('Location: ' . $url);
    exit;
}

function next_prefixed_id($table, $column, $prefix, $padding = 3) {
    $sql = "SELECT {$column} AS id FROM {$table} WHERE {$column} LIKE :prefix";
    $stmt = db()->prepare($sql);
    $stmt->execute(['prefix' => $prefix . '%']);
    $rows = $stmt->fetchAll();

    $max = 0;
    foreach ($rows as $row) {
        $id = (string) ($row['id'] ?? '');
        if (strpos($id, $prefix) !== 0) {
            continue;
        }
        $suffix = substr($id, strlen($prefix));
        if (ctype_digit($suffix)) {
            $num = (int) $suffix;
            if ($num > $max) {
                $max = $num;
            }
        }
    }

    return $prefix . str_pad((string) ($max + 1), $padding, '0', STR_PAD_LEFT);
}

function calculate_durasi_jam($jamMulai, $jamSelesai) {
    $mulai = strtotime($jamMulai);
    $selesai = strtotime($jamSelesai);
    if ($mulai === false || $selesai === false || $selesai <= $mulai) {
        return 0;
    }

    $diffDetik = $selesai - $mulai;
    return (int) ceil($diffDetik / 3600);
}

function find_lapangan_schedule_info($idLapangan) {
    $stmt = db()->prepare('SELECT id_lapangan, nama_lapangan, jam_buka, jam_tutup, status FROM lapangan WHERE id_lapangan = :id LIMIT 1');
    $stmt->execute(['id' => (string) $idLapangan]);
    return $stmt->fetch() ?: null;
}

function find_conflicting_reservation($idLapangan, $tanggal, $jamMulai, $jamSelesai, $excludeReservationId = null) {
    $sql = "
        SELECT
            r.id_reservasi,
            r.status_reservasi,
            r.tanggal_booking,
            r.jam_mulai,
            r.jam_selesai,
            p.nama_lengkap,
            l.nama_lapangan
        FROM reservasi r
        JOIN pengguna p ON p.id_pengguna = r.id_pengguna
        JOIN lapangan l ON l.id_lapangan = r.id_lapangan
        WHERE r.id_lapangan = :id_lapangan
          AND r.tanggal_booking = :tanggal_booking
          AND r.status_reservasi <> 'dibatalkan'
          AND r.jam_mulai < :jam_selesai
          AND r.jam_selesai > :jam_mulai
    ";

    $params = [
        'id_lapangan' => (string) $idLapangan,
        'tanggal_booking' => (string) $tanggal,
        'jam_mulai' => (string) $jamMulai,
        'jam_selesai' => (string) $jamSelesai,
    ];

    if ($excludeReservationId !== null && $excludeReservationId !== '') {
        $sql .= ' AND r.id_reservasi <> :exclude_id';
        $params['exclude_id'] = (string) $excludeReservationId;
    }

    $sql .= ' ORDER BY r.jam_mulai ASC LIMIT 1';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch() ?: null;
}

function ensure_reservation_schedule_is_available($idLapangan, $tanggal, $jamMulai, $jamSelesai, $excludeReservationId = null) {
    $tanggal = trim((string) $tanggal);
    $jamMulai = trim((string) $jamMulai);
    $jamSelesai = trim((string) $jamSelesai);

    if ($tanggal === '' || $jamMulai === '' || $jamSelesai === '') {
        throw new RuntimeException('Tanggal dan jam reservasi wajib diisi lengkap.');
    }

    if (strtotime($tanggal) === false || strtotime($jamMulai) === false || strtotime($jamSelesai) === false) {
        throw new RuntimeException('Format tanggal atau jam reservasi tidak valid.');
    }

    if (strtotime($jamSelesai) <= strtotime($jamMulai)) {
        throw new RuntimeException('Jam selesai harus lebih besar dari jam mulai.');
    }

    $lapangan = find_lapangan_schedule_info($idLapangan);
    if (!$lapangan) {
        throw new RuntimeException('Lapangan yang dipilih tidak ditemukan.');
    }

    if (($lapangan['status'] ?? '') !== 'tersedia') {
        throw new RuntimeException('Lapangan ini sedang tidak tersedia untuk dibooking.');
    }

    $jamBuka = substr((string) ($lapangan['jam_buka'] ?? ''), 0, 5);
    $jamTutup = substr((string) ($lapangan['jam_tutup'] ?? ''), 0, 5);
    if ($jamBuka !== '' && $jamMulai < $jamBuka) {
        throw new RuntimeException('Jam mulai berada di luar operasional lapangan.');
    }
    if ($jamTutup !== '' && $jamSelesai > $jamTutup) {
        throw new RuntimeException('Jam selesai berada di luar operasional lapangan.');
    }

    $conflict = find_conflicting_reservation($idLapangan, $tanggal, $jamMulai, $jamSelesai, $excludeReservationId);
    if ($conflict) {
        $conflictStart = substr((string) ($conflict['jam_mulai'] ?? ''), 0, 5);
        $conflictEnd = substr((string) ($conflict['jam_selesai'] ?? ''), 0, 5);
        $lapanganName = (string) ($conflict['nama_lapangan'] ?? $lapangan['nama_lapangan'] ?? 'lapangan ini');
        throw new RuntimeException(
            'Slot bentrok dengan reservasi ' . (string) ($conflict['id_reservasi'] ?? '-') .
            ' pada ' . $lapanganName . ' (' . $conflictStart . '-' . $conflictEnd . ').'
        );
    }

    return $lapangan;
}

?>

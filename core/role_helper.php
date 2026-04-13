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

?>

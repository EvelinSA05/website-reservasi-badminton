<?php
session_start();

require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/cookies.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php');
    exit;
}

$action = isset($_POST['action']) ? trim($_POST['action']) : '';
$loginPortal = isset($_POST['login_portal']) ? trim((string) $_POST['login_portal']) : 'public';
$isStaffPortal = $loginPortal === 'staff';

try {
    if ($action === 'register') {
        $name = isset($_POST['register_name']) ? trim($_POST['register_name']) : '';
        $email = isset($_POST['register_email']) ? trim($_POST['register_email']) : '';
        $password = isset($_POST['register_password']) ? $_POST['register_password'] : '';
        $confirm = isset($_POST['register_confirm_password']) ? $_POST['register_confirm_password'] : '';
        $phone = isset($_POST['register_phone']) ? trim($_POST['register_phone']) : '';
        $role = 'pelanggan';

        if ($password !== $confirm) {
            set_flash('error', 'Konfirmasi password tidak sama.');
            header('Location: ../pages/register.php');
            exit;
        }

        $result = register_user($name, $email, $password, $phone, $role);
        if (!$result['ok']) {
            set_flash('error', $result['message']);
            header('Location: ../pages/register.php');
            exit;
        }

        set_cookie_last_email($email);
        set_flash('success', $result['message']);
        header('Location: ../login.php');
        exit;
    }

    if ($action === 'login') {
        $email = isset($_POST['login_email']) ? trim($_POST['login_email']) : '';
        $password = isset($_POST['login_password']) ? $_POST['login_password'] : '';
        $remember = isset($_POST['remember_me']) && $_POST['remember_me'] === '1';

        $result = attempt_login($email, $password);
        if (!$result['ok']) {
            set_flash('error', $result['message']);
            header('Location: ' . ($isStaffPortal ? '../login-staff.php' : '../login.php'));
            exit;
        }

        $role = (string) ($result['user']['role'] ?? '');
        if ($isStaffPortal && !in_array($role, ['admin', 'kasir', 'owner'], true)) {
            set_flash('error', 'Portal staff hanya untuk admin, kasir, dan owner.');
            header('Location: ../login-staff.php');
            exit;
        }

        login_user_session($result['user']);
        set_cookie_last_email($email);

        if ($remember) {
            issue_remember_me($result['user']['id_pengguna'], 30);
        }

        set_flash('success', 'Login berhasil sebagai ' . $result['user']['role'] . '. Selamat datang, ' . $result['user']['nama_lengkap'] . '!');
        header('Location: ' . ($isStaffPortal ? '../pages/dashboard.php' : '../index.php'));
        exit;
    }

    set_flash('error', 'Aksi tidak dikenali.');
    header('Location: ../login.php');
    exit;
} catch (Throwable $e) {
    $msg = $e->getMessage();
    if (stripos($msg, 'pdo_mysql') !== false) {
        set_flash('error', 'Extension pdo_mysql belum aktif. Aktifkan di php.ini lalu restart server.');
    } else {
        set_flash('error', 'Koneksi database gagal atau query bermasalah. Cek konfigurasi DB.');
    }
    $target = $action === 'register' ? '../pages/register.php' : ($isStaffPortal ? '../login-staff.php' : '../login.php');
    header('Location: ' . $target);
    exit;
}
?>


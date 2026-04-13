<?php
session_start();
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/cookies.php';

$user = current_user();
$role = strtolower(trim((string) ($user['role'] ?? '')));
$redirectTarget = in_array($role, ['admin', 'kasir', 'owner'], true)
    ? '../login-staff.php'
    : '../index.php';

logout_user();
clear_auth_cookies();

session_start();
set_flash('success', 'Logout berhasil.');
header('Location: ' . $redirectTarget);
exit;
?>

<?php
session_start();
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/cookies.php';

logout_user();
clear_auth_cookies();

session_start();
set_flash('success', 'Logout berhasil.');
header('Location: ../index.php');
exit;
?>

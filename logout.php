<?php
/**
 * LOGOUT.PHP - Menghapus Session & Cookies
 * 
 * File ini menghancurkan semua data session dan cookies yang tersimpan.
 * 
 * Perbedaan penghapusan:
 * - Session: menggunakan session_destroy() → data di server dihapus
 * - Cookies: menggunakan setcookie() dengan waktu expired di masa lalu → browser menghapus cookie
 */

// Mulai session agar bisa mengaksesnya
session_start();

// Include fungsi cookies
require_once 'cookies.php';

// ============================================================
// HAPUS SESSION
// ============================================================
// Kosongkan semua variabel session
$_SESSION = [];

// Hapus cookie session (PHPSESSID)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),    // Biasanya "PHPSESSID"
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Hancurkan session di server
session_destroy();

// ============================================================
// HAPUS COOKIES
// ============================================================
hapus_semua_cookie();

// Redirect ke index.php
header('Location: index.php');
exit;
?>

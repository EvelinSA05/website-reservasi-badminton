<?php
/**
 * RESET_SESSION.PHP - Hapus Session Saja (bukan cookies)
 * 
 * Dipanggil oleh JavaScript saat browser baru dibuka.
 * Hanya menghapus data session, cookies tetap utuh.
 */

require_once 'session_config.php';
session_start();

// Kosongkan semua variabel session
$_SESSION = [];

// Hapus cookie PHPSESSID
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
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

// Response OK
http_response_code(200);
echo 'Session cleared';
?>

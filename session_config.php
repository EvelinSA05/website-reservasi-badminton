<?php
/**
 * SESSION_CONFIG.PHP - Konfigurasi Session yang Ketat
 * 
 * File ini mengatur parameter session agar benar-benar hilang saat browser ditutup.
 * Menggunakan server-side expiry karena beberapa browser (Chrome) memulihkan session cookies.
 * HARUS di-include SEBELUM session_start() di setiap file.
 */

// Konfigurasi session cookie agar expire saat browser ditutup
session_set_cookie_params([
    'lifetime' => 0,          // 0 = session cookie, hilang saat browser ditutup
    'path' => '/',
    'httponly' => true,        // Cookie tidak bisa diakses via JavaScript
    'samesite' => 'Strict'    // Proteksi CSRF
]);

// Session expired setelah 30 menit tidak aktif
ini_set('session.gc_maxlifetime', 1800);
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 1);

// Gunakan cookie only untuk session ID (lebih aman)
ini_set('session.use_only_cookies', 1);
ini_set('session.use_strict_mode', 1);

/**
 * Fungsi untuk mengecek apakah session sudah expired
 * Dipanggil SETELAH session_start()
 * 
 * Session akan dihapus jika:
 * 1. Tidak aktif selama 30 menit (1800 detik)
 * 2. Sudah lebih dari 2 jam sejak session dibuat
 */
function cek_session_expired() {
    $waktu_sekarang = time();
    $batas_tidak_aktif = 1800;   // 30 menit
    $batas_maksimal = 7200;       // 2 jam maksimal umur session

    // Set waktu pembuatan session jika belum ada
    if (!isset($_SESSION['session_dibuat'])) {
        $_SESSION['session_dibuat'] = $waktu_sekarang;
    }

    // Set waktu aktivitas terakhir jika belum ada
    if (!isset($_SESSION['aktivitas_terakhir'])) {
        $_SESSION['aktivitas_terakhir'] = $waktu_sekarang;
    }

    // Cek 1: Session tidak aktif terlalu lama (30 menit)
    $selisih_aktivitas = $waktu_sekarang - $_SESSION['aktivitas_terakhir'];
    if ($selisih_aktivitas > $batas_tidak_aktif) {
        hapus_session();
        return;
    }

    // Cek 2: Session sudah terlalu lama (lebih dari 2 jam)
    $selisih_dibuat = $waktu_sekarang - $_SESSION['session_dibuat'];
    if ($selisih_dibuat > $batas_maksimal) {
        hapus_session();
        return;
    }

    // Update waktu aktivitas terakhir
    $_SESSION['aktivitas_terakhir'] = $waktu_sekarang;
}

/**
 * Hapus session secara menyeluruh
 */
function hapus_session() {
    $_SESSION = [];
    
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
    
    session_destroy();
    session_start(); // Mulai session baru yang kosong
    $_SESSION['session_dibuat'] = time();
    $_SESSION['aktivitas_terakhir'] = time();
}
?> 

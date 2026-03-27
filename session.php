<?php
/**
 * SESSION.PHP - Pengelolaan Session
 * 
 * File ini menangani penyimpanan data reservasi menggunakan SESSION.
 * 
 * Session menyimpan data di sisi SERVER dan hanya bertahan selama sesi browsing aktif.
 * Setiap pengguna mendapat Session ID unik (disimpan sebagai cookie PHPSESSID di browser).
 * Session otomatis hilang ketika browser ditutup atau session di-destroy.
 * 
 * Pada project ini, session digunakan untuk:
 * 1. Menyimpan data reservasi terakhir
 * 2. Menyimpan riwayat semua reservasi selama sesi aktif
 * 3. Menandai status reservasi (berhasil/gagal)
 */

// Konfigurasi session ketat (HARUS sebelum session_start)
require_once 'session_config.php';

// Mulai session - HARUS dipanggil sebelum output apapun
session_start();
cek_session_expired(); // Cek apakah session sudah expired

// Include file cookies untuk fungsi set cookie
require_once 'cookies.php';

// Hanya proses jika ada data POST dari form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Ambil data dari form
    $nama = isset($_POST['id_pengguna']) ? trim($_POST['id_pengguna']) : '';
    $tanggal = isset($_POST['tanggal_booking']) ? trim($_POST['tanggal_booking']) : '';
    $jam_mulai = isset($_POST['jam_mulai']) ? trim($_POST['jam_mulai']) : '';
    $jam_selesai = isset($_POST['jam_selesai']) ? trim($_POST['jam_selesai']) : '';

    // Validasi server-side: pastikan semua field terisi
    if (empty($nama) || empty($tanggal) || empty($jam_mulai) || empty($jam_selesai)) {
        $_SESSION['status'] = 'gagal';
        $_SESSION['pesan'] = 'Semua kolom wajib diisi!';
        header('Location: index.php');
        exit;
    }

    // Validasi: nama hanya boleh huruf dan spasi
    if (!preg_match('/^[a-zA-Z\s]+$/', $nama)) {
        $_SESSION['status'] = 'gagal';
        $_SESSION['pesan'] = 'Nama hanya boleh berisi huruf dan spasi!';
        header('Location: index.php');
        exit;
    }

    // Validasi: jam selesai harus lebih besar dari jam mulai
    if ($jam_selesai <= $jam_mulai) {
        $_SESSION['status'] = 'gagal';
        $_SESSION['pesan'] = 'Jam selesai harus lebih besar dari jam mulai!';
        header('Location: index.php');
        exit;
    }

    // ============================================================
    // SIMPAN KE SESSION - Data tersimpan di server
    // ============================================================
    
    // Data reservasi terakhir (single reservation)
    $_SESSION['reservasi_terakhir'] = [
        'nama' => $nama,
        'tanggal' => $tanggal,
        'jam_mulai' => $jam_mulai,
        'jam_selesai' => $jam_selesai,
        'waktu_booking' => date('d/m/Y H:i:s')
    ];

    // Riwayat reservasi (array of reservations) - menumpuk selama sesi aktif
    if (!isset($_SESSION['riwayat'])) {
        $_SESSION['riwayat'] = [];
    }
    $_SESSION['riwayat'][] = $_SESSION['reservasi_terakhir'];

    // Set status berhasil
    $_SESSION['status'] = 'berhasil';
    $_SESSION['pesan'] = 'Reservasi berhasil disimpan!';

    // ============================================================
    // SIMPAN KE COOKIES - Data tersimpan di browser pengguna
    // ============================================================
    
    // Simpan nama pengguna ke cookie (30 hari) agar auto-fill saat kembali
    set_cookie_nama($nama);
    
    // Simpan jam mulai & jam selesai ke cookie
    set_cookie_jam($jam_mulai, $jam_selesai);
    
    // Simpan tanggal booking ke cookie
    set_cookie_tanggal($tanggal);

    // Redirect kembali ke index.php
    header('Location: index.php');
    exit;

} else {
    // Jika diakses langsung tanpa POST, redirect ke index.php
    header('Location: index.php');
    exit;
}
?>

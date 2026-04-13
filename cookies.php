<?php
/**
 * COOKIES.PHP - Pengelolaan Cookies
 * 
 * File ini berisi fungsi-fungsi untuk mengelola cookies pada website reservasi badminton.
 * 
 * Cookies menyimpan data di sisi CLIENT (browser) dan bertahan meskipun browser ditutup.
 * Digunakan untuk mengingat preferensi pengguna seperti nama, agar tidak perlu mengetik ulang.
 */

/**
 * Set cookie nama pengguna
 * Cookie akan bertahan selama 30 hari
 * 
 * @param string $nama - Nama pengguna yang akan disimpan
 */
function set_cookie_nama($nama) {
    // setcookie(nama, nilai, waktu_expired, path)
    // time() + (86400 * 30) = 30 hari dari sekarang (86400 detik = 1 hari)
    setcookie('nama_pengguna', $nama, time() + (86400 * 30), '/');
}

/**
 * Ambil cookie nama pengguna
 * 
 * @return string - Nama pengguna dari cookie, atau string kosong jika tidak ada
 */
function get_cookie_nama() {
    return isset($_COOKIE['nama_pengguna']) ? $_COOKIE['nama_pengguna'] : '';
}

/**
 * Hapus cookie nama pengguna
 * Dilakukan dengan men-set waktu expired ke masa lalu
 */
function hapus_cookie_nama() {
    // Set expired ke 1 jam yang lalu agar browser menghapus cookie
    setcookie('nama_pengguna', '', time() - 3600, '/');
}

/**
 * Set cookie preferensi jam
 * Cookie bertahan 30 hari
 * 
 * @param string $jam_mulai - Jam mulai
 * @param string $jam_selesai - Jam selesai
 */
function set_cookie_jam($jam_mulai, $jam_selesai) {
    setcookie('jam_mulai', $jam_mulai, time() + (86400 * 30), '/');
    setcookie('jam_selesai', $jam_selesai, time() + (86400 * 30), '/');
}

/**
 * Ambil cookie jam
 * 
 * @return array - Array berisi jam_mulai dan jam_selesai dari cookie
 */
function get_cookie_jam() {
    return [
        'jam_mulai' => isset($_COOKIE['jam_mulai']) ? $_COOKIE['jam_mulai'] : '',
        'jam_selesai' => isset($_COOKIE['jam_selesai']) ? $_COOKIE['jam_selesai'] : ''
    ];
}

/**
 * Set cookie tanggal booking
 * Cookie bertahan 30 hari
 * 
 * @param string $tanggal - Tanggal booking
 */
function set_cookie_tanggal($tanggal) {
    setcookie('tanggal_booking', $tanggal, time() + (86400 * 30), '/');
}

/**
 * Ambil cookie tanggal booking
 * 
 * @return string - Tanggal booking dari cookie, atau string kosong jika tidak ada
 */
function get_cookie_tanggal() {
    return isset($_COOKIE['tanggal_booking']) ? $_COOKIE['tanggal_booking'] : '';
}

/**
 * Hapus semua cookies terkait reservasi
 */
function hapus_semua_cookie() {
    hapus_cookie_nama();
    setcookie('jam_mulai', '', time() - 3600, '/');
    setcookie('jam_selesai', '', time() - 3600, '/');
    setcookie('tanggal_booking', '', time() - 3600, '/');
}

// ============================================================
// Jika file ini diakses langsung (bukan di-include),
// tampilkan halaman info cookies yang aktif
// ============================================================
if (basename($_SERVER['PHP_SELF']) === 'cookies.php') {
    require_once 'session_config.php';
    session_start();
    cek_session_expired(); // Cek apakah session sudah expired
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Info Cookies - Reservasi Badminton</title>
    <link rel="icon" type="image/svg+xml"
        href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🍪%3C/text%3E%3C/svg%3E">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .cookie-card {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }
        .cookie-card:hover {
            border-color: rgba(0, 255, 255, 0.4);
            box-shadow: 0 0 20px rgba(0, 255, 255, 0.1);
        }
        .cookie-name { color: #00ffff; font-weight: 600; font-size: 16px; }
        .cookie-value { color: #eee; font-size: 14px; margin-top: 5px; }
        .cookie-meta { color: #888; font-size: 12px; margin-top: 5px; }
        .info-section {
            background: rgba(0,0,0,0.3);
            border-radius: 16px;
            padding: 30px;
            max-width: 600px;
            margin: 40px auto;
            border: 1px solid rgba(255,255,255,0.08);
        }
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-active { background: rgba(0,255,100,0.2); color: #00ff64; }
        .badge-empty { background: rgba(255,100,0,0.2); color: #ff6400; }
    </style>
</head>
<body class="font-outfit flex flex-col justify-start items-center min-h-screen pt-[6vh] px-[20px] pb-[80px] text-[#333] relative overflow-x-hidden">
    
    <div class="info-section">
        <h2 class="text-center text-white font-semibold mb-[10px] text-[24px] tracking-[-0.5px] drop-shadow-[0_2px_10px_rgba(0,255,255,0.5)]">
            🍪 Informasi Cookies
        </h2>
        <p class="text-center text-[#aaa] text-[14px] mb-[25px]">Cookies yang tersimpan di browser Anda saat ini</p>

        <?php
        $cookies_reservasi = [
            'nama_pengguna' => 'Nama Pengguna',
            'tanggal_booking' => 'Tanggal Booking',
            'jam_mulai' => 'Jam Mulai', 
            'jam_selesai' => 'Jam Selesai'
        ];

        $ada_cookie = false;
        foreach ($cookies_reservasi as $key => $label) {
            if (isset($_COOKIE[$key]) && $_COOKIE[$key] !== '') {
                $ada_cookie = true;
        ?>
            <div class="cookie-card">
                <div class="flex justify-between items-center">
                    <span class="cookie-name"><?php echo $label; ?></span>
                    <span class="badge badge-active">Aktif</span>
                </div>
                <div class="cookie-value">📦 Nilai: <strong><?php echo htmlspecialchars($_COOKIE[$key]); ?></strong></div>
                <div class="cookie-meta">🔑 Key: <code><?php echo $key; ?></code> &nbsp;|&nbsp; ⏰ Expires: 30 hari sejak di-set</div>
            </div>
        <?php
            }
        }

        if (!$ada_cookie) {
        ?>
            <div class="cookie-card text-center">
                <span class="badge badge-empty">Kosong</span>
                <p class="text-[#888] mt-[10px] text-[14px]">Belum ada cookies yang tersimpan. Silakan lakukan reservasi terlebih dahulu.</p>
            </div>
        <?php } ?>

        <div class="flex gap-[10px] justify-center mt-[20px]">
            <a href="index.php" class="reset-btn" style="text-decoration:none; text-align:center; padding: 10px 25px;">← Kembali ke Reservasi</a>
            <?php if ($ada_cookie) { ?>
                <a href="reset.php" class="reset-btn" style="text-decoration:none; text-align:center; padding: 10px 25px; background: rgba(255,0,50,0.3); border-color: rgba(255,0,50,0.5);">🗑️ Hapus Semua</a>
            <?php } ?>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>
<?php } ?>

<?php
/**
 * INDEX.PHP - Halaman Utama Reservasi Badminton
 * 
 * Halaman ini menggunakan:
 * - SESSION: untuk menampilkan status reservasi dan riwayat reservasi
 * - COOKIES: untuk auto-fill nama pengguna dan jam favorit
 */

// Mulai session - WAJIB dipanggil di awal sebelum output HTML
session_start();

// Include fungsi cookies
require_once 'cookies.php';

// ============================================================
// BACA COOKIES - untuk auto-fill form
// ============================================================
$nama_dari_cookie = get_cookie_nama();           // Baca cookie nama_pengguna
$jam_favorit = get_cookie_jam_favorit();           // Baca cookie jam favorit

// ============================================================
// BACA SESSION - untuk status dan riwayat
// ============================================================
$status = isset($_SESSION['status']) ? $_SESSION['status'] : '';
$pesan = isset($_SESSION['pesan']) ? $_SESSION['pesan'] : '';
$reservasi_terakhir = isset($_SESSION['reservasi_terakhir']) ? $_SESSION['reservasi_terakhir'] : null;
$riwayat = isset($_SESSION['riwayat']) ? $_SESSION['riwayat'] : [];

// Hapus status setelah dibaca (flash message) agar tidak muncul lagi setelah refresh
unset($_SESSION['status']);
unset($_SESSION['pesan']);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Reservasi Badminton</title>

    <meta name="description" content="Website reservasi lapangan badminton online.">
    <meta name="author" content="Kelompok">

    <!-- Favicon emoji badminton -->
    <link rel="icon" type="image/svg+xml"
        href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🏸%3C/text%3E%3C/svg%3E">
    <link rel="stylesheet" href="style.css">
    <!-- Tailwind CSS Play CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        outfit: ['Outfit', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        /* Styles for session/cookie info cards */
        .info-banner {
            max-width: 520px;
            width: 100%;
            margin: 0 auto 20px auto;
            padding: 15px 20px;
            border-radius: 12px;
            backdrop-filter: blur(10px);
            animation: fadeInDown 0.6s ease-out;
            position: relative;
            z-index: 10;
        }
        .info-banner.success {
            background: rgba(0, 255, 100, 0.1);
            border: 1px solid rgba(0, 255, 100, 0.3);
        }
        .info-banner.error {
            background: rgba(255, 50, 50, 0.1);
            border: 1px solid rgba(255, 50, 50, 0.3);
        }
        .info-banner.cookie-info {
            background: rgba(255, 200, 0, 0.08);
            border: 1px solid rgba(255, 200, 0, 0.25);
        }

        .riwayat-section {
            max-width: 520px;
            width: 100%;
            margin: 25px auto 0 auto;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 25px;
            position: relative;
            z-index: 10;
        }
        .riwayat-item {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }
        .riwayat-item:hover {
            border-color: rgba(0, 255, 255, 0.3);
            box-shadow: 0 0 15px rgba(0, 255, 255, 0.08);
        }
        .riwayat-item:last-child { margin-bottom: 0; }

        .nav-links {
            max-width: 520px;
            width: 100%;
            margin: 15px auto 0 auto;
            display: flex;
            gap: 10px;
            justify-content: center;
            position: relative;
            z-index: 10;
        }
        .nav-link {
            color: #aaa;
            text-decoration: none;
            font-size: 13px;
            padding: 8px 16px;
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s ease;
            background: rgba(255,255,255,0.03);
        }
        .nav-link:hover {
            color: #00ffff;
            border-color: rgba(0,255,255,0.4);
            background: rgba(0,255,255,0.05);
        }

        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-15px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>

<body class="font-outfit flex flex-col justify-start items-center min-h-screen pt-[6vh] px-[20px] pb-[80px] text-[#333] relative overflow-x-hidden">
    <!-- Glowing 3D Badminton Court Background -->
    <div class="glowing-court">
        <div class="court-line court-outer"></div>
        <div class="court-line court-half"></div>
        <div class="court-line court-short-service-top"></div>
        <div class="court-line court-short-service-bottom"></div>
        <div class="court-line court-center"></div>
        <div class="court-line court-singles-left"></div>
        <div class="court-line court-singles-right"></div>
        <div class="court-net"></div>
    </div>
    <div class="floating-objects fixed inset-0 pointer-events-none z-0 overflow-hidden">
        <div class="shuttlecock sc-1">🏸</div>
        <div class="shuttlecock sc-2">🏸</div>
        <div class="shuttlecock sc-3">🏸</div>
        <div class="shuttlecock sc-4">🏸</div>
        <div class="shuttlecock sc-5">🏸</div>
        <div class="shuttlecock sc-6">🏸</div>
    </div>

    <?php
    // ============================================================
    // TAMPILKAN STATUS DARI SESSION (Flash Message)
    // ============================================================
    if ($status === 'berhasil') { ?>
        <div class="info-banner success">
            <p class="text-[#00ff64] font-semibold text-[15px]">✅ <?php echo htmlspecialchars($pesan); ?></p>
            <?php if ($reservasi_terakhir) { ?>
                <p class="text-[#aaa] text-[13px] mt-[5px]">
                    📋 <?php echo htmlspecialchars($reservasi_terakhir['nama']); ?> — 
                    <?php echo htmlspecialchars($reservasi_terakhir['tanggal']); ?> 
                    (<?php echo htmlspecialchars($reservasi_terakhir['jam_mulai']); ?> - <?php echo htmlspecialchars($reservasi_terakhir['jam_selesai']); ?>)
                </p>
            <?php } ?>
        </div>
    <?php } elseif ($status === 'gagal') { ?>
        <div class="info-banner error">
            <p class="text-[#ff5555] font-semibold text-[15px]">❌ <?php echo htmlspecialchars($pesan); ?></p>
        </div>
    <?php } ?>

    <?php
    // ============================================================
    // TAMPILKAN INFO COOKIE (jika ada cookie tersimpan)
    // ============================================================
    if (!empty($nama_dari_cookie)) { ?>
        <div class="info-banner cookie-info">
            <p class="text-[#ffc800] text-[13px]">
                🍪 <strong>Cookie aktif:</strong> Selamat datang kembali, <strong><?php echo htmlspecialchars($nama_dari_cookie); ?></strong>! 
                Nama Anda otomatis terisi dari cookie.
            </p>
        </div>
    <?php } ?>

    <div class="form-wrapper">
        <!-- Form mengirim data ke session.php via POST -->
        <form action="session.php" method="POST" onsubmit="return validasiNama(event)" id="registrationForm">
            <h2 class="text-center text-white font-semibold mb-[25px] text-[26px] tracking-[-0.5px] animate-[fadeInDown_0.8s_ease-out_backwards] drop-shadow-[0_2px_10px_rgba(255,0,255,0.5)]">Formulir Reservasi Badminton</h2>

            <div class="form-group">
                <label for="id_pengguna" class="font-medium text-[18.5px] max-[550px]:text-[14px] max-[550px]:ml-[4px] text-[#eee] self-center">Nama Pengguna</label>
                <span class="separator text-center font-medium text-[18.5px] text-[#eee] self-center max-[550px]:hidden">:</span>
                <div class="input-container flex flex-col w-full">
                    <!-- Value di-isi dari COOKIE jika ada -->
                    <input type="text" id="id_pengguna" name="id_pengguna" 
                           placeholder="Masukkan nama Anda..."
                           value="<?php echo htmlspecialchars($nama_dari_cookie); ?>">
                    <span id="nameError" class="validation-error">⚠️ Nama hanya boleh berisi huruf dan spasi.</span>
                </div>
            </div>


            <div class="form-group">
                <label for="tanggal_booking" class="font-medium text-[18.5px] max-[550px]:text-[14px] max-[550px]:ml-[4px] text-[#eee] self-center">Tanggal Booking</label>
                <span class="separator text-center font-medium text-[18.5px] text-[#eee] self-center max-[550px]:hidden">:</span>
                <input type="date" id="tanggal_booking" name="tanggal_booking">
            </div>

            <div class="form-group">
                <label for="jam_mulai" class="font-medium text-[18.5px] max-[550px]:text-[14px] max-[550px]:ml-[4px] text-[#eee] self-center">Jam Mulai</label>
                <span class="separator text-center font-medium text-[18.5px] text-[#eee] self-center max-[550px]:hidden">:</span>

                <select id="jam_mulai" name="jam_mulai">
                    <option value="">-- Pilih Jam --</option>
                    <?php
                    $jam_options = ['08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00','18:00','19:00','20:00'];
                    foreach ($jam_options as $jam) {
                        $selected = ($jam_favorit['jam_mulai'] === $jam) ? 'selected' : '';
                        echo "<option value=\"$jam\" $selected>$jam</option>";
                    }
                    ?>
                </select>
            </div>


            <div class="form-group">
                <label for="jam_selesai" class="font-medium text-[18.5px] max-[550px]:text-[14px] max-[550px]:ml-[4px] text-[#eee] self-center">Jam Selesai</label>
                <span class="separator text-center font-medium text-[18.5px] text-[#eee] self-center max-[550px]:hidden">:</span>

                <select id="jam_selesai" name="jam_selesai">
                    <option value="">-- Pilih Jam --</option>
                    <?php
                    foreach ($jam_options as $jam) {
                        $selected = ($jam_favorit['jam_selesai'] === $jam) ? 'selected' : '';
                        echo "<option value=\"$jam\" $selected>$jam</option>";
                    }
                    ?>
                </select>
            </div>

            <input type="submit" value="Reservasi">
        </form>

        <!-- Success Animation Container -->
        <div class="success-message" id="successMessage" style="display: none;">
            <div class="checkmark-wrapper flex justify-center mb-[10px]">
                <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                    <circle class="checkmark__circle" cx="26" cy="26" r="25" fill="none" />
                    <path class="checkmark__check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8" />
                </svg>
            </div>
            <h3 class="text-[28px] my-[20px] mb-[10px] text-[#00ffff] drop-shadow-[0_0_10px_rgba(0,255,255,0.5)]">Reservasi Berhasil!</h3>
            <p class="text-[16px] text-[#ccc] mb-[30px]">Lapangan kamu sudah kami siapkan.</p>
            <button class="reset-btn" onclick="resetForm()">Reservasi Baru</button>
        </div>

    </div>

    <!-- Custom Error Alert Popup -->
    <div class="custom-alert-overlay" id="customAlertOverlay">
        <div class="custom-alert-box">
            <div class="alert-icon text-[55px] mb-[10px] animate-[pulseAlert_1.5s_infinite_alternate]">⚠️</div>
            <h3 class="alert-title text-[#ff3366] text-[26px] mb-[15px] font-bold drop-shadow-[0_0_10px_rgba(255,0,50,0.6)]">Validasi Gagal!</h3>
            <p class="alert-text text-[#ffd6e0] mb-[30px] text-[15px] leading-relaxed">Mohon lengkapi semua kolom isian yang berwarna merah sebelum melakukan reservasi.</p>
            <button class="alert-btn" type="button" onclick="closeErrorAlert()">Mengerti</button>
        </div>
    </div>

    <?php
    // ============================================================
    // RIWAYAT RESERVASI DARI SESSION
    // ============================================================
    if (!empty($riwayat)) { ?>
        <div class="riwayat-section">
            <h3 class="text-white text-[20px] font-semibold mb-[15px] drop-shadow-[0_2px_10px_rgba(0,255,255,0.3)]">
                📜 Riwayat Reservasi <span class="text-[#00ffff] text-[14px]">(Session)</span>
            </h3>
            <p class="text-[#888] text-[12px] mb-[15px]">Data ini disimpan di <strong>server (SESSION)</strong> dan akan hilang saat browser ditutup.</p>
            
            <?php foreach (array_reverse($riwayat) as $index => $item) { ?>
                <div class="riwayat-item">
                    <div class="flex justify-between items-center mb-[5px]">
                        <span class="text-[#00ffff] font-semibold text-[14px]">
                            👤 <?php echo htmlspecialchars($item['nama']); ?>
                        </span>
                        <span class="text-[#666] text-[11px]">
                            <?php echo htmlspecialchars($item['waktu_booking']); ?>
                        </span>
                    </div>
                    <p class="text-[#ccc] text-[13px]">
                        📅 <?php echo htmlspecialchars($item['tanggal']); ?> &nbsp;|&nbsp;
                        🕐 <?php echo htmlspecialchars($item['jam_mulai']); ?> - <?php echo htmlspecialchars($item['jam_selesai']); ?>
                    </p>
                </div>
            <?php } ?>
        </div>
    <?php } ?>

    <!-- Navigation Links -->
    <div class="nav-links">
        <a href="cookies.php" class="nav-link">🍪 Lihat Cookies</a>
        <?php if (!empty($riwayat) || !empty($nama_dari_cookie)) { ?>
            <a href="logout.php" class="nav-link">🗑️ Hapus Semua Data</a>
        <?php } ?>
    </div>

    <script src="script.js"></script>
</body>

</html>
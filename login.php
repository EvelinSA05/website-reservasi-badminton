<?php
session_start();
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/cookies.php';
$company = require __DIR__ . '/data/company.php';

try {
    try_login_from_remember_cookie();
} catch (Throwable $e) {
    set_flash('error', 'Gagal membaca data login dari database.');
}

$user = current_user();
$flash = pull_flash();
$lastEmail = get_cookie_last_email();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Pelanggan | <?php echo htmlspecialchars($company['short_name']); ?></title>
    <meta name="description" content="Login pelanggan untuk reservasi di <?php echo htmlspecialchars($company['name']); ?>, <?php echo htmlspecialchars($company['location']); ?>.">
    <meta name="author" content="Kelompok">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%26%23127992%3B%3C/text%3E%3C/svg%3E">
    <link rel="stylesheet" href="assets/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .flash-box { max-width: 580px; width: 100%; margin: 0 auto 14px auto; padding: 12px 16px; border-radius: 12px; border: 1px solid transparent; z-index: 12; position: relative; }
        .flash-success { background: rgba(0,255,120,0.1); border-color: rgba(0,255,120,0.35); color: #87ffbb; }
        .flash-error { background: rgba(255,60,60,0.1); border-color: rgba(255,60,60,0.35); color: #ff9f9f; }
        .auth-links { margin-top: 10px; display: flex; gap: 10px; justify-content: center; }
        .mini-link { color: #8ad8ff; font-size: 13px; text-decoration: none; }
        .mini-link:hover { text-decoration: underline; }
    </style>
</head>
<body class="font-outfit flex justify-center items-start min-h-screen pt-[6vh] px-[20px] pb-[80px] text-[#333] relative overflow-x-hidden">
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
        <div class="shuttlecock sc-1">&#127992;</div>
        <div class="shuttlecock sc-2">&#127992;</div>
        <div class="shuttlecock sc-3">&#127992;</div>
        <div class="shuttlecock sc-4">&#127992;</div>
        <div class="shuttlecock sc-5">&#127992;</div>
        <div class="shuttlecock sc-6">&#127992;</div>
    </div>

    <div class="w-full max-w-[580px] z-[11] relative">
        <?php if ($flash) { ?>
            <div class="flash-box <?php echo $flash['type'] === 'success' ? 'flash-success' : 'flash-error'; ?>">
                <?php echo htmlspecialchars($flash['message']); ?>
            </div>
        <?php } ?>

        <?php if ($user) { ?>
            <div class="success-message" style="display:block;">
                <h2 class="text-[28px] my-[10px] mb-[8px] text-[#00ffff]">Sudah Login</h2>
                <p class="text-[#ccc] mb-[4px]">Halo, <strong><?php echo htmlspecialchars($user['name']); ?></strong></p>
                <p class="text-[#aaa] text-[14px] mb-[3px]"><?php echo htmlspecialchars($user['email']); ?></p>
                <p class="text-[#ffde8a] text-[13px] mb-[20px]">Role: <strong><?php echo htmlspecialchars($user['role']); ?></strong></p>
                <div class="auth-links">
                    <a href="pages/dashboard.php" class="mini-link">Buka Dashboard</a>
                    <a href="actions/logout.php" class="mini-link">Logout</a>
                    <a href="index.php" class="mini-link">Kembali ke Landing</a>
                </div>
            </div>
        <?php } else { ?>
            <div class="form-wrapper">
                <form id="loginForm" class="auth-form" action="actions/session.php" method="POST" onsubmit="return handleLoginSubmit(event)">
                    <input type="hidden" name="action" value="login">
                    <div class="text-center mb-[14px]">
                        <p class="text-[#bef264] text-[12px] uppercase tracking-[0.24em] font-semibold mb-[10px]"><?php echo htmlspecialchars($company['location']); ?></p>
                        <h2 class="text-center text-white font-semibold mb-[10px] text-[26px] tracking-[-0.5px] animate-[fadeInDown_0.8s_ease-out_backwards] drop-shadow-[0_2px_10px_rgba(255,0,255,0.5)]"><?php echo htmlspecialchars($company['short_name']); ?></h2>
                        <p class="text-center text-[#aaa] text-[14px] mb-[4px]">Login pelanggan untuk booking, cek reservasi, dan kirim pembayaran.</p>
                        <p class="text-center text-[#8da4c9] text-[13px]"><?php echo htmlspecialchars($company['name']); ?> • <?php echo htmlspecialchars($company['location']); ?></p>
                    </div>

                    <div class="form-group">
                        <label for="login_email" class="font-medium text-[18.5px] max-[550px]:text-[14px] max-[550px]:ml-[4px] text-[#eee] self-center">Email</label>
                        <span class="separator text-center font-medium text-[18.5px] text-[#eee] self-center max-[550px]:hidden">:</span>
                        <input type="email" id="login_email" name="login_email" value="<?php echo htmlspecialchars($lastEmail); ?>" placeholder="contoh@email.com" required>
                    </div>

                    <div class="form-group">
                        <label for="login_password" class="font-medium text-[18.5px] max-[550px]:text-[14px] max-[550px]:ml-[4px] text-[#eee] self-center">Password</label>
                        <span class="separator text-center font-medium text-[18.5px] text-[#eee] self-center max-[550px]:hidden">:</span>
                        <div class="password-field">
                            <input type="password" id="login_password" name="login_password" placeholder="Masukkan password" required>
                            <button type="button" class="toggle-password" data-target="login_password" aria-label="Tampilkan password">
                                <span class="eye-icon eye-open" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none">
                                        <path d="M2 12C3.9 8.4 7.6 6 12 6C16.4 6 20.1 8.4 22 12C20.1 15.6 16.4 18 12 18C7.6 18 3.9 15.6 2 12Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                        <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/>
                                    </svg>
                                </span>
                                <span class="eye-icon eye-closed" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none">
                                        <path d="M3 3L21 21" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                        <path d="M10.6 10.6C10.2 11 10 11.5 10 12C10 13.1 10.9 14 12 14C12.5 14 13 13.8 13.4 13.4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                        <path d="M6.7 6.7C4.8 8 3.3 9.8 2 12C3.9 15.6 7.6 18 12 18C14.2 18 16.2 17.4 17.9 16.3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M14.9 6.3C14 6.1 13 6 12 6C11.4 6 10.9 6 10.3 6.1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                        <path d="M18.3 7.7C19.8 8.8 21 10.3 22 12C21.4 13.1 20.6 14.1 19.7 14.9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </span>
                            </button>
                        </div>
                    </div>

                    <div class="text-[#ccc] text-[14px] mt-[-5px] mb-[5px] flex items-center gap-[8px]">
                        <input type="checkbox" id="remember_me" name="remember_me" value="1" style="width:16px; height:16px;">
                        <label for="remember_me" class="cursor-pointer">Remember me (30 hari)</label>
                    </div>

                    <input type="submit" value="Login">
                    <p class="auth-switch-text">Belum punya akun? <a href="pages/register.php" class="auth-switch-link">Register sekarang</a></p>
                    <p class="auth-switch-text">Lihat info venue dulu? <a href="index.php" class="auth-switch-link">Buka Landing Pelanggan</a></p>
                </form>
            </div>
        <?php } ?>
    </div>

    <div class="custom-alert-overlay" id="customAlertOverlay">
        <div class="custom-alert-box">
            <div class="alert-icon text-[55px] mb-[10px] animate-[pulseAlert_1.5s_infinite_alternate]">!</div>
            <h3 id="alertTitle" class="alert-title text-[#ff3366] text-[26px] mb-[15px] font-bold drop-shadow-[0_0_10px_rgba(255,0,50,0.6)]">Validasi Gagal</h3>
            <p id="alertMessage" class="alert-text text-[#ffd6e0] mb-[30px] text-[15px] leading-relaxed">Mohon isi semua data yang wajib.</p>
            <button class="alert-btn" type="button" onclick="closeErrorAlert()">Mengerti</button>
        </div>
    </div>

    <script src="assets/script.js"></script>
</body>
</html>


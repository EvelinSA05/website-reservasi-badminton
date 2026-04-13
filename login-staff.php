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
$isStaffLoggedIn = $user && in_array((string) ($user['role'] ?? ''), ['admin', 'kasir', 'owner'], true);
$isPelangganLoggedIn = $user && ((string) ($user['role'] ?? '') === 'pelanggan');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Staff | <?php echo htmlspecialchars($company['short_name']); ?></title>
    <meta name="description" content="Portal login staff untuk admin, kasir, dan owner <?php echo htmlspecialchars($company['name']); ?>.">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%26%23127934%3B%3C/text%3E%3C/svg%3E">
    <link rel="stylesheet" href="assets/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .flash-box { max-width: 620px; width: 100%; margin: 0 auto 14px auto; padding: 12px 16px; border-radius: 14px; border: 1px solid transparent; z-index: 12; position: relative; }
        .flash-success { background: rgba(0,255,120,0.1); border-color: rgba(0,255,120,0.35); color: #87ffbb; }
        .flash-error { background: rgba(255,60,60,0.1); border-color: rgba(255,60,60,0.35); color: #ff9f9f; }
        .staff-shell { max-width: 1120px; width: 100%; display: grid; grid-template-columns: 1.05fr .95fr; gap: 22px; align-items: stretch; }
        .staff-panel {
            border: 1px solid rgba(255,255,255,.12);
            background: linear-gradient(155deg, rgba(14, 9, 35, .92), rgba(25, 8, 45, .88));
            border-radius: 28px;
            padding: 28px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,.28);
        }
        .staff-panel::before {
            content: "";
            position: absolute;
            inset: auto -10% -30% auto;
            width: 240px;
            height: 240px;
            border-radius: 999px;
            background: radial-gradient(circle, rgba(34,211,238,.18), transparent 64%);
            pointer-events: none;
        }
        .staff-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(34,211,238,.12);
            color: #67e8f9;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .staff-title {
            margin: 18px 0 10px;
            font-size: clamp(34px, 4vw, 54px);
            line-height: 1.03;
            letter-spacing: -.03em;
            color: #f8fafc;
            font-weight: 700;
        }
        .staff-copy {
            margin: 0;
            color: #aab5d3;
            line-height: 1.8;
            font-size: 15px;
            max-width: 560px;
        }
        .staff-grid {
            margin-top: 20px;
            display: grid;
            gap: 12px;
        }
        .staff-feature {
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 18px;
            background: rgba(255,255,255,.04);
            padding: 16px 18px;
        }
        .staff-feature strong {
            display: block;
            color: #f8fafc;
            font-size: 16px;
            margin-bottom: 6px;
        }
        .staff-feature span {
            color: #9eb0d2;
            font-size: 14px;
            line-height: 1.7;
        }
        .staff-links { margin-top: 18px; display: flex; gap: 12px; flex-wrap: wrap; }
        .staff-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 16px;
            border-radius: 999px;
            text-decoration: none;
            border: 1px solid rgba(255,255,255,.12);
            background: rgba(255,255,255,.05);
            color: #eaf2ff;
            font-size: 13px;
            font-weight: 600;
        }
        .staff-link.primary {
            background: linear-gradient(135deg, #bef264, #22d3ee);
            color: #140019;
            border-color: transparent;
        }
        .staff-note {
            margin-top: 14px;
            color: #93a6cb;
            font-size: 13px;
            line-height: 1.7;
        }
        .staff-auth-title {
            text-align: center;
            color: #ffffff;
            font-size: 28px;
            margin-bottom: 10px;
            font-weight: 600;
            letter-spacing: -0.03em;
        }
        .staff-auth-subtitle {
            text-align: center;
            color: #aaa;
            font-size: 14px;
            margin-bottom: 16px;
        }
        .role-chip-row {
            display: flex;
            gap: 8px;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }
        .role-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 7px 12px;
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,.14);
            background: rgba(255,255,255,.05);
            color: #d6e0fb;
            font-size: 12px;
            font-weight: 700;
        }
        .auth-links { margin-top: 10px; display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
        .mini-link { color: #8ad8ff; font-size: 13px; text-decoration: none; }
        .mini-link:hover { text-decoration: underline; }
        @media (max-width: 960px) {
            .staff-shell { grid-template-columns: 1fr; max-width: 620px; }
        }
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
        <div class="shuttlecock sc-1">&#127934;</div>
        <div class="shuttlecock sc-2">&#127934;</div>
        <div class="shuttlecock sc-3">&#127934;</div>
        <div class="shuttlecock sc-4">&#127934;</div>
        <div class="shuttlecock sc-5">&#127934;</div>
        <div class="shuttlecock sc-6">&#127934;</div>
    </div>

    <div class="staff-shell z-[11] relative">
        <div class="staff-panel">
            <span class="staff-eyebrow"><?php echo htmlspecialchars($company['location']); ?></span>
            <h1 class="staff-title">Portal kerja internal <?php echo htmlspecialchars($company['short_name']); ?>.</h1>
            <p class="staff-copy">Halaman ini dipisahkan dari login pelanggan supaya admin, kasir, dan owner <?php echo htmlspecialchars($company['name']); ?> bisa langsung masuk ke area operasional venue tanpa melewati landing publik.</p>

            <div class="staff-grid">
                <div class="staff-feature">
                    <strong>Admin</strong>
                    <span>Kelola jadwal lapangan, pantau reservasi pelanggan, dan jaga operasional venue tetap rapi.</span>
                </div>
                <div class="staff-feature">
                    <strong>Kasir</strong>
                    <span>Fokus pada pembayaran booking, status DP atau lunas, dan pengecekan bukti transfer pelanggan.</span>
                </div>
                <div class="staff-feature">
                    <strong>Owner</strong>
                    <span>Lihat ringkasan performa <?php echo htmlspecialchars($company['short_name']); ?>, reservasi aktif, dan arus pembayaran.</span>
                </div>
            </div>

            <div class="staff-links">
                <a href="login.php" class="staff-link primary">Login Pelanggan</a>
            </div>
            <p class="staff-note"><?php echo htmlspecialchars($company['name']); ?> berada di <?php echo htmlspecialchars($company['address']); ?> dengan jam operasional <?php echo htmlspecialchars($company['hours']); ?>. Portal ini memang diakses langsung lewat URL `login-staff.php` tanpa tombol khusus di landing page.</p>
        </div>

        <div class="w-full z-[11] relative">
            <?php if ($flash) { ?>
                <div class="flash-box <?php echo $flash['type'] === 'success' ? 'flash-success' : 'flash-error'; ?>">
                    <?php echo htmlspecialchars($flash['message']); ?>
                </div>
            <?php } ?>

            <?php if ($isStaffLoggedIn) { ?>
                <div class="success-message" style="display:block;">
                    <h2 class="text-[28px] my-[10px] mb-[8px] text-[#00ffff]">Portal Staff Aktif</h2>
                    <p class="text-[#ccc] mb-[4px]">Halo, <strong><?php echo htmlspecialchars($user['name']); ?></strong></p>
                    <p class="text-[#aaa] text-[14px] mb-[3px]"><?php echo htmlspecialchars($user['email']); ?></p>
                    <p class="text-[#ffde8a] text-[13px] mb-[20px]">Role: <strong><?php echo htmlspecialchars($user['role']); ?></strong></p>
                    <div class="auth-links">
                        <a href="pages/dashboard.php" class="mini-link">Buka Dashboard</a>
                        <a href="actions/logout.php" class="mini-link">Logout</a>
                    </div>
                </div>
            <?php } elseif ($isPelangganLoggedIn) { ?>
                <div class="flash-box flash-error">
                    Akun pelanggan sedang aktif. Portal staff hanya untuk admin, kasir, dan owner. Silakan logout dulu atau gunakan login pelanggan biasa.
                </div>
                <div class="auth-links">
                    <a href="actions/logout.php" class="mini-link">Logout</a>
                    <a href="login.php" class="mini-link">Buka Login Pelanggan</a>
                </div>
            <?php } else { ?>
                <div class="form-wrapper">
                    <form id="staffLoginForm" class="auth-form" action="actions/session.php" method="POST" onsubmit="return handleLoginSubmit(event)">
                        <input type="hidden" name="action" value="login">
                        <input type="hidden" name="login_portal" value="staff">
                        <h2 class="staff-auth-title">Login Staff Venue</h2>
                        <p class="staff-auth-subtitle">Masuk untuk melanjutkan ke dashboard internal <?php echo htmlspecialchars($company['short_name']); ?>.</p>

                        <div class="role-chip-row">
                            <span class="role-chip">Admin</span>
                            <span class="role-chip">Kasir</span>
                            <span class="role-chip">Owner</span>
                        </div>

                        <div class="form-group">
                            <label for="login_email" class="font-medium text-[18.5px] max-[550px]:text-[14px] max-[550px]:ml-[4px] text-[#eee] self-center">Email</label>
                            <span class="separator text-center font-medium text-[18.5px] text-[#eee] self-center max-[550px]:hidden">:</span>
                            <input type="email" id="login_email" name="login_email" value="<?php echo htmlspecialchars($lastEmail); ?>" placeholder="staff@sdkbadmintonhall.id" required>
                        </div>

                        <div class="form-group">
                            <label for="login_password" class="font-medium text-[18.5px] max-[550px]:text-[14px] max-[550px]:ml-[4px] text-[#eee] self-center">Password</label>
                            <span class="separator text-center font-medium text-[18.5px] text-[#eee] self-center max-[550px]:hidden">:</span>
                            <div class="password-field">
                                <input type="password" id="login_password" name="login_password" placeholder="Masukkan password staff" required>
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

                        <input type="submit" value="Masuk ke Dashboard Staff">
                        <p class="auth-switch-text">Butuh area pelanggan? <a href="login.php" class="auth-switch-link">Buka login pelanggan</a></p>
                    </form>
                </div>
            <?php } ?>
        </div>
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

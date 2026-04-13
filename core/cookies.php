<?php

require_once __DIR__ . '/auth.php';

function set_cookie_last_email($email) {
    $email = trim($email);
    set_cookie_value('last_email', $email, time() + (86400 * 30));
}

function get_cookie_last_email() {
    return isset($_COOKIE['last_email']) ? (string) $_COOKIE['last_email'] : '';
}

function clear_cookie_last_email() {
    set_cookie_value('last_email', '', time() - 3600);
}

function clear_auth_cookies() {
    clear_cookie_last_email();
    set_cookie_value(REMEMBER_COOKIE_NAME, '', time() - 3600);
}

if (basename($_SERVER['PHP_SELF']) === 'cookies.php') {
    session_start();
    require_once __DIR__ . '/auth.php';

    $cookieMap = [
        'last_email' => 'Email Terakhir',
        REMEMBER_COOKIE_NAME => 'Remember Me Token'
    ];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Info Cookies - Auth Badminton</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .cookie-card { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 18px; margin-bottom: 12px; }
        .cookie-name { color: #00ffff; font-weight: 600; }
        .cookie-value { color: #eee; margin-top: 4px; word-break: break-all; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-active { background: rgba(0,255,100,0.2); color: #00ff64; }
        .badge-empty { background: rgba(255,100,0,0.2); color: #ff6400; }
    </style>
</head>
<body class="font-outfit flex flex-col justify-start items-center min-h-screen pt-[6vh] px-[20px] pb-[80px] text-[#333] relative overflow-x-hidden">
    <div class="form-wrapper" style="max-width:640px;">
        <div class="success-message" style="display:block; text-align:left;">
            <h2 class="text-white text-[24px] mb-[15px]">Cookies Aktif</h2>
            <?php
            $found = false;
            foreach ($cookieMap as $key => $label) {
                if (isset($_COOKIE[$key]) && $_COOKIE[$key] !== '') {
                    $found = true;
                    echo '<div class="cookie-card">';
                    echo '<div class="flex justify-between items-center"><span class="cookie-name">' . htmlspecialchars($label) . '</span><span class="badge badge-active">Aktif</span></div>';
                    echo '<div class="cookie-value">' . htmlspecialchars($_COOKIE[$key]) . '</div>';
                    echo '</div>';
                }
            }

            if (!$found) {
                echo '<div class="cookie-card text-center"><span class="badge badge-empty">Kosong</span><p class="text-[#aaa] mt-[8px]">Belum ada cookie auth tersimpan.</p></div>';
            }
            ?>
            <div class="mt-[15px]">
                <a href="login.php" class="auth-switch-link">Kembali ke Login</a>
            </div>
        </div>
    </div>
</body>
</html>
<?php } ?>

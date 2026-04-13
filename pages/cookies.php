<?php
session_start();
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/cookies.php';

$flash = pull_flash();
$user = current_user();
$cookies = [
    'last_email' => 'Email Terakhir',
    REMEMBER_COOKIE_NAME => 'Remember Me Token'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cookies</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../assets/panel-theme.css">
</head>
<body class="panel-page">
    <div class="panel-wrap" style="max-width: 860px;">
        <div class="panel">
            <div class="panel-title">
                <h2>Info Cookies</h2>
                <div class="links-inline">
                    <a class="link-muted" href="dashboard.php">Dashboard</a>
                    <a class="link-muted" href="../login.php">Login</a>
                </div>
            </div>

            <?php if ($flash): ?>
                <div class="flash <?= $flash['type'] === 'success' ? 'ok' : 'err' ?>"><?= h($flash['message']) ?></div>
            <?php endif; ?>

            <?php if ($user): ?>
                <p class="meta">User aktif: <strong><?= h($user['name']) ?></strong> (<?= h($user['role']) ?>)</p>
            <?php endif; ?>

            <div class="grid-2" style="margin-top: 12px;">
                <?php foreach ($cookies as $key => $label): ?>
                    <div style="padding:12px; border:1px solid rgba(255,255,255,.16); border-radius:12px; background:rgba(255,255,255,.03);">
                        <div class="heading-font" style="font-weight:700; margin-bottom: 4px;"><?= h($label) ?></div>
                        <div class="panel-subtitle" style="margin:0 0 8px;"><?= h($key) ?></div>
                        <div style="word-break:break-all;"><?= isset($_COOKIE[$key]) ? h($_COOKIE[$key]) : '-' ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>

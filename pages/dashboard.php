<?php
session_start();
require_once __DIR__ . '/../core/role_helper.php';

$user = ensure_login_and_role(['admin', 'kasir', 'pelanggan', 'owner']);
$flash = pull_flash();
$role = $user['role'];

function role_features($role) {
    $items = [];

    if ($role === 'admin') {
        $items[] = ['url' => 'lapangan.php', 'label' => 'CRUD Master Lapangan'];
        $items[] = ['url' => 'reservasi.php', 'label' => 'Kelola Transaksi Reservasi'];
        $items[] = ['url' => 'pembayaran.php', 'label' => 'Kelola Transaksi Pembayaran'];
    } elseif ($role === 'kasir') {
        $items[] = ['url' => 'reservasi.php', 'label' => 'Lihat Reservasi'];
        $items[] = ['url' => 'pembayaran.php', 'label' => 'CRUD Pembayaran'];
    } elseif ($role === 'pelanggan') {
        $items[] = ['url' => 'reservasi.php', 'label' => 'CRUD Reservasi Saya'];
    } elseif ($role === 'owner') {
        $items[] = ['url' => 'reservasi.php', 'label' => 'Lihat Laporan Reservasi'];
        $items[] = ['url' => 'pembayaran.php', 'label' => 'Lihat Laporan Pembayaran'];
    }

    return $items;
}

$features = role_features($role);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../assets/panel-theme.css">
    <style>
        .dash-links { display: grid; gap: 10px; margin: 14px 0 20px; }
        .dash-link {
            display: block;
            padding: 12px 14px;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, .15);
            color: #e5f7ff;
            text-decoration: none;
            background: rgba(255, 255, 255, .04);
            transition: .2s ease;
        }
        .dash-link:hover {
            border-color: rgba(0, 255, 255, .55);
            transform: translateY(-1px);
        }
    </style>
</head>
<body class="panel-page">
    <div class="panel-wrap" style="max-width: 880px;">
        <div class="panel">
            <div class="panel-title">
                <h2>Dashboard</h2>
                <div class="links-inline">
                    <a href="../login.php" class="link-muted">Login Page</a>
                    <a href="cookies.php" class="link-muted">Lihat Cookies</a>
                    <a href="../actions/logout.php" class="link-muted">Logout</a>
                </div>
            </div>

            <?php if ($flash): ?>
                <div class="flash <?= $flash['type'] === 'success' ? 'ok' : 'err' ?>"><?= h($flash['message']) ?></div>
            <?php endif; ?>

            <p class="meta">Login sebagai: <strong><?= h($user['name']) ?></strong></p>
            <p class="panel-subtitle" style="margin: 0;"><?= h($user['email']) ?></p>
            <p class="meta" style="color:#ffde8a;">Role aktif: <strong><?= h($user['role']) ?></strong></p>

            <div class="dash-links">
                <?php foreach ($features as $feature): ?>
                    <a class="dash-link" href="<?= h($feature['url']) ?>"><?= h($feature['label']) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>

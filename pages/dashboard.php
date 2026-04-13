<?php
session_start();
require_once __DIR__ . '/../core/role_helper.php';
require_once __DIR__ . '/../data/company.php';

$user = ensure_login_and_role(['admin', 'kasir', 'pelanggan', 'owner']);
$flash = pull_flash();
$role = (string) ($user['role'] ?? '');
$userId = (string) ($user['user_id'] ?? '');
$capabilities = role_capability_groups($role);
$company = require __DIR__ . '/../data/company.php';

function format_short_number($value) {
    $number = (int) $value;
    if ($number >= 1000) {
        return number_format($number / 1000, 1, ',', '.') . 'K';
    }

    return (string) $number;
}

function dashboard_content($role, $userId) {
    $stats = [];
    $actions = [];

    if ($role === 'admin') {
        $stats = [
            [
                'label' => 'Lapangan Aktif',
                'value' => (int) db()->query("SELECT COUNT(*) FROM lapangan WHERE status = 'tersedia'")->fetchColumn(),
                'hint' => 'Siap dipakai untuk booking'
            ],
            [
                'label' => 'Reservasi Hari Ini',
                'value' => (int) db()->query('SELECT COUNT(*) FROM reservasi WHERE tanggal_booking = CURDATE()')->fetchColumn(),
                'hint' => 'Perlu dipantau dan dikonfirmasi'
            ],
            [
                'label' => 'Pembayaran Pending',
                'value' => (int) db()->query("SELECT COUNT(*) FROM pembayaran WHERE LOWER(status_pembayaran) = 'pending'")->fetchColumn(),
                'hint' => 'Butuh verifikasi admin atau kasir'
            ],
            [
                'label' => 'Pelanggan Terdaftar',
                'value' => (int) db()->query("SELECT COUNT(*) FROM pengguna WHERE role = 'pelanggan'")->fetchColumn(),
                'hint' => 'Basis pengguna aktif platform'
            ],
        ];
        $actions = [
            ['url' => 'lapangan.php', 'title' => 'Kelola Lapangan', 'desc' => 'Tambah, ubah, dan rapikan master lapangan agar katalog tetap up to date.', 'badge' => 'Master Data'],
            ['url' => 'reservasi.php', 'title' => 'Pantau Reservasi', 'desc' => 'Cek reservasi baru, ubah status, dan lihat konteks pembayaran tiap booking.', 'badge' => 'Operasional'],
            ['url' => 'pembayaran.php', 'title' => 'Verifikasi Pembayaran', 'desc' => 'Konfirmasi pembayaran pelanggan dan cocokkan bukti transfer atau QRIS.', 'badge' => 'Keuangan'],
            ['url' => 'laporan.php', 'title' => 'Buka Laporan Venue', 'desc' => 'Lihat ringkasan reservasi harian, pemasukan, lapangan favorit, dan pelanggan paling aktif.', 'badge' => 'Laporan'],
        ];
    } elseif ($role === 'kasir') {
        $stats = [
            [
                'label' => 'Pembayaran Hari Ini',
                'value' => (int) db()->query('SELECT COUNT(*) FROM pembayaran WHERE DATE(tanggal_bayar) = CURDATE()')->fetchColumn(),
                'hint' => 'Transaksi yang masuk hari ini'
            ],
            [
                'label' => 'Pending Verifikasi',
                'value' => (int) db()->query("SELECT COUNT(*) FROM pembayaran WHERE LOWER(status_pembayaran) = 'pending'")->fetchColumn(),
                'hint' => 'Segera diproses'
            ],
            [
                'label' => 'Reservasi Aktif',
                'value' => (int) db()->query("SELECT COUNT(*) FROM reservasi WHERE status_reservasi <> 'dibatalkan'")->fetchColumn(),
                'hint' => 'Dasar pengecekan pembayaran'
            ],
            [
                'label' => 'Nominal Masuk',
                'value' => 'Rp' . number_format((int) db()->query('SELECT COALESCE(SUM(jumlah_bayar), 0) FROM pembayaran')->fetchColumn(), 0, ',', '.'),
                'hint' => 'Akumulasi pembayaran tercatat'
            ],
        ];
        $actions = [
            ['url' => 'pembayaran.php', 'title' => 'Kelola Pembayaran', 'desc' => 'Lihat pembayaran baru, update status DP atau lunas, dan periksa bukti transfer.', 'badge' => 'Prioritas'],
            ['url' => 'reservasi.php', 'title' => 'Lihat Reservasi', 'desc' => 'Cocokkan pembayaran terhadap reservasi yang aktif dan cek sisa tagihan pelanggan.', 'badge' => 'Pendukung'],
            ['url' => 'laporan.php', 'title' => 'Laporan Harian', 'desc' => 'Pantau pemasukan masuk, ringkasan booking, dan aktivitas venue per periode.', 'badge' => 'Ringkasan'],
        ];
    } elseif ($role === 'owner') {
        $stats = [
            [
                'label' => 'Total Reservasi',
                'value' => (int) db()->query('SELECT COUNT(*) FROM reservasi')->fetchColumn(),
                'hint' => 'Seluruh reservasi tercatat'
            ],
            [
                'label' => 'Pendapatan Tercatat',
                'value' => 'Rp' . number_format((int) db()->query('SELECT COALESCE(SUM(jumlah_bayar), 0) FROM pembayaran')->fetchColumn(), 0, ',', '.'),
                'hint' => 'Akumulasi semua pembayaran'
            ],
            [
                'label' => 'Pembayaran Pending',
                'value' => (int) db()->query("SELECT COUNT(*) FROM pembayaran WHERE LOWER(status_pembayaran) = 'pending'")->fetchColumn(),
                'hint' => 'Perlu atensi tim operasional'
            ],
            [
                'label' => 'Pelanggan',
                'value' => (int) db()->query("SELECT COUNT(*) FROM pengguna WHERE role = 'pelanggan'")->fetchColumn(),
                'hint' => 'Basis pelanggan aktif'
            ],
        ];
        $actions = [
            ['url' => 'laporan.php', 'title' => 'Laporan Operasional', 'desc' => 'Lihat reservasi per hari, reservasi per bulan, pemasukan, lapangan favorit, dan pelanggan paling aktif.', 'badge' => 'Insight'],
            ['url' => 'pembayaran.php', 'title' => 'Laporan Pembayaran', 'desc' => 'Pantau nominal masuk, status pembayaran, dan transaksi yang masih tertahan.', 'badge' => 'Keuangan'],
            ['url' => 'lapangan.php', 'title' => 'Lihat Master Lapangan', 'desc' => 'Pantau daftar lapangan dan status operasional venue dalam mode baca saja.', 'badge' => 'Read only'],
        ];
    } else {
        $stmt = db()->prepare('SELECT COUNT(*) FROM reservasi WHERE id_pengguna = :id');
        $stmt->execute(['id' => $userId]);
        $reservationCount = (int) $stmt->fetchColumn();

        $stmt = db()->prepare("SELECT COUNT(*) FROM reservasi WHERE id_pengguna = :id AND status_reservasi = 'pending'");
        $stmt->execute(['id' => $userId]);
        $pendingReservations = (int) $stmt->fetchColumn();

        $stmt = db()->prepare("SELECT COUNT(*) FROM pembayaran py JOIN reservasi r ON r.id_reservasi = py.id_reservasi WHERE r.id_pengguna = :id AND LOWER(py.status_pembayaran) = 'pending'");
        $stmt->execute(['id' => $userId]);
        $pendingPayments = (int) $stmt->fetchColumn();

        $stmt = db()->prepare('SELECT COALESCE(SUM(total_biaya), 0) FROM reservasi WHERE id_pengguna = :id');
        $stmt->execute(['id' => $userId]);
        $totalBookingValue = (int) $stmt->fetchColumn();

        $stats = [
            [
                'label' => 'Reservasi Saya',
                'value' => $reservationCount,
                'hint' => 'Riwayat booking yang sudah dibuat'
            ],
            [
                'label' => 'Menunggu Konfirmasi',
                'value' => $pendingReservations,
                'hint' => 'Reservasi yang masih diproses admin'
            ],
            [
                'label' => 'Pembayaran Pending',
                'value' => $pendingPayments,
                'hint' => 'Sedang diverifikasi admin atau kasir'
            ],
            [
                'label' => 'Total Nilai Booking',
                'value' => 'Rp' . number_format($totalBookingValue, 0, ',', '.'),
                'hint' => 'Akumulasi transaksi reservasi kamu'
            ],
        ];
        $actions = [
            ['url' => 'reservasi.php', 'title' => 'Buat atau Kelola Reservasi', 'desc' => 'Pilih slot lapangan, cek status booking, dan lanjut ke pembayaran dari satu tempat.', 'badge' => 'Utama'],
            ['url' => 'pembayaran.php', 'title' => 'Riwayat Pembayaran', 'desc' => 'Lihat pembayaran yang sudah dikirim, status verifikasi, dan bukti transfer yang tersimpan.', 'badge' => 'Keuangan'],
        ];
    }

    return [$stats, $actions];
}

[$stats, $actions] = dashboard_content($role, $userId);
$currentPage = basename((string) ($_SERVER['PHP_SELF'] ?? 'dashboard.php'));

function dashboard_nav_items($role, $currentPage) {
    $items = [
        ['url' => 'dashboard.php', 'label' => 'Overview', 'hint' => 'Ringkasan akun'],
        ['url' => 'reservasi.php', 'label' => $role === 'pelanggan' ? 'Reservasi' : 'Data Reservasi', 'hint' => 'Booking dan status'],
        ['url' => 'pembayaran.php', 'label' => $role === 'pelanggan' ? 'Pembayaran' : 'Data Pembayaran', 'hint' => 'Riwayat dan verifikasi'],
    ];

    if (role_has_permission($role, 'court.read')) {
        $items[] = ['url' => 'lapangan.php', 'label' => 'Lapangan', 'hint' => 'Master venue'];
    }
    if (role_has_permission($role, 'report.read')) {
        $items[] = ['url' => 'laporan.php', 'label' => 'Laporan', 'hint' => 'Insight operasional'];
    }

    foreach ($items as &$item) {
        $item['active'] = ($currentPage === $item['url']);
    }
    unset($item);

    return $items;
}

$navItems = dashboard_nav_items($role, $currentPage);
$capabilitySummary = [
    'Reservasi' => implode(' / ', array_map(static fn($key) => strtoupper(substr($key, 0, 1)), array_keys(array_filter($capabilities['reservation'] ?? [])))),
    'Pembayaran' => implode(' / ', array_map(static fn($key) => strtoupper(substr($key, 0, 1)), array_keys(array_filter($capabilities['payment'] ?? [])))),
    'Lapangan' => implode(' / ', array_map(static fn($key) => strtoupper(substr($key, 0, 1)), array_keys(array_filter($capabilities['court'] ?? [])))),
    'Laporan' => !empty($capabilities['report']['read']) ? 'R' : '-',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | <?= h($company['short_name']) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../assets/panel-theme.css">
    <style>
        .dashboard-layout {
            display: grid;
            grid-template-columns: 290px minmax(0, 1fr);
            gap: 18px;
            align-items: start;
        }
        .dashboard-sidebar {
            position: sticky;
            top: 18px;
            display: grid;
            gap: 14px;
        }
        .sidebar-card {
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 24px;
            padding: 18px;
            background:
                radial-gradient(circle at top left, rgba(190,242,100,.10), transparent 30%),
                linear-gradient(145deg, rgba(22, 10, 40, .92), rgba(11, 6, 26, .88));
            box-shadow: 0 18px 34px rgba(0,0,0,.24);
        }
        .dashboard-shell {
            display: grid;
            gap: 16px;
        }
        .dashboard-topbar {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            padding: 12px 16px;
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 18px;
            background: rgba(255,255,255,.05);
            backdrop-filter: blur(10px);
        }
        .brand-mini {
            display: inline-flex;
            align-items: center;
            gap: 12px;
        }
        .brand-mini-icon {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #bef264, #22d3ee);
            color: #140019;
            font-size: 20px;
            font-weight: 700;
            box-shadow: 0 12px 24px rgba(34,211,238,.16);
        }
        .brand-mini-copy strong {
            display: block;
            font-size: 14px;
            color: #f8fafc;
        }
        .brand-mini-copy span {
            display: block;
            font-size: 12px;
            color: #9eb0d2;
            margin-top: 2px;
        }
        .sidebar-role-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: fit-content;
            padding: 7px 12px;
            border-radius: 999px;
            background: rgba(34,211,238,.12);
            color: #67e8f9;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
        }
        .sidebar-user {
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px solid rgba(255,255,255,.08);
        }
        .sidebar-user strong {
            display: block;
            font-size: 20px;
            color: #f8fafc;
        }
        .sidebar-user span {
            display: block;
            margin-top: 6px;
            color: #9eb0d2;
            font-size: 13px;
            line-height: 1.7;
            word-break: break-word;
        }
        .sidebar-nav {
            display: grid;
            gap: 10px;
        }
        .sidebar-nav a {
            position: relative;
            display: block;
            text-decoration: none;
            color: inherit;
            padding: 16px 18px 16px 24px;
            border-radius: 18px;
            border: 1px solid rgba(255,255,255,.1);
            background: rgba(255,255,255,.04);
            transition: transform .2s ease, border-color .2s ease, background .2s ease;
        }
        .sidebar-nav a:hover {
            transform: translateX(2px);
            border-color: rgba(34,211,238,.28);
            background: rgba(255,255,255,.06);
        }
        .sidebar-nav a.is-active {
            background: linear-gradient(135deg, rgba(190,242,100,.18), rgba(34,211,238,.20));
            border-color: rgba(190,242,100,.38);
            box-shadow: 0 14px 24px rgba(34,211,238,.14);
        }
        .sidebar-nav a.is-active::before {
            content: "";
            position: absolute;
            left: 8px;
            top: 12px;
            bottom: 12px;
            width: 3px;
            border-radius: 999px;
            background: linear-gradient(180deg, #bef264, #22d3ee);
        }
        .sidebar-nav strong {
            display: block;
            font-size: 15px;
            color: #f8fafc;
        }
        .sidebar-nav span {
            display: block;
            margin-top: 4px;
            font-size: 12px;
            color: #9eb0d2;
        }
        .sidebar-actions {
            display: grid;
            gap: 8px;
        }
        .top-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 0 14px;
            border-radius: 999px;
            text-decoration: none;
            border: 1px solid rgba(255,255,255,.12);
            background: rgba(255,255,255,.04);
            color: #eef2ff;
            font-size: 13px;
            font-weight: 600;
        }
        .top-link.primary {
            background: linear-gradient(135deg, #bef264, #22d3ee);
            color: #140019;
            border-color: transparent;
        }
        .dashboard-hero {
            display: grid;
            grid-template-columns: 1.15fr .85fr;
            gap: 16px;
        }
        .hero-card {
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 24px;
            padding: 22px;
            background:
                radial-gradient(circle at top right, rgba(34,211,238,.14), transparent 34%),
                linear-gradient(145deg, rgba(22, 10, 40, .92), rgba(11, 6, 26, .88));
        }
        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 12px;
            border-radius: 999px;
            background: rgba(190,242,100,.12);
            color: #d9f99d;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .hero-card h1 {
            margin: 16px 0 8px;
            font-size: clamp(34px, 4vw, 48px);
            line-height: 1.04;
            letter-spacing: -.03em;
        }
        .hero-card p {
            margin: 0;
            color: #b8c4df;
            line-height: 1.75;
            font-size: 15px;
            max-width: 640px;
        }
        .identity-grid {
            margin-top: 18px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }
        .identity-item {
            padding: 14px;
            border-radius: 18px;
            border: 1px solid rgba(255,255,255,.1);
            background: rgba(255,255,255,.04);
        }
        .identity-item span {
            display: block;
            color: #91a2c6;
            font-size: 12px;
            margin-bottom: 6px;
        }
        .identity-item strong {
            color: #f8fafc;
            font-size: 15px;
            word-break: break-word;
        }
        .hero-side {
            display: grid;
            gap: 12px;
            align-content: start;
        }
        .hero-note {
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 22px;
            padding: 18px;
            background: rgba(255,255,255,.04);
        }
        .hero-note h3 {
            margin: 0 0 8px;
            font-size: 18px;
        }
        .hero-note p {
            margin: 0;
            color: #aab5d3;
            line-height: 1.75;
            font-size: 14px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
        }
        .stat-card {
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 20px;
            padding: 16px;
            background: rgba(255,255,255,.05);
        }
        .stat-card span {
            display: block;
            color: #91a2c6;
            font-size: 12px;
        }
        .stat-card strong {
            display: block;
            margin-top: 8px;
            font-size: 28px;
            color: #f8fafc;
            font-family: 'Space Grotesk', 'Plus Jakarta Sans', sans-serif;
        }
        .stat-card small {
            display: block;
            margin-top: 6px;
            color: #aab5d3;
            line-height: 1.6;
            font-size: 12px;
        }
        .dashboard-main {
            display: grid;
            grid-template-columns: 1.1fr .9fr;
            gap: 16px;
        }
        .section-card {
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 24px;
            padding: 18px;
            background: rgba(255,255,255,.04);
        }
        .section-card h2 {
            margin: 0;
            font-size: 24px;
        }
        .section-card p.section-subtitle {
            margin: 8px 0 0;
            color: #9eb0d2;
            line-height: 1.7;
        }
        .action-grid {
            margin-top: 16px;
            display: grid;
            gap: 12px;
        }
        .action-card {
            display: block;
            text-decoration: none;
            color: inherit;
            padding: 18px;
            border-radius: 22px;
            border: 1px solid rgba(255,255,255,.12);
            background: linear-gradient(145deg, rgba(31, 16, 54, .94), rgba(15, 8, 31, .9));
            transition: transform .2s ease, border-color .2s ease, box-shadow .2s ease;
        }
        .action-card:hover {
            transform: translateY(-2px);
            border-color: rgba(34,211,238,.32);
            box-shadow: 0 16px 28px rgba(0,0,0,.22);
        }
        .action-badge {
            display: inline-flex;
            padding: 6px 10px;
            border-radius: 999px;
            background: rgba(34,211,238,.14);
            color: #67e8f9;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
        }
        .action-card h3 {
            margin: 14px 0 8px;
            font-size: 24px;
            line-height: 1.2;
        }
        .action-card p {
            margin: 0;
            color: #b6c3de;
            line-height: 1.75;
            font-size: 14px;
        }
        .action-meta {
            margin-top: 14px;
            color: #bef264;
            font-size: 13px;
            font-weight: 700;
        }
        @media (max-width: 1024px) {
            .dashboard-layout {
                grid-template-columns: 1fr;
            }
            .dashboard-sidebar {
                position: static;
            }
            .dashboard-hero,
            .dashboard-main,
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
            .dashboard-hero {
                grid-template-columns: 1fr;
            }
            .dashboard-main {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 760px) {
            .panel-wrap {
                padding: 0 12px 22px;
            }
            .stats-grid,
            .identity-grid {
                grid-template-columns: 1fr;
            }
            .dashboard-topbar,
            .sidebar-actions {
                align-items: stretch;
            }
            .dashboard-topbar {
                align-items: stretch;
            }
        }
    </style>
</head>
<body class="panel-page">
    <div class="panel-wrap">
        <div class="dashboard-layout">
            <aside class="dashboard-sidebar">
                <div class="sidebar-card">
                    <div class="brand-mini">
                        <span class="brand-mini-icon" aria-hidden="true">&#127934;</span>
                        <div class="brand-mini-copy">
                            <strong><?= h($company['short_name']) ?></strong>
                            <span><?= h($company['location']) ?></span>
                        </div>
                    </div>
                    <div class="sidebar-user">
                        <span class="sidebar-role-pill"><?= h($role) ?></span>
                        <strong><?= h($user['name']) ?></strong>
                        <span><?= h($user['email']) ?></span>
                    </div>
                </div>
                <div class="sidebar-card">
                    <div class="sidebar-nav">
                        <?php foreach ($navItems as $item): ?>
                            <a href="<?= h($item['url']) ?>" class="<?= $item['active'] ? 'is-active' : '' ?>">
                                <strong><?= h($item['label']) ?></strong>
                                <span><?= h($item['hint']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="sidebar-card">
                    <div class="sidebar-actions">
                        <?php if ($role === 'pelanggan'): ?>
                            <a href="../index.php" class="top-link">Kembali ke Landing</a>
                        <?php endif; ?>
                        <a href="../actions/logout.php" class="top-link primary">Logout</a>
                    </div>
                </div>
            </aside>

            <div class="dashboard-shell">
                <div class="dashboard-topbar">
                    <div class="brand-mini">
                        <span class="brand-mini-icon" aria-hidden="true">&#10024;</span>
                        <div class="brand-mini-copy">
                            <strong>Overview</strong>
                            <span>Ringkasan akun dan operasional venue</span>
                        </div>
                    </div>
                </div>

                <?php if ($flash): ?>
                    <div class="flash <?= $flash['type'] === 'success' ? 'ok' : 'err' ?>"><?= h($flash['message']) ?></div>
                <?php endif; ?>

                <section class="dashboard-hero">
                    <div class="hero-card">
                        <span class="eyebrow">Dashboard <?= h($role) ?></span>
                        <h1>Area kerja yang lebih rapi untuk <?= h($user['name']) ?></h1>
                        <p>
                            <?php if ($role === 'pelanggan'): ?>
                                Dashboard ini dipakai sebagai area akun untuk <?= h($company['name']) ?>. Landing page tetap fokus untuk informasi venue, sedangkan halaman ini menjadi tempat yang lebih rapi untuk mengelola reservasi, pembayaran, dan aktivitas harianmu.
                            <?php else: ?>
                                Dashboard ini dipakai sebagai area kerja internal untuk <?= h($company['name']) ?>. Dari sini tim venue bisa membuka modul operasional yang relevan tanpa harus kembali ke halaman publik.
                            <?php endif; ?>
                        </p>
                        <div class="identity-grid">
                            <div class="identity-item">
                                <span>Venue</span>
                                <strong><?= h($company['short_name']) ?></strong>
                            </div>
                            <div class="identity-item">
                                <span>Nama</span>
                                <strong><?= h($user['name']) ?></strong>
                            </div>
                            <div class="identity-item">
                                <span>Email</span>
                                <strong><?= h($user['email']) ?></strong>
                            </div>
                            <div class="identity-item">
                                <span>Jam Operasional</span>
                                <strong><?= h($company['hours']) ?></strong>
                            </div>
                            <div class="identity-item">
                                <span>Role Aktif</span>
                                <strong><?= h(ucfirst($role)) ?></strong>
                            </div>
                        </div>
                    </div>
                    <div class="hero-side">
                        <div class="hero-note">
                            <h3>Info Venue</h3>
                            <p><?= h($company['name']) ?> berada di <?= h($company['address']) ?>. Kontak admin utama saat ini: <?= h($company['admin_contact']) ?>.</p>
                        </div>
                        <div class="hero-note">
                            <h3>Fungsi dashboard</h3>
                            <p>Dashboard dipakai sebagai ringkasan, jalur cepat, dan area navigasi akun untuk satu venue. Tugas detail seperti booking, verifikasi, atau pembayaran tetap dikerjakan di halaman modul masing-masing.</p>
                        </div>
                        <div class="hero-note">
                            <h3>Hak Akses CRUD</h3>
                            <p>Reservasi: <?= h($capabilitySummary['Reservasi']) ?> | Pembayaran: <?= h($capabilitySummary['Pembayaran']) ?> | Lapangan: <?= h($capabilitySummary['Lapangan']) ?> | Laporan: <?= h($capabilitySummary['Laporan']) ?></p>
                        </div>
                    </div>
                </section>

                <section class="stats-grid">
                    <?php foreach ($stats as $stat): ?>
                        <div class="stat-card">
                            <span><?= h($stat['label']) ?></span>
                            <strong><?= h(is_int($stat['value']) ? format_short_number($stat['value']) : $stat['value']) ?></strong>
                            <small><?= h($stat['hint']) ?></small>
                        </div>
                    <?php endforeach; ?>
                </section>

                <section class="dashboard-main">
                    <div class="section-card">
                        <h2>Aksi Utama</h2>
                        <p class="section-subtitle">Bagian ini berisi pintasan ke modul yang paling sering dipakai sesuai peran kamu.</p>
                        <div class="action-grid">
                            <?php foreach ($actions as $action): ?>
                                <a class="action-card" href="<?= h($action['url']) ?>">
                                    <span class="action-badge"><?= h($action['badge']) ?></span>
                                    <h3><?= h($action['title']) ?></h3>
                                    <p><?= h($action['desc']) ?></p>
                                    <div class="action-meta">Buka modul &rarr;</div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</body>
</html>

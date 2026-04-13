<?php
session_start();
require_once __DIR__ . '/../core/role_helper.php';
$company = require __DIR__ . '/../data/company.php';

$user = ensure_login_and_role(['admin', 'kasir', 'owner']);
$flash = pull_flash();
$selectedDate = trim((string) ($_GET['tanggal'] ?? date('Y-m-d')));
$selectedMonth = trim((string) ($_GET['bulan'] ?? date('Y-m')));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = date('Y-m-d');
}
if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}
$exportType = trim((string) ($_GET['export'] ?? ''));

$dailyReservationsStmt = db()->prepare('SELECT COUNT(*) FROM reservasi WHERE tanggal_booking = :tanggal');
$dailyReservationsStmt->execute(['tanggal' => $selectedDate]);
$dailyReservations = (int) $dailyReservationsStmt->fetchColumn();

$dailyBookingValueStmt = db()->prepare('SELECT COALESCE(SUM(total_biaya), 0) FROM reservasi WHERE tanggal_booking = :tanggal');
$dailyBookingValueStmt->execute(['tanggal' => $selectedDate]);
$dailyBookingValue = (int) $dailyBookingValueStmt->fetchColumn();

$dailyIncomeStmt = db()->prepare('SELECT COALESCE(SUM(jumlah_bayar), 0) FROM pembayaran WHERE DATE(tanggal_bayar) = :tanggal');
$dailyIncomeStmt->execute(['tanggal' => $selectedDate]);
$dailyIncome = (int) $dailyIncomeStmt->fetchColumn();

$monthlyReservationsStmt = db()->prepare("SELECT COUNT(*) FROM reservasi WHERE DATE_FORMAT(tanggal_booking, '%Y-%m') = :bulan");
$monthlyReservationsStmt->execute(['bulan' => $selectedMonth]);
$monthlyReservations = (int) $monthlyReservationsStmt->fetchColumn();

$monthlyBookingValueStmt = db()->prepare("SELECT COALESCE(SUM(total_biaya), 0) FROM reservasi WHERE DATE_FORMAT(tanggal_booking, '%Y-%m') = :bulan");
$monthlyBookingValueStmt->execute(['bulan' => $selectedMonth]);
$monthlyBookingValue = (int) $monthlyBookingValueStmt->fetchColumn();

$monthlyIncomeStmt = db()->prepare("SELECT COALESCE(SUM(jumlah_bayar), 0) FROM pembayaran WHERE DATE_FORMAT(tanggal_bayar, '%Y-%m') = :bulan");
$monthlyIncomeStmt->execute(['bulan' => $selectedMonth]);
$monthlyIncome = (int) $monthlyIncomeStmt->fetchColumn();

$topCourtsStmt = db()->prepare("
    SELECT
        l.nama_lapangan,
        l.jenis_lantai,
        COUNT(*) AS total_booking,
        COALESCE(SUM(r.durasi), 0) AS total_jam,
        COALESCE(SUM(r.total_biaya), 0) AS total_nilai
    FROM reservasi r
    JOIN lapangan l ON l.id_lapangan = r.id_lapangan
    WHERE DATE_FORMAT(r.tanggal_booking, '%Y-%m') = :bulan
    GROUP BY r.id_lapangan, l.nama_lapangan, l.jenis_lantai
    ORDER BY total_booking DESC, total_jam DESC, l.nama_lapangan ASC
    LIMIT 5
");
$topCourtsStmt->execute(['bulan' => $selectedMonth]);
$topCourts = $topCourtsStmt->fetchAll();

$topCustomersStmt = db()->prepare("
    SELECT
        p.nama_lengkap,
        p.email,
        COUNT(*) AS total_booking,
        COALESCE(SUM(r.total_biaya), 0) AS total_nilai
    FROM reservasi r
    JOIN pengguna p ON p.id_pengguna = r.id_pengguna
    WHERE p.role = 'pelanggan'
      AND DATE_FORMAT(r.tanggal_booking, '%Y-%m') = :bulan
    GROUP BY r.id_pengguna, p.nama_lengkap, p.email
    ORDER BY total_booking DESC, total_nilai DESC, p.nama_lengkap ASC
    LIMIT 5
");
$topCustomersStmt->execute(['bulan' => $selectedMonth]);
$topCustomers = $topCustomersStmt->fetchAll();

$dailyBreakdownStmt = db()->prepare("
    SELECT
        tanggal_booking,
        COUNT(*) AS total_booking,
        COALESCE(SUM(total_biaya), 0) AS total_nilai
    FROM reservasi
    WHERE DATE_FORMAT(tanggal_booking, '%Y-%m') = :bulan
    GROUP BY tanggal_booking
    ORDER BY tanggal_booking DESC
    LIMIT 10
");
$dailyBreakdownStmt->execute(['bulan' => $selectedMonth]);
$dailyBreakdown = $dailyBreakdownStmt->fetchAll();

if ($exportType === 'csv') {
    $fileName = 'laporan-' . $selectedMonth . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');

    $output = fopen('php://output', 'w');
    if ($output === false) {
        exit;
    }

    fwrite($output, "\xEF\xBB\xBF");

    fputcsv($output, ['Laporan', $company['name']]);
    fputcsv($output, ['Lokasi', $company['location']]);
    fputcsv($output, ['Tanggal harian', $selectedDate]);
    fputcsv($output, ['Bulan laporan', $selectedMonth]);
    fputcsv($output, []);

    fputcsv($output, ['Ringkasan Periode', 'Nilai']);
    fputcsv($output, ['Reservasi harian', $dailyReservations]);
    fputcsv($output, ['Nilai booking harian', $dailyBookingValue]);
    fputcsv($output, ['Pemasukan harian', $dailyIncome]);
    fputcsv($output, ['Reservasi bulanan', $monthlyReservations]);
    fputcsv($output, ['Nilai booking bulanan', $monthlyBookingValue]);
    fputcsv($output, ['Pemasukan bulanan', $monthlyIncome]);
    fputcsv($output, ['Selisih booking vs pemasukan', max(0, $monthlyBookingValue - $monthlyIncome)]);
    fputcsv($output, []);

    fputcsv($output, ['Lapangan Paling Sering Dibooking']);
    fputcsv($output, ['Nama Lapangan', 'Jenis Lantai', 'Total Booking', 'Total Jam', 'Total Nilai']);
    foreach ($topCourts as $court) {
        fputcsv($output, [
            $court['nama_lapangan'] ?? '',
            $court['jenis_lantai'] ?? '',
            $court['total_booking'] ?? 0,
            $court['total_jam'] ?? 0,
            $court['total_nilai'] ?? 0,
        ]);
    }
    fputcsv($output, []);

    fputcsv($output, ['Pelanggan Paling Aktif']);
    fputcsv($output, ['Nama Pelanggan', 'Email', 'Total Booking', 'Total Nilai']);
    foreach ($topCustomers as $customer) {
        fputcsv($output, [
            $customer['nama_lengkap'] ?? '',
            $customer['email'] ?? '',
            $customer['total_booking'] ?? 0,
            $customer['total_nilai'] ?? 0,
        ]);
    }
    fputcsv($output, []);

    fputcsv($output, ['Reservasi Harian Dalam Bulan Ini']);
    fputcsv($output, ['Tanggal', 'Total Booking', 'Total Nilai']);
    foreach ($dailyBreakdown as $day) {
        fputcsv($output, [
            $day['tanggal_booking'] ?? '',
            $day['total_booking'] ?? 0,
            $day['total_nilai'] ?? 0,
        ]);
    }

    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan | <?= h($company['short_name']) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../assets/panel-theme.css">
    <style>
        .report-filters {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-top: 14px;
        }
        .report-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-top: 14px;
        }
        .report-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 14px;
        }
        .print-only {
            display: none;
        }
        .report-card {
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 20px;
            background: rgba(255,255,255,.05);
            padding: 16px;
        }
        .report-card span {
            display: block;
            color: #9eb0d2;
            font-size: 12px;
        }
        .report-card strong {
            display: block;
            margin-top: 8px;
            color: #f8fafc;
            font-size: 26px;
            line-height: 1.15;
        }
        .report-card small {
            display: block;
            margin-top: 8px;
            color: #c7d4ea;
            line-height: 1.6;
            font-size: 12px;
        }
        .report-layout {
            display: grid;
            grid-template-columns: 1.1fr .9fr;
            gap: 16px;
        }
        .report-list {
            display: grid;
            gap: 10px;
            margin-top: 14px;
        }
        .report-row {
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 16px;
            background: rgba(255,255,255,.04);
            padding: 14px 16px;
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: flex-start;
        }
        .report-row strong {
            display: block;
            color: #f8fafc;
            font-size: 17px;
        }
        .report-row span {
            display: block;
            margin-top: 5px;
            color: #aab5d3;
            font-size: 13px;
            line-height: 1.7;
        }
        .report-row .metric {
            text-align: right;
            min-width: 150px;
        }
        .report-row .metric strong {
            font-size: 20px;
        }
        .report-empty {
            margin-top: 14px;
            padding: 18px;
            border-radius: 16px;
            border: 1px dashed rgba(255,255,255,.18);
            color: #b8c4df;
            background: rgba(255,255,255,.03);
        }
        @media (max-width: 980px) {
            .report-grid,
            .report-layout {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 680px) {
            .report-filters {
                grid-template-columns: 1fr;
            }
            .report-row {
                flex-direction: column;
            }
            .report-row .metric {
                text-align: left;
                min-width: 0;
            }
        }
        @media print {
            body {
                background: #ffffff !important;
                color: #111827 !important;
            }
            .panel-page,
            .panel-wrap,
            .panel,
            .report-card,
            .report-row,
            .report-empty {
                background: #ffffff !important;
                color: #111827 !important;
                box-shadow: none !important;
            }
            .links-inline,
            .report-actions,
            form.report-filters {
                display: none !important;
            }
            .panel,
            .report-card,
            .report-row,
            .report-empty {
                border-color: #d1d5db !important;
            }
            .meta,
            .panel-subtitle,
            .report-card span,
            .report-card small,
            .report-row span {
                color: #4b5563 !important;
            }
            .report-card strong,
            .report-row strong,
            h2, h3 {
                color: #111827 !important;
            }
            .print-only {
                display: block;
                margin-top: 10px;
                color: #4b5563;
                font-size: 12px;
            }
        }
    </style>
</head>
<body class="panel-page">
    <div class="panel-wrap">
        <div class="panel">
            <div class="panel-title">
                <div>
                    <h2>Laporan <?= h($company['short_name']) ?></h2>
                    <p class="panel-subtitle">Ringkasan reservasi, pemasukan, performa lapangan, dan pelanggan aktif untuk <?= h($company['name']) ?>.</p>
                </div>
                <div class="links-inline">
                    <a class="link-muted" href="dashboard.php">Dashboard</a>
                    <a class="link-muted" href="../actions/logout.php">Logout</a>
                </div>
            </div>
            <p class="meta">Login: <strong><?= h($user['name']) ?></strong> (<?= h($user['role']) ?>) • Venue <?= h($company['location']) ?></p>
            <p class="print-only">Dicetak dari sistem <?= h($company['short_name']) ?> untuk periode harian <?= h($selectedDate) ?> dan bulanan <?= h($selectedMonth) ?>.</p>
            <?php if ($flash): ?>
                <div class="flash <?= $flash['type'] === 'success' ? 'ok' : 'err' ?>"><?= h($flash['message']) ?></div>
            <?php endif; ?>

            <form class="report-filters" method="get">
                <label>
                    <span class="desc-muted">Laporan harian</span>
                    <input class="input" type="date" name="tanggal" value="<?= h($selectedDate) ?>">
                </label>
                <label>
                    <span class="desc-muted">Laporan bulanan</span>
                    <input class="input" type="month" name="bulan" value="<?= h($selectedMonth) ?>">
                </label>
            </form>
            <div class="report-actions">
                <a class="btn" href="?tanggal=<?= urlencode($selectedDate) ?>&bulan=<?= urlencode($selectedMonth) ?>&export=csv">Export CSV</a>
                <button class="btn-outline" type="button" onclick="window.print()">Print Laporan</button>
            </div>
        </div>

        <div class="panel">
            <h3 style="margin-top:0;">Ringkasan Periode</h3>
            <div class="report-grid">
                <div class="report-card">
                    <span>Reservasi pada <?= h($selectedDate) ?></span>
                    <strong><?= h((string) $dailyReservations) ?></strong>
                    <small>Total nilai booking hari itu Rp<?= h(number_format($dailyBookingValue, 0, ',', '.')) ?>.</small>
                </div>
                <div class="report-card">
                    <span>Pemasukan pada <?= h($selectedDate) ?></span>
                    <strong>Rp<?= h(number_format($dailyIncome, 0, ',', '.')) ?></strong>
                    <small>Diambil dari pembayaran yang tercatat pada tanggal tersebut.</small>
                </div>
                <div class="report-card">
                    <span>Reservasi bulan <?= h($selectedMonth) ?></span>
                    <strong><?= h((string) $monthlyReservations) ?></strong>
                    <small>Total nilai booking bulan ini Rp<?= h(number_format($monthlyBookingValue, 0, ',', '.')) ?>.</small>
                </div>
                <div class="report-card">
                    <span>Pemasukan bulan <?= h($selectedMonth) ?></span>
                    <strong>Rp<?= h(number_format($monthlyIncome, 0, ',', '.')) ?></strong>
                    <small>Akumulasi nominal masuk yang sudah tercatat di pembayaran.</small>
                </div>
                <div class="report-card">
                    <span>Selisih booking vs pemasukan</span>
                    <strong>Rp<?= h(number_format(max(0, $monthlyBookingValue - $monthlyIncome), 0, ',', '.')) ?></strong>
                    <small>Membantu melihat sisa nilai booking yang belum terealisasi menjadi pemasukan.</small>
                </div>
                <div class="report-card">
                    <span>Fokus laporan</span>
                    <strong>Single Venue</strong>
                    <small>Semua angka di halaman ini khusus untuk operasional <?= h($company['short_name']) ?>.</small>
                </div>
            </div>
        </div>

        <div class="report-layout">
            <div class="panel">
                <h3 style="margin-top:0;">Lapangan Paling Sering Dibooking</h3>
                <?php if ($topCourts): ?>
                    <div class="report-list">
                        <?php foreach ($topCourts as $court): ?>
                            <div class="report-row">
                                <div>
                                    <strong><?= h($court['nama_lapangan']) ?></strong>
                                    <span><?= h($court['jenis_lantai']) ?> • Nilai booking Rp<?= h(number_format((int) $court['total_nilai'], 0, ',', '.')) ?></span>
                                </div>
                                <div class="metric">
                                    <strong><?= h((string) $court['total_booking']) ?>x</strong>
                                    <span><?= h((string) $court['total_jam']) ?> jam terpakai</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="report-empty">Belum ada data booking untuk bulan yang dipilih.</div>
                <?php endif; ?>
            </div>

            <div class="panel">
                <h3 style="margin-top:0;">Pelanggan Paling Aktif</h3>
                <?php if ($topCustomers): ?>
                    <div class="report-list">
                        <?php foreach ($topCustomers as $customer): ?>
                            <div class="report-row">
                                <div>
                                    <strong><?= h($customer['nama_lengkap']) ?></strong>
                                    <span><?= h($customer['email']) ?></span>
                                </div>
                                <div class="metric">
                                    <strong><?= h((string) $customer['total_booking']) ?> booking</strong>
                                    <span>Nilai Rp<?= h(number_format((int) $customer['total_nilai'], 0, ',', '.')) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="report-empty">Belum ada pelanggan aktif untuk bulan yang dipilih.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="panel">
            <h3 style="margin-top:0;">Reservasi Harian Dalam Bulan Ini</h3>
            <?php if ($dailyBreakdown): ?>
                <div class="report-list">
                    <?php foreach ($dailyBreakdown as $day): ?>
                        <div class="report-row">
                            <div>
                                <strong><?= h((string) $day['tanggal_booking']) ?></strong>
                                <span>Aktivitas reservasi harian di <?= h($company['short_name']) ?></span>
                            </div>
                            <div class="metric">
                                <strong><?= h((string) $day['total_booking']) ?> booking</strong>
                                <span>Rp<?= h(number_format((int) $day['total_nilai'], 0, ',', '.')) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="report-empty">Belum ada reservasi pada bulan yang dipilih.</div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

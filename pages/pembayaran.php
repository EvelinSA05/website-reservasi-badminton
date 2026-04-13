<?php
require_once __DIR__ . '/../core/role_helper.php';
$company = require __DIR__ . '/../data/company.php';

date_default_timezone_set('Asia/Jakarta');

$user = ensure_login_and_role(['admin', 'kasir', 'owner', 'pelanggan']);
$flash = pull_flash();
$isPelanggan = $user['role'] === 'pelanggan';
$canViewAllPayments = user_has_permission($user, 'payment.read_all');
$canCreateOwnPayment = user_has_permission($user, 'payment.create_own');
$canCreateInternalPayment = user_has_permission($user, 'payment.create_internal');
$canEdit = user_has_permission($user, 'payment.update');
$canDeletePayment = user_has_permission($user, 'payment.delete');
$canCreatePayment = $canCreateOwnPayment || $canCreateInternalPayment;
const BUKTI_UPLOAD_DIR = __DIR__ . '/../uploads/bukti';
const BUKTI_DB_PREFIX = 'uploads/bukti/';
$nowPaymentValue = date('Y-m-d\TH:i');
$nowPaymentDisplay = date('d-m-Y H:i') . ' WIB';
$venueTransferAccounts = [
    ['bank' => 'BCA', 'number' => '1234567890', 'holder' => 'Sony Dwi Kuncoro Badminton Hall'],
    ['bank' => 'Mandiri', 'number' => '9876543210', 'holder' => 'Sony Dwi Kuncoro Badminton Hall'],
];

function ensure_payment_verification_columns() {
    static $done = false;
    if ($done) {
        return;
    }

    $existing = [];
    foreach (db()->query("SHOW COLUMNS FROM pembayaran")->fetchAll() as $column) {
        $existing[(string) ($column['Field'] ?? '')] = true;
    }

    if (!isset($existing['verified_at'])) {
        db()->exec("ALTER TABLE pembayaran ADD COLUMN verified_at DATETIME NULL AFTER id_admin_verifikasi");
    }
    if (!isset($existing['verification_note'])) {
        db()->exec("ALTER TABLE pembayaran ADD COLUMN verification_note TEXT NULL AFTER verified_at");
    }

    $done = true;
}

function payment_status_meta($status) {
    $normalized = strtolower(trim((string) $status));
    if ($normalized === 'dp') {
        return ['class' => 'dp', 'label' => 'DP'];
    }
    if ($normalized === 'lunas') {
        return ['class' => 'lunas', 'label' => 'Lunas'];
    }
    return ['class' => 'pending', 'label' => 'Pending'];
}

function payment_verification_payload($status, $userId, $note) {
    $normalized = strtolower(trim((string) $status));
    $cleanNote = trim((string) $note);
    if ($normalized === 'pending') {
        return [
            'admin_id' => null,
            'verified_at' => null,
            'note' => $cleanNote !== '' ? $cleanNote : null,
        ];
    }

    return [
        'admin_id' => $userId,
        'verified_at' => date('Y-m-d H:i:s'),
        'note' => $cleanNote !== '' ? $cleanNote : null,
    ];
}

function format_verification_datetime($value) {
    $raw = trim((string) $value);
    if ($raw === '') {
        return '-';
    }
    $timestamp = strtotime($raw);
    if ($timestamp === false) {
        return $raw;
    }
    return date('d-m-Y H:i', $timestamp) . ' WIB';
}

function generate_transfer_unique_code($reservationId, $paymentPlan) {
    $seed = strtoupper(trim((string) $reservationId)) . '|' . strtolower(trim((string) $paymentPlan));
    $sum = 0;
    foreach (str_split($seed) as $char) {
        $sum += ord($char);
    }
    return 100 + ($sum % 900);
}

ensure_payment_verification_columns();

function pembayaran_feedback_box($flash, $role) {
    if (!$flash || !is_array($flash)) {
        return null;
    }

    $message = (string) ($flash['message'] ?? '');
    $type = (string) ($flash['type'] ?? 'info');
    $isPelanggan = ($role === 'pelanggan');

    if ($type === 'success' && $isPelanggan && str_contains($message, 'Pembayaran berhasil ditambahkan')) {
        return [
            'tone' => 'success',
            'title' => 'Bukti pembayaran berhasil dikirim',
            'body' => 'Pembayaranmu sudah masuk ke sistem dengan status pending dan akan dicek oleh admin atau kasir venue.',
            'steps' => [
                'Pantau status pembayaran pada tabel riwayat di bawah halaman ini.',
                'Tunggu admin atau kasir memverifikasi bukti yang kamu unggah.',
                'Kembali ke reservasi untuk memastikan booking terkait tetap terpantau.',
            ],
        ];
    }

    if ($type === 'success' && !$isPelanggan && str_contains($message, 'Pembayaran berhasil diupdate')) {
        return [
            'tone' => 'success',
            'title' => 'Status pembayaran sudah diperbarui',
            'body' => 'Perubahan pembayaran sudah tersimpan dan akan memengaruhi status tagihan pada reservasi terkait.',
            'steps' => [
                'Pastikan status DP atau lunas sudah sesuai dengan nominal yang diterima.',
                'Cek kembali halaman reservasi bila pelanggan perlu melihat status terbaru.',
            ],
        ];
    }

    if ($type === 'error') {
        return [
            'tone' => 'error',
            'title' => 'Aksi pembayaran belum berhasil',
            'body' => $message,
            'steps' => [
                'Periksa pilihan reservasi, jenis pembayaran, dan bukti transfer yang diunggah.',
                'Pastikan sisa tagihan masih tersedia untuk dibayar.',
            ],
        ];
    }

    return [
        'tone' => $type === 'success' ? 'success' : 'info',
        'title' => $type === 'success' ? 'Update pembayaran berhasil' : 'Informasi pembayaran',
        'body' => $message,
        'steps' => [],
    ];
}

function ensure_bukti_upload_dir() {
    if (!is_dir(BUKTI_UPLOAD_DIR)) {
        @mkdir(BUKTI_UPLOAD_DIR, 0777, true);
    }
    if (!is_dir(BUKTI_UPLOAD_DIR)) {
        throw new RuntimeException('Folder upload bukti tidak dapat dibuat.');
    }
}

function handle_bukti_upload($fileFieldName, $fallback = '-') {
    if (!isset($_FILES[$fileFieldName]) || !is_array($_FILES[$fileFieldName])) {
        return $fallback;
    }

    $file = $_FILES[$fileFieldName];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $fallback;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload bukti gagal. Coba ulangi lagi.');
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > 3 * 1024 * 1024) {
        throw new RuntimeException('Ukuran foto bukti maksimal 3MB.');
    }

    $tmpPath = (string) ($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        throw new RuntimeException('File upload tidak valid.');
    }

    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = (string) finfo_file($finfo, $tmpPath);
            finfo_close($finfo);
        }
    }
    if ($mime === '' && function_exists('exif_imagetype')) {
        $imgType = @exif_imagetype($tmpPath);
        $map = [
            IMAGETYPE_JPEG => 'image/jpeg',
            IMAGETYPE_PNG => 'image/png',
            IMAGETYPE_WEBP => 'image/webp'
        ];
        if ($imgType && isset($map[$imgType])) {
            $mime = $map[$imgType];
        }
    }
    if ($mime === '' && function_exists('getimagesize')) {
        $imageInfo = @getimagesize($tmpPath);
        if (is_array($imageInfo) && isset($imageInfo['mime'])) {
            $mime = (string) $imageInfo['mime'];
        }
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Format bukti harus JPG, PNG, atau WEBP.');
    }

    ensure_bukti_upload_dir();
    $fileName = 'bukti_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $target = BUKTI_UPLOAD_DIR . '/' . $fileName;

    if (!move_uploaded_file($tmpPath, $target)) {
        throw new RuntimeException('Gagal menyimpan foto bukti.');
    }

    return BUKTI_DB_PREFIX . $fileName;
}

function ensure_uploaded_bukti_exists($fileFieldName) {
    if (
        !isset($_FILES[$fileFieldName]) ||
        !is_array($_FILES[$fileFieldName]) ||
        (int) ($_FILES[$fileFieldName]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
    ) {
        throw new RuntimeException('Bukti pembayaran wajib diunggah.');
    }
}

function delete_bukti_file($storedPath) {
    if (!is_string($storedPath) || $storedPath === '' || $storedPath === '-') {
        return;
    }
    if (strpos($storedPath, BUKTI_DB_PREFIX) !== 0) {
        return;
    }
    $fileName = basename($storedPath);
    $fullPath = BUKTI_UPLOAD_DIR . '/' . $fileName;
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canCreatePayment) {
        redirect_with_flash('pembayaran.php', 'error', 'Role Anda hanya dapat melihat data pembayaran.');
    }

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $id = next_prefixed_id('pembayaran', 'id_pembayaran', 'PY');
            $idReservasi = trim($_POST['id_reservasi'] ?? '');
            $metode = trim($_POST['metode_pembayaran'] ?? '');
            $jumlah = 0.0;
            $tanggal = date('Y-m-d H:i:s');
            $status = $isPelanggan ? 'pending' : trim($_POST['status_pembayaran'] ?? 'pending');
            $paymentPlan = trim((string) ($_POST['payment_plan'] ?? 'lunas'));
            $verificationNote = trim((string) ($_POST['verification_note'] ?? ''));
            ensure_uploaded_bukti_exists('bukti_pembayaran_file');
            $bukti = handle_bukti_upload('bukti_pembayaran_file', '-');
            $verificationPayload = $canEdit
                ? payment_verification_payload($status, (string) $user['user_id'], $verificationNote)
                : ['admin_id' => null, 'verified_at' => null, 'note' => null];

            $reservationValidationStmt = db()->prepare('SELECT id_reservasi, id_pengguna, id_lapangan, tanggal_booking, jam_mulai, jam_selesai, status_reservasi FROM reservasi WHERE id_reservasi = :id LIMIT 1');
            $reservationValidationStmt->execute(['id' => $idReservasi]);
            $reservationForPayment = $reservationValidationStmt->fetch();
            if (!$reservationForPayment) {
                redirect_with_flash('pembayaran.php', 'error', 'Reservasi yang akan dibayar tidak ditemukan.');
            }
            if ($isPelanggan && (string) ($reservationForPayment['id_pengguna'] ?? '') !== (string) $user['user_id']) {
                redirect_with_flash('pembayaran.php', 'error', 'Reservasi tidak ditemukan untuk akun Anda.');
            }
            if (strtolower((string) ($reservationForPayment['status_reservasi'] ?? '')) === 'dibatalkan') {
                redirect_with_flash('pembayaran.php', 'error', 'Reservasi ini sudah dibatalkan sehingga pembayaran tidak dapat diproses.');
            }
            ensure_reservation_schedule_is_available(
                $reservationForPayment['id_lapangan'] ?? '',
                $reservationForPayment['tanggal_booking'] ?? '',
                substr((string) ($reservationForPayment['jam_mulai'] ?? ''), 0, 5),
                substr((string) ($reservationForPayment['jam_selesai'] ?? ''), 0, 5),
                $idReservasi
            );

            $stmt = db()->prepare('SELECT total_biaya FROM reservasi WHERE id_reservasi = :id LIMIT 1');
            $stmt->execute(['id' => $idReservasi]);
            $totalBiayaReservasi = $stmt->fetchColumn();
            if ($totalBiayaReservasi === false) {
                redirect_with_flash('pembayaran.php', 'error', 'Reservasi tidak ditemukan untuk proses pembayaran.');
            }

            $stmt = db()->prepare('SELECT COALESCE(SUM(jumlah_bayar), 0) FROM pembayaran WHERE id_reservasi = :id');
            $stmt->execute(['id' => $idReservasi]);
            $totalSudahDibayar = (float) $stmt->fetchColumn();

            $totalBiayaReservasi = (float) $totalBiayaReservasi;
            $sisaTagihan = max(0, $totalBiayaReservasi - $totalSudahDibayar);
            $targetDp = ceil($totalBiayaReservasi / 2);
            $sisaDp = max(0, $targetDp - $totalSudahDibayar);

            if ($isPelanggan) {
                $stmt = db()->prepare('SELECT id_reservasi FROM reservasi WHERE id_reservasi = :id AND id_pengguna = :user_id LIMIT 1');
                $stmt->execute([
                    'id' => $idReservasi,
                    'user_id' => $user['user_id']
                ]);
                $reservationOwned = $stmt->fetchColumn();
                if ($reservationOwned === false) {
                    redirect_with_flash('pembayaran.php', 'error', 'Reservasi tidak ditemukan untuk akun Anda.');
                }

                if ($paymentPlan === 'dp50') {
                    $jumlah = min($sisaTagihan, $sisaDp);
                } else {
                    $jumlah = $sisaTagihan;
                }

                if (strcasecmp($metode, 'Transfer') === 0) {
                    $jumlah += generate_transfer_unique_code($idReservasi, $paymentPlan);
                }

                if ($paymentPlan === 'dp50' && $sisaDp <= 0) {
                    redirect_with_flash('pembayaran.php', 'error', 'DP 50% untuk reservasi ini sudah terpenuhi. Silakan pilih pelunasan untuk membayar sisa tagihan.');
                }

                if ($jumlah <= 0) {
                    $errorMessage = $paymentPlan === 'dp50'
                        ? 'Reservasi ini sudah memenuhi DP 50% atau sudah lunas. Pilih pelunasan jika masih ada sisa tagihan.'
                        : 'Reservasi ini sudah lunas dan tidak perlu pembayaran tambahan.';
                    redirect_with_flash('pembayaran.php', 'error', $errorMessage);
                }
            } else {
                $jumlah = $sisaTagihan;
                if (strcasecmp($metode, 'Transfer') === 0) {
                    $jumlah += generate_transfer_unique_code($idReservasi, $paymentPlan);
                }
                if ($jumlah <= 0) {
                    redirect_with_flash('pembayaran.php', 'error', 'Reservasi ini sudah lunas dan tidak perlu pembayaran tambahan.');
                }
            }

            $stmt = db()->prepare('INSERT INTO pembayaran (id_pembayaran, id_reservasi, metode_pembayaran, jumlah_bayar, tanggal_bayar, status_pembayaran, bukti_pembayaran, id_admin_verifikasi, verified_at, verification_note) VALUES (:id, :id_res, :metode, :jumlah, :tanggal, :status, :bukti, :admin, :verified_at, :verification_note)');
            $stmt->execute([
                'id' => $id,
                'id_res' => $idReservasi,
                'metode' => $metode,
                'jumlah' => $jumlah,
                'tanggal' => $tanggal,
                'status' => $status,
                'bukti' => $bukti,
                'admin' => $verificationPayload['admin_id'],
                'verified_at' => $verificationPayload['verified_at'],
                'verification_note' => $verificationPayload['note'],
            ]);
            redirect_with_flash('pembayaran.php', 'success', 'Pembayaran berhasil ditambahkan.');
        }

        if ($action === 'update') {
            if (!$canEdit) {
                redirect_with_flash('pembayaran.php', 'error', 'Anda tidak memiliki izin untuk mengubah pembayaran.');
            }
            $id = trim($_POST['id_pembayaran'] ?? '');
            $metode = trim($_POST['metode_pembayaran'] ?? '');
            $jumlah = (float) ($_POST['jumlah_bayar'] ?? 0);
            $tanggal = trim($_POST['tanggal_bayar'] ?? '');
            $status = trim($_POST['status_pembayaran'] ?? 'pending');
            $verificationNote = trim((string) ($_POST['verification_note'] ?? ''));
            $oldBukti = trim($_POST['old_bukti_pembayaran'] ?? '-');
            $bukti = handle_bukti_upload('bukti_pembayaran_file', $oldBukti === '' ? '-' : $oldBukti);
            if ($bukti !== $oldBukti) {
                delete_bukti_file($oldBukti);
            }
            $verificationPayload = payment_verification_payload($status, (string) $user['user_id'], $verificationNote);

            $stmt = db()->prepare('UPDATE pembayaran SET metode_pembayaran=:metode, jumlah_bayar=:jumlah, tanggal_bayar=:tanggal, status_pembayaran=:status, bukti_pembayaran=:bukti, id_admin_verifikasi=:admin, verified_at=:verified_at, verification_note=:verification_note WHERE id_pembayaran=:id');
            $stmt->execute([
                'id' => $id,
                'metode' => $metode,
                'jumlah' => $jumlah,
                'tanggal' => str_replace('T', ' ', $tanggal) . ':00',
                'status' => $status,
                'bukti' => $bukti,
                'admin' => $verificationPayload['admin_id'],
                'verified_at' => $verificationPayload['verified_at'],
                'verification_note' => $verificationPayload['note'],
            ]);
            redirect_with_flash('pembayaran.php', 'success', 'Pembayaran berhasil diupdate.');
        }

        if ($action === 'delete') {
            if (!$canEdit) {
                redirect_with_flash('pembayaran.php', 'error', 'Anda tidak memiliki izin untuk menghapus pembayaran.');
            }
            $id = trim($_POST['id_pembayaran'] ?? '');
            $stmt = db()->prepare('SELECT bukti_pembayaran FROM pembayaran WHERE id_pembayaran=:id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $oldBukti = $stmt->fetchColumn();
            $stmt = db()->prepare('DELETE FROM pembayaran WHERE id_pembayaran=:id');
            $stmt->execute(['id' => $id]);
            delete_bukti_file(is_string($oldBukti) ? $oldBukti : '');
            redirect_with_flash('pembayaran.php', 'success', 'Pembayaran berhasil dihapus.');
        }
    } catch (Throwable $e) {
        redirect_with_flash('pembayaran.php', 'error', 'Operasi pembayaran gagal: ' . $e->getMessage());
    }
}

$selectedReservasiId = trim((string) ($_GET['reservasi'] ?? ''));

if ($canViewAllPayments) {
    $reservasiList = db()->query('SELECT r.id_reservasi, r.tanggal_booking, r.total_biaya, r.status_reservasi, p.nama_lengkap FROM reservasi r JOIN pengguna p ON p.id_pengguna=r.id_pengguna ORDER BY r.id_reservasi DESC')->fetchAll();
    $payRows = db()->query('SELECT py.*, r.id_pengguna, u.nama_lengkap, verifier.nama_lengkap AS verifier_name FROM pembayaran py JOIN reservasi r ON r.id_reservasi=py.id_reservasi JOIN pengguna u ON u.id_pengguna=r.id_pengguna LEFT JOIN pengguna verifier ON verifier.id_pengguna = py.id_admin_verifikasi ORDER BY py.tanggal_bayar DESC')->fetchAll();
} else {
    $stmt = db()->prepare('SELECT r.id_reservasi, r.tanggal_booking, r.total_biaya, r.status_reservasi, p.nama_lengkap FROM reservasi r JOIN pengguna p ON p.id_pengguna=r.id_pengguna WHERE r.id_pengguna = :user_id ORDER BY r.id_reservasi DESC');
    $stmt->execute(['user_id' => $user['user_id']]);
    $reservasiList = $stmt->fetchAll();

    $stmt = db()->prepare('SELECT py.*, r.id_pengguna, u.nama_lengkap, verifier.nama_lengkap AS verifier_name FROM pembayaran py JOIN reservasi r ON r.id_reservasi=py.id_reservasi JOIN pengguna u ON u.id_pengguna=r.id_pengguna LEFT JOIN pengguna verifier ON verifier.id_pengguna = py.id_admin_verifikasi WHERE r.id_pengguna = :user_id ORDER BY py.tanggal_bayar DESC');
    $stmt->execute(['user_id' => $user['user_id']]);
    $payRows = $stmt->fetchAll();
}

$totalPembayaran = count($payRows);
$totalPending = 0;
$totalDp = 0;
$totalLunas = 0;
$nominalMasuk = 0;
foreach ($payRows as $row) {
    $nominalMasuk += (float) ($row['jumlah_bayar'] ?? 0);
    $status = strtolower((string) ($row['status_pembayaran'] ?? ''));
    if ($status === 'pending') {
        $totalPending++;
    } elseif ($status === 'dp') {
        $totalDp++;
    } elseif ($status === 'lunas') {
        $totalLunas++;
    }
}
$pembayaranFeedback = pembayaran_feedback_box($flash, (string) $user['role']);

$paymentMap = [];
foreach ($payRows as $row) {
    $reservationId = (string) ($row['id_reservasi'] ?? '');
    if ($reservationId === '') {
        continue;
    }
    if (!isset($paymentMap[$reservationId])) {
        $paymentMap[$reservationId] = 0.0;
    }
    $paymentMap[$reservationId] += (float) ($row['jumlah_bayar'] ?? 0);
}

$reservasiBillingRows = [];
foreach ($reservasiList as $reservation) {
    $reservationId = (string) ($reservation['id_reservasi'] ?? '');
    $totalTagihan = (float) ($reservation['total_biaya'] ?? 0);
    $totalBayar = (float) ($paymentMap[$reservationId] ?? 0);
    $sisa = max(0, $totalTagihan - $totalBayar);

    if ($totalBayar <= 0) {
        $billingStatus = 'belum-bayar';
        $billingLabel = 'Belum Bayar';
    } elseif ($totalBayar < $totalTagihan) {
        $billingStatus = 'dp';
        $billingLabel = 'DP';
    } else {
        $billingStatus = 'lunas';
        $billingLabel = 'Lunas';
    }

    $reservasiBillingRows[] = [
        'id_reservasi' => $reservationId,
        'tanggal_booking' => $reservation['tanggal_booking'] ?? '',
        'status_reservasi' => $reservation['status_reservasi'] ?? '',
        'nama_lengkap' => $reservation['nama_lengkap'] ?? '',
        'total_biaya' => $totalTagihan,
        'total_bayar' => $totalBayar,
        'sisa' => $sisa,
        'billing_status' => $billingStatus,
        'billing_label' => $billingLabel,
    ];
}

$payableReservasiRows = array_values(array_filter($reservasiBillingRows, static function (array $row): bool {
    return (float) ($row['sisa'] ?? 0) > 0;
}));

$selectedReservationStillPayable = false;
foreach ($payableReservasiRows as $row) {
    if ($selectedReservasiId !== '' && $selectedReservasiId === (string) ($row['id_reservasi'] ?? '')) {
        $selectedReservationStillPayable = true;
        break;
    }
}

if (!$selectedReservationStillPayable) {
    $selectedReservasiId = '';
}

if ($isPelanggan && $selectedReservasiId === '' && !empty($payableReservasiRows)) {
    $selectedReservasiId = (string) ($payableReservasiRows[0]['id_reservasi'] ?? '');
}

$selectedReservationSummary = null;
foreach ($payableReservasiRows as $row) {
    if ($selectedReservasiId !== '' && $selectedReservasiId === (string) ($row['id_reservasi'] ?? '')) {
        $selectedReservationSummary = $row;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran | <?= h($company['short_name']) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../assets/panel-theme.css">
    <style>
        [hidden] { display: none !important; }
        .pay-note { color:#aab5d3; font-size: 12px; margin-top: 4px; display: block; }
        .cell-actions { display:grid; gap:8px; max-width:220px; }
        .stats {
            display:grid;
            grid-template-columns: repeat(4, 1fr);
            gap:10px;
            margin-top:12px;
        }
        .stat {
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,.12);
            background: rgba(255,255,255,.04);
            padding: 12px;
        }
        .stat-label { font-size:12px; color:#96a0bc; margin-bottom:5px; }
        .stat-value { font-size:20px; font-weight:700; color:#f0f5ff; }
        .payment-builder {
            display:grid;
            grid-template-columns: minmax(0, 1.08fr) minmax(340px, .92fr);
            gap: 18px;
            align-items:start;
        }
        .pay-card {
            border:1px solid rgba(255,255,255,.12);
            border-radius: 20px;
            background: linear-gradient(180deg, rgba(255,255,255,.05), rgba(255,255,255,.03));
            padding: 18px;
        }
        .pay-card h4 { margin:0; font-size:17px; }
        .payment-form-card,
        .payment-side-card {
            display: grid;
            gap: 14px;
        }
        .payment-side-card {
            position: sticky;
            top: 18px;
        }
        .payment-preview-head {
            display:grid;
            gap:6px;
            padding-bottom: 4px;
        }
        .payment-preview-kicker {
            color:#67e8f9;
            font-size:11px;
            font-weight:700;
            letter-spacing:.12em;
            text-transform:uppercase;
        }
        .payment-preview-copy {
            color:#aab5d3;
            font-size:12px;
            line-height:1.75;
        }
        .payment-field-grid {
            display:grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap:12px;
        }
        .reservation-selection {
            display: grid;
            gap: 12px;
        }
        .reservation-locked {
            min-height: 48px;
            display: flex;
            align-items: center;
            padding: 0 16px;
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,.14);
            background: rgba(255,255,255,.05);
            color: #f8fbff;
            font-weight: 700;
            font-size: 15px;
        }
        .reservation-locked.is-empty {
            color: #94a3b8;
            font-weight: 600;
        }
        .reservation-summary-grid {
            display: grid;
            grid-template-columns: 1.35fr .8fr .85fr;
            gap: 10px;
        }
        .reservation-summary-item {
            display: grid;
            gap: 6px;
            min-height: 82px;
            padding: 12px 14px;
            border-radius: 14px;
            background: rgba(255,255,255,.04);
            border: 1px solid rgba(255,255,255,.08);
            align-content: start;
        }
        .reservation-summary-item span {
            color:#aebcda;
            font-size:12px;
        }
        .reservation-summary-item strong {
            color:#f8fbff;
            font-size:16px;
            line-height:1.4;
            word-break: break-word;
        }
        .pay-section {
            display:grid;
            gap:12px;
            padding: 14px;
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,.08);
            background: rgba(255,255,255,.03);
        }
        .pay-section-head {
            display:grid;
            gap:4px;
        }
        .pay-section-copy {
            color:#aab5d3;
            font-size:12px;
            line-height:1.7;
        }
        .payment-submit-row {
            display:flex;
            justify-content:flex-start;
            padding-top: 2px;
        }
        .payment-submit-row .btn {
            min-width: 190px;
        }
        .method-grid,
        .plan-grid,
        .status-grid {
            display:grid;
            grid-template-columns: repeat(3, 1fr);
            gap:10px;
        }
        .method-btn,
        .plan-btn,
        .status-btn {
            border:1px solid rgba(255,255,255,.14);
            border-radius: 14px;
            background: rgba(255,255,255,.05);
            color:#eef4ff;
            padding: 12px;
            text-align:left;
            cursor:pointer;
            font: 600 13px 'Plus Jakarta Sans', sans-serif;
        }
        .method-btn strong,
        .plan-btn strong,
        .status-btn strong {
            display:block;
            font-size: 14px;
            margin-bottom:4px;
        }
        .method-btn span,
        .plan-btn span,
        .status-btn span {
            display:block;
            color:#9eb0d2;
            font-size:12px;
        }
        .method-btn.active,
        .plan-btn.active,
        .status-btn.active {
            background: linear-gradient(135deg, rgba(34,211,238,.18), rgba(124,58,237,.22));
            border-color: rgba(34,211,238,.34);
            box-shadow: 0 10px 24px rgba(34,211,238,.10);
        }
        .plan-btn:disabled {
            cursor: not-allowed;
            opacity: .48;
            border-color: rgba(255,255,255,.08);
            background: rgba(255,255,255,.03);
            box-shadow: none;
        }
        .payment-preview {
            display:grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap:10px;
            color:#d6e0fb;
            font-size:13px;
        }
        .payment-preview strong {
            color:#f7fcff;
            font-size:15px;
            line-height:1.45;
            word-break:break-word;
        }
        .payment-preview-row {
            display:grid;
            gap:6px;
            align-content:start;
            padding:12px 13px;
            border-radius: 12px;
            background: rgba(255,255,255,.04);
            border:1px solid rgba(255,255,255,.08);
            min-height:82px;
        }
        .payment-preview-row span {
            color:#aebcda;
            font-size:12px;
        }
        .payment-preview-row.is-highlight {
            background: linear-gradient(135deg, rgba(34,211,238,.12), rgba(124,58,237,.14));
            border-color: rgba(34,211,238,.22);
        }
        .payment-preview-row.is-wide {
            grid-column: 1 / -1;
            min-height: auto;
        }
        .preview-badge {
            display:inline-flex;
            align-items:center;
            justify-content:center;
            width:fit-content;
            padding:7px 12px;
            border-radius:999px;
            font-size:12px;
            font-weight:700;
            line-height:1;
            border:1px solid rgba(255,255,255,.1);
        }
        .preview-badge.pending {
            background: rgba(251, 191, 36, .16);
            color:#fde68a;
            border-color: rgba(251, 191, 36, .28);
        }
        .preview-badge.dp {
            background: rgba(59, 130, 246, .16);
            color:#bfdbfe;
            border-color: rgba(59, 130, 246, .26);
        }
        .preview-badge.lunas {
            background: rgba(34, 197, 94, .16);
            color:#bbf7d0;
            border-color: rgba(34, 197, 94, .26);
        }
        .preview-badge.neutral {
            background: rgba(255,255,255,.06);
            color:#e5eefc;
            border-color: rgba(255,255,255,.12);
        }
        .payment-method-card {
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 18px;
            background: linear-gradient(145deg, rgba(15,23,42,.46), rgba(49,18,85,.42));
            padding: 16px;
            display: grid;
            gap: 14px;
        }
        .payment-method-card h5 {
            margin: 0;
            font-size: 16px;
            color: #f8fafc;
        }
        .payment-method-copy {
            color: #c7d4ea;
            font-size: 12.5px;
            line-height: 1.75;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: flex-start;
            gap: 18px;
            padding-left: 8px;
        }
        .payment-method-copy p {
            margin: 0;
            max-width: 34ch;
        }
        .payment-qr-wrap {
            display: grid;
            grid-template-columns: 124px minmax(0, 1fr);
            gap: 26px;
            align-items: center;
        }
        .payment-qr-wrap img {
            width: 124px;
            height: 124px;
            border-radius: 20px;
            background: #fff;
            padding: 12px;
        }
        .payment-bank-list {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }
        .payment-bank-item,
        .payment-highlight-box {
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 14px;
            background: rgba(255,255,255,.04);
            padding: 12px;
            min-height: 92px;
        }
        .payment-bank-item strong,
        .payment-highlight-box strong {
            display: block;
            color: #f8fafc;
            font-size: 13px;
        }
        .payment-bank-item span,
        .payment-highlight-box span {
            display: block;
            margin-top: 5px;
            color: #c7d4ea;
            font-size: 12px;
            line-height: 1.7;
        }
        .payment-amount-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 7px 12px;
            border-radius: 999px;
            background: rgba(34,211,238,.16);
            color: #a5f3fc;
            font-size: 12px;
            font-weight: 700;
            width: fit-content;
        }
        .status-pill {
            display:inline-flex;
            align-items:center;
            justify-content:center;
            border-radius:999px;
            padding:4px 10px;
            font-size:11px;
            font-weight:700;
        }
        .status-pill.pending { background: rgba(251, 191, 36, .18); color:#fde68a; }
        .status-pill.dp { background: rgba(59, 130, 246, .18); color:#bfdbfe; }
        .status-pill.lunas { background: rgba(34, 197, 94, .18); color:#bbf7d0; }
        .status-pill.belum-bayar { background: rgba(244, 63, 94, .18); color:#fecdd3; }
        .verify-pill {
            display:inline-flex;
            align-items:center;
            justify-content:center;
            border-radius:999px;
            padding:4px 10px;
            font-size:11px;
            font-weight:700;
            margin-top:6px;
        }
        .verify-pill.unverified { background: rgba(244, 63, 94, .14); color:#fecdd3; }
        .verify-pill.verified { background: rgba(34, 197, 94, .14); color:#bbf7d0; }
        .verify-stack {
            display: grid;
            gap: 6px;
            margin-top: 8px;
        }
        .verify-meta {
            color: #b8c7df;
            font-size: 12px;
            line-height: 1.7;
        }
        .verify-note {
            margin-top: 6px;
            padding: 10px 12px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,.08);
            background: rgba(255,255,255,.04);
            color: #d7e2f5;
            font-size: 12px;
            line-height: 1.7;
        }
        .method-pill {
            display:inline-flex;
            align-items:center;
            border-radius:999px;
            padding:4px 10px;
            font-size:11px;
            font-weight:700;
            background: rgba(255,255,255,.08);
            color:#ecf4ff;
        }
        textarea {
            width: 100%;
            min-height: 88px;
            border: 1px solid rgba(255,255,255,.12);
            background: rgba(255,255,255,.06);
            color: #f8fafc;
            border-radius: 14px;
            padding: 12px 14px;
            resize: vertical;
            font: inherit;
        }
        .billing-list {
            display:grid;
            gap:10px;
        }
        .billing-card {
            border:1px solid rgba(255,255,255,.12);
            border-radius:14px;
            background: rgba(255,255,255,.04);
            padding:14px;
            display:grid;
            grid-template-columns: 1.2fr .9fr .9fr;
            gap:12px;
            align-items:center;
        }
        .billing-card strong {
            display:block;
            color:#f7fcff;
            font-size:15px;
            margin-bottom:4px;
        }
        .billing-meta {
            color:#9eb0d2;
            font-size:12px;
            line-height:1.6;
        }
        .billing-amount {
            color:#d6e0fb;
            font-size:13px;
            line-height:1.7;
        }
        .billing-amount strong {
            display:inline;
            font-size:13px;
        }
        .status-box {
            margin-top: 14px;
            padding: 16px 18px;
            border-radius: 18px;
            border: 1px solid rgba(255,255,255,.12);
            background: rgba(255,255,255,.05);
            display: grid;
            gap: 8px;
            overflow: hidden;
            transition: opacity .35s ease, transform .35s ease, max-height .4s ease, margin .4s ease, padding .4s ease, border-width .4s ease;
            max-height: 320px;
        }
        .status-box.is-hiding {
            opacity: 0;
            transform: translateY(-8px);
            max-height: 0;
            margin-top: 0;
            padding-top: 0;
            padding-bottom: 0;
            border-width: 0;
        }
        .status-box-head {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .status-box-close {
            margin-left: auto;
            width: 30px;
            height: 30px;
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,.12);
            background: rgba(255,255,255,.06);
            color: #dbeafe;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 15px;
            line-height: 1;
            transition: background .2s ease, transform .2s ease, border-color .2s ease;
        }
        .status-box-close:hover {
            background: rgba(255,255,255,.12);
            border-color: rgba(255,255,255,.22);
            transform: scale(1.04);
        }
        .status-box-icon {
            width: 34px;
            height: 34px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: 700;
            flex: 0 0 34px;
            box-shadow: inset 0 0 0 1px rgba(255,255,255,.08);
        }
        .status-box.success {
            border-color: rgba(134, 239, 172, .32);
            background: linear-gradient(135deg, rgba(34,197,94,.14), rgba(34,211,238,.08));
        }
        .status-box.success .status-box-icon {
            background: rgba(34, 197, 94, .18);
            color: #bbf7d0;
        }
        .status-box.error {
            border-color: rgba(248, 113, 113, .32);
            background: linear-gradient(135deg, rgba(127,29,29,.28), rgba(127,29,29,.14));
        }
        .status-box.error .status-box-icon {
            background: rgba(248, 113, 113, .16);
            color: #fecaca;
        }
        .status-box.info {
            border-color: rgba(34,211,238,.24);
            background: linear-gradient(135deg, rgba(34,211,238,.12), rgba(255,255,255,.04));
        }
        .status-box.info .status-box-icon {
            background: rgba(34, 211, 238, .16);
            color: #a5f3fc;
        }
        .status-box-title { font-size: 18px; font-weight: 700; color: #f8fafc; }
        .status-box-copy { color: #d7e2f5; line-height: 1.75; font-size: 14px; }
        .status-box-steps {
            margin: 0;
            padding-left: 18px;
            color: #c7d4ea;
            font-size: 13px;
            line-height: 1.8;
        }
        .status-box-steps li + li { margin-top: 2px; }
        @media (max-width: 980px) {
            .stats { grid-template-columns: repeat(2, 1fr); }
            .payment-builder { grid-template-columns: 1fr; }
            .billing-card { grid-template-columns: 1fr; }
            .payment-side-card { position: static; }
        }
        @media (max-width: 680px) {
            .payment-field-grid,
            .method-grid,
            .status-grid,
            .payment-preview,
            .reservation-summary-grid,
            .payment-bank-list { grid-template-columns: 1fr; }
            .plan-grid { grid-template-columns: 1fr !important; }
            .payment-qr-wrap { grid-template-columns: 1fr; }
            .payment-submit-row .btn { width: 100%; }
        }
    </style>
</head>
<body class="panel-page">
    <div class="panel-wrap">
        <div class="panel">
            <div class="panel-title">
                <div>
                    <h2>Pembayaran <?= h($company['short_name']) ?></h2>
                    <p class="panel-subtitle">Kelola pembayaran reservasi untuk <?= h($company['name']) ?> dan pantau status verifikasinya.</p>
                </div>
                <div class="links-inline">
                    <a class="link-muted" href="dashboard.php">Dashboard</a>
                    <a class="link-muted" href="../actions/logout.php">Logout</a>
                </div>
            </div>
            <p class="meta">Login: <strong><?= h($user['name']) ?></strong> (<?= h($user['role']) ?>) • Kontak admin venue <?= h($company['admin_contact']) ?></p>
            <div class="stats">
                <div class="stat">
                    <div class="stat-label">Total Pembayaran</div>
                    <div class="stat-value"><?= h($totalPembayaran) ?></div>
                </div>
                <div class="stat">
                    <div class="stat-label">Pending</div>
                    <div class="stat-value"><?= h($totalPending) ?></div>
                </div>
                <div class="stat">
                    <div class="stat-label">DP</div>
                    <div class="stat-value"><?= h($totalDp) ?></div>
                </div>
                <div class="stat">
                    <div class="stat-label">Nominal Masuk</div>
                    <div class="stat-value">Rp<?= h(number_format((int) $nominalMasuk, 0, ',', '.')) ?></div>
                </div>
            </div>
            <?php if ($pembayaranFeedback): ?>
                <div class="status-box <?= h($pembayaranFeedback['tone']) ?>"<?= $pembayaranFeedback['tone'] === 'success' ? ' data-autoclose="true"' : '' ?>>
                    <div class="status-box-head">
                        <span class="status-box-icon" aria-hidden="true"><?= $pembayaranFeedback['tone'] === 'success' ? '&#10003;' : ($pembayaranFeedback['tone'] === 'error' ? '!' : '&#105;i') ?></span>
                        <div class="status-box-title"><?= h($pembayaranFeedback['title']) ?></div>
                        <button class="status-box-close" type="button" data-close-status aria-label="Tutup notifikasi">&times;</button>
                    </div>
                    <div class="status-box-copy"><?= h($pembayaranFeedback['body']) ?></div>
                    <?php if (!empty($pembayaranFeedback['steps'])): ?>
                        <ul class="status-box-steps">
                            <?php foreach ($pembayaranFeedback['steps'] as $step): ?>
                                <li><?= h($step) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if (!$isPelanggan && $totalPending > 0): ?>
                <div class="status-box info">
                    <div class="status-box-head">
                        <span class="status-box-icon" aria-hidden="true">&#105;i</span>
                        <div class="status-box-title">Ada <?= h((string) $totalPending) ?> pembayaran yang menunggu verifikasi</div>
                        <button class="status-box-close" type="button" data-close-status aria-label="Tutup notifikasi">&times;</button>
                    </div>
                    <div class="status-box-copy">Kasir atau admin venue perlu memeriksa bukti transfer yang baru masuk agar pelanggan segera tahu apakah pembayaran sudah diakui sebagai DP atau lunas.</div>
                    <ul class="status-box-steps">
                        <li>Tinjau tabel pembayaran dan cek bukti unggahan terbaru.</li>
                        <li>Perbarui status pembayaran sesuai nominal yang sudah diterima.</li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($canCreatePayment): ?>
            <div class="panel">
                <h3 style="margin-top:0;"><?= $isPelanggan ? 'Bayar Reservasi' : 'Tambah Pembayaran' ?></h3>
                <p class="desc-muted" style="margin-top:-4px; margin-bottom:14px;">Semua pembayaran pada halaman ini ditujukan untuk booking di <?= h($company['short_name']) ?> dan diverifikasi oleh admin atau kasir venue.</p>
                <form method="POST" class="form-reset" enctype="multipart/form-data" id="paymentCreateForm">
                    <input type="hidden" name="action" value="create">
                    <div class="payment-builder">
                        <div class="pay-card payment-form-card">
                            <div class="pay-section">
                                <div class="pay-section-head">
                                    <h4>Informasi pembayaran</h4>
                                    <div class="pay-section-copy">Pilih reservasi dulu agar nominal, metode, dan instruksi pembayaran bisa menyesuaikan otomatis.</div>
                                </div>
                                <div class="payment-field-grid">
                                    <div class="reservation-selection" style="grid-column: 1 / -1;">
                                        <?php if ($isPelanggan): ?>
                                            <input type="hidden" name="id_reservasi" id="id_reservasi" value="<?= h($selectedReservasiId) ?>" required>
                                            <div class="reservation-locked<?= $selectedReservationSummary ? '' : ' is-empty' ?>" aria-label="Reservasi yang dipilih">
                                                <?= h($selectedReservationSummary ? (($selectedReservationSummary['id_reservasi'] ?? '') . ' - ' . ($selectedReservationSummary['nama_lengkap'] ?? '')) : 'Belum ada reservasi yang bisa dibayar') ?>
                                            </div>
                                        <?php else: ?>
                                            <select name="id_reservasi" id="id_reservasi" required>
                                                <option value="">Pilih reservasi</option>
                                                <?php foreach ($payableReservasiRows as $r): ?>
                                                    <option value="<?= h($r['id_reservasi']) ?>" <?= $selectedReservasiId !== '' && $selectedReservasiId === (string) $r['id_reservasi'] ? 'selected' : '' ?>><?= h($r['id_reservasi'] . ' - ' . $r['nama_lengkap']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php endif; ?>
                                        <div class="reservation-summary-grid">
                                            <div class="reservation-summary-item">
                                                <span>Reservasi</span>
                                                <strong id="reservationInfoIdentity">Belum dipilih</strong>
                                            </div>
                                            <div class="reservation-summary-item">
                                                <span>Status bayar</span>
                                                <strong id="reservationInfoBilling">Belum ada</strong>
                                            </div>
                                            <div class="reservation-summary-item">
                                                <span>Sisa tagihan</span>
                                                <strong id="reservationInfoRemaining">Rp0</strong>
                                            </div>
                                        </div>
                                    </div>
                                    <?php if ($isPelanggan): ?>
                                        <input type="hidden" name="tanggal_bayar" id="tanggal_bayar" value="<?= h($nowPaymentValue) ?>" required>
                                        <input type="text" value="<?= h($nowPaymentDisplay) ?>" readonly aria-label="Waktu pembayaran otomatis Surabaya">
                                    <?php else: ?>
                                        <input type="hidden" name="tanggal_bayar" id="tanggal_bayar" value="<?= h($nowPaymentValue) ?>" required>
                                        <input type="text" id="tanggal_bayar_display" value="<?= h($nowPaymentDisplay) ?>" readonly aria-label="Waktu pembayaran otomatis Surabaya">
                                    <?php endif; ?>
                                    <input type="number" name="jumlah_bayar" id="jumlah_bayar" min="0" step="0.01" placeholder="Jumlah bayar" readonly required>
                                    <input type="file" name="bukti_pembayaran_file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required>
                                  </div>
                                <?php if (empty($payableReservasiRows)): ?>
                                    <div class="pay-section-copy" style="margin-top:12px;">Semua reservasi yang tersedia sudah lunas, jadi tidak ada pembayaran baru yang perlu dibuat saat ini.</div>
                                <?php endif; ?>
                            </div>
                            <input type="hidden" name="metode_pembayaran" id="metode_pembayaran" value="<?= $isPelanggan ? '' : 'QRIS' ?>" required>
                            <input type="hidden" name="status_pembayaran" id="status_pembayaran" value="pending" required>
                            <input type="hidden" name="payment_plan" id="payment_plan" value="lunas" required>
                            <?php if ($isPelanggan): ?>
                                <div class="pay-section">
                                    <div class="pay-section-head">
                                        <h4>Pilih jenis pembayaran</h4>
                                        <div class="pay-section-copy">Pilih DP untuk membayar setengah tagihan dulu, atau lunas jika ingin menyelesaikan pembayaran sekarang.</div>
                                    </div>
                                    <div class="plan-grid" id="planGrid" style="grid-template-columns: repeat(2, 1fr);">
                                        <button type="button" class="plan-btn" data-value="dp50">
                                            <strong>DP 50%</strong>
                                            <span id="dpPlanHint">Bayar setengah total tagihan dulu</span>
                                        </button>
                                        <button type="button" class="plan-btn active" data-value="lunas">
                                            <strong>Lunas</strong>
                                            <span>Bayar sisa tagihan penuh sekarang</span>
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="pay-section">
                                <div class="pay-section-head">
                                    <h4>Pilih metode pembayaran</h4>
                                    <div class="pay-section-copy">QRIS cocok untuk scan cepat, transfer memakai kode unik, dan cash dibayarkan langsung ke kasir venue.</div>
                                </div>
                                    <div class="method-grid" id="methodGrid">
                                    <button type="button" class="method-btn<?= $isPelanggan ? '' : ' active' ?>" data-value="QRIS">
                                        <strong>QRIS</strong>
                                        <span>Cepat untuk scan dan verifikasi</span>
                                    </button>
                                    <button type="button" class="method-btn" data-value="Transfer">
                                        <strong>Transfer</strong>
                                        <span>Cocok untuk nominal penuh</span>
                                    </button>
                                    <button type="button" class="method-btn" data-value="Cash">
                                        <strong>Cash</strong>
                                        <span>Pembayaran langsung di venue</span>
                                    </button>
                                </div>
                                <?php if ($isPelanggan): ?>
                                    <div class="pay-note">Pembayaran pelanggan selalu masuk sebagai <strong>pending</strong> dulu, lalu diverifikasi admin atau kasir venue.</div>
                                    <div class="payment-method-card" id="paymentMethodCard" hidden>
                                        <h5 id="paymentMethodTitle">Instruksi QRIS</h5>
                                        <div class="payment-qr-wrap" id="paymentQrisPanel">
                                            <img src="../assets/qris.jpeg" alt="QRIS SDK Badminton Hall">
                                        <div class="payment-method-copy">
                                                <p>Scan QRIS ini menggunakan e-wallet atau mobile banking, lalu pastikan nominal yang dibayarkan sesuai preview transaksi.</p>
                                                <div class="payment-amount-chip" id="paymentMethodAmount">Rp0</div>
                                            </div>
                                        </div>
                                        <div class="payment-bank-list" id="paymentTransferPanel" hidden>
                                            <?php foreach ($venueTransferAccounts as $account): ?>
                                                <div class="payment-bank-item">
                                                    <strong><?= h($account['bank']) ?> • <?= h($account['number']) ?></strong>
                                                    <span><?= h($account['holder']) ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                            <div class="payment-highlight-box">
                                                <strong>Nominal transfer yang harus dikirim</strong>
                                                <span id="paymentTransferAmount">Rp0</span>
                                            </div>
                                            <div class="payment-highlight-box">
                                                <strong>Kode unik transfer</strong>
                                                <span id="paymentTransferCode">-</span>
                                            </div>
                                        </div>
                                        <div class="payment-highlight-box" id="paymentCashPanel" hidden>
                                            <strong>Pembayaran tunai di venue</strong>
                                            <span>Datang ke <?= h($company['name']) ?>, tunjukkan ID reservasi, lalu lakukan pembayaran di kasir sesuai nominal pada preview transaksi.</span>
                                            <span class="payment-amount-chip" id="paymentCashAmount">Rp0</span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if (!$isPelanggan): ?>
                                <div class="pay-section">
                                    <div class="pay-section-head">
                                        <h4>Pilih status pembayaran</h4>
                                        <div class="pay-section-copy">Gunakan pending untuk menunggu cek bukti, DP untuk pembayaran sebagian, dan lunas untuk pelunasan penuh.</div>
                                    </div>
                                    <div class="status-grid" id="statusGrid">
                                        <button type="button" class="status-btn active" data-value="pending">
                                            <strong>Pending</strong>
                                            <span>Menunggu konfirmasi</span>
                                        </button>
                                        <button type="button" class="status-btn" data-value="DP">
                                            <strong>DP</strong>
                                            <span>Sudah bayar sebagian</span>
                                        </button>
                                        <button type="button" class="status-btn" data-value="lunas">
                                            <strong>Lunas</strong>
                                            <span>Pembayaran selesai</span>
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="payment-submit-row"><button class="btn btn-create" type="submit"><?= $isPelanggan ? 'Kirim Pembayaran' : 'Tambah Pembayaran' ?></button></div>
                        </div>
                        <div class="pay-card payment-side-card">
                            <div class="payment-preview-head">
                                <div class="payment-preview-kicker">Ringkasan Tagihan</div>
                                <h4>Preview Transaksi</h4>
                                <div class="payment-preview-copy">Panel ini merangkum tagihan, status pembayaran, dan metode aktif supaya pelanggan maupun petugas bisa cek ulang sebelum kirim.</div>
                            </div>
                            <div class="payment-preview">
                                <div class="payment-preview-row"><span>Reservasi</span><strong id="previewReservasi">Belum dipilih</strong></div>
                                <div class="payment-preview-row"><span>Pelanggan</span><strong id="previewNama">-</strong></div>
                                <div class="payment-preview-row"><span>Target tagihan</span><strong id="previewTarget">Rp0</strong></div>
                                <div class="payment-preview-row"><span>Sudah dibayar</span><strong id="previewPaid">Rp0</strong></div>
                                <div class="payment-preview-row"><span>Sisa tagihan</span><strong id="previewRemaining">Rp0</strong></div>
                                <div class="payment-preview-row is-highlight"><span>Nominal input</span><strong id="previewJumlah">Rp0</strong></div>
                                <div class="payment-preview-row"><span>Status reservasi bayar</span><strong><span class="preview-badge neutral" id="previewBilling">Belum ada</span></strong></div>
                                <div class="payment-preview-row"><span>Status</span><strong><span class="preview-badge pending" id="previewStatus">Pending</span></strong></div>
                                <div class="payment-preview-row"><span>Jenis bayar</span><strong><span class="preview-badge lunas" id="previewPlan">Lunas</span></strong></div>
                                <div class="payment-preview-row"><span>Metode</span><strong id="previewMetode"><?= $isPelanggan ? 'Belum dipilih' : 'QRIS' ?></strong></div>
                                <div class="payment-preview-row is-wide" id="previewUniqueCodeRow" hidden><span>Kode unik transfer</span><strong id="previewUniqueCode">-</strong></div>
                            </div>
                            <small class="pay-note"><?= $isPelanggan ? 'Pilih DP 50% atau lunas. Nominal pembayaran dan waktu kirim akan otomatis mengikuti pilihanmu serta waktu Surabaya saat ini, lalu admin/kasir akan memverifikasi bukti yang kamu kirim.' : 'Tips: pilih reservasi dulu agar nominal target bisa terisi otomatis ke kolom jumlah bayar.' ?></small>
                        </div>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <div class="panel">
            <h3 style="margin-top:0;">Data Pembayaran</h3>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Reservasi</th>
                            <th>Detail</th>
                            <th style="width: 230px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payRows as $py): ?>
                            <?php
                            $statusMeta = payment_status_meta($py['status_pembayaran'] ?? 'pending');
                            $verificationLabel = empty($py['id_admin_verifikasi']) ? 'Belum diverifikasi' : 'Sudah diverifikasi';
                            $verificationClass = empty($py['id_admin_verifikasi']) ? 'unverified' : 'verified';
                            $verificationTimestamp = format_verification_datetime($py['verified_at'] ?? '');
                            $verificationNote = trim((string) ($py['verification_note'] ?? ''));
                            $verifierName = trim((string) ($py['verifier_name'] ?? ''));
                            ?>
                            <tr>
                                <td><strong><?= h($py['id_pembayaran']) ?></strong></td>
                                <td><?= h($py['id_reservasi']) ?><br><small class="pay-note"><?= h($py['nama_lengkap']) ?></small></td>
                                <td>
                                    <?php if ($canEdit): ?>
                                        <form method="POST" class="form-reset" enctype="multipart/form-data">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="id_pembayaran" value="<?= h($py['id_pembayaran']) ?>">
                                            <input type="hidden" name="old_bukti_pembayaran" value="<?= h($py['bukti_pembayaran']) ?>">
                                            <div class="grid-2">
                                                <select name="metode_pembayaran" required>
                                                    <option value="QRIS" <?= $py['metode_pembayaran'] === 'QRIS' ? 'selected' : '' ?>>QRIS</option>
                                                    <option value="Transfer" <?= $py['metode_pembayaran'] === 'Transfer' ? 'selected' : '' ?>>Transfer</option>
                                                    <option value="Cash" <?= $py['metode_pembayaran'] === 'Cash' ? 'selected' : '' ?>>Cash</option>
                                                </select>
                                                <input type="number" step="0.01" name="jumlah_bayar" value="<?= h($py['jumlah_bayar']) ?>" required>
                                                <input type="datetime-local" name="tanggal_bayar" value="<?= h(str_replace(' ', 'T', substr((string) $py['tanggal_bayar'], 0, 16))) ?>" required>
                                                <select name="status_pembayaran" required>
                                                    <option value="pending" <?= strtolower((string) $py['status_pembayaran']) === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                    <option value="DP" <?= strtolower((string) $py['status_pembayaran']) === 'dp' ? 'selected' : '' ?>>DP</option>
                                                    <option value="lunas" <?= strtolower((string) $py['status_pembayaran']) === 'lunas' ? 'selected' : '' ?>>Lunas</option>
                                                </select>
                                                <input type="file" name="bukti_pembayaran_file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                                                <textarea name="verification_note" rows="3" placeholder="Catatan verifikasi untuk pelanggan atau arsip internal"><?= h($verificationNote) ?></textarea>
                                            </div>
                                            <small class="pay-note">Bukti saat ini:
                                                <?php if (!empty($py['bukti_pembayaran']) && $py['bukti_pembayaran'] !== '-'): ?>
                                                    <a class="link-muted" href="../<?= h($py['bukti_pembayaran']) ?>" target="_blank" rel="noopener noreferrer"><?= h(basename((string) $py['bukti_pembayaran'])) ?></a>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </small>
                                            <small class="pay-note">
                                                <span class="method-pill"><?= h($py['metode_pembayaran']) ?></span>
                                                <span class="status-pill <?= h($statusMeta['class']) ?>"><?= h($statusMeta['label']) ?></span>
                                                <span class="verify-pill <?= h($verificationClass) ?>"><?= h($verificationLabel) ?></span>
                                            </small>
                                            <div class="verify-stack">
                                                <div class="verify-meta">Diverifikasi oleh: <strong><?= h($verifierName !== '' ? $verifierName : '-') ?></strong></div>
                                                <div class="verify-meta">Timestamp verifikasi: <strong><?= h($verificationTimestamp) ?></strong></div>
                                                <div class="verify-note"><?= h($verificationNote !== '' ? $verificationNote : 'Belum ada catatan verifikasi.') ?></div>
                                            </div>
                                            <p style="margin-top:8px;"><button class="btn btn-save" type="submit">Simpan</button></p>
                                        </form>
                                    <?php else: ?>
                                        Metode: <span class="method-pill"><?= h($py['metode_pembayaran']) ?></span><br>
                                        Jumlah: Rp<?= h(number_format((int) $py['jumlah_bayar'], 0, ',', '.')) ?><br>
                                        Tanggal: <?= h($py['tanggal_bayar']) ?><br>
                                        Status: <span class="status-pill <?= h($statusMeta['class']) ?>"><?= h($statusMeta['label']) ?></span><br>
                                        Verifikasi: <span class="verify-pill <?= h($verificationClass) ?>"><?= h($verificationLabel) ?></span><br>
                                        Diverifikasi oleh: <strong><?= h($verifierName !== '' ? $verifierName : '-') ?></strong><br>
                                        Timestamp verifikasi: <strong><?= h($verificationTimestamp) ?></strong><br>
                                        Catatan verifikasi:
                                        <div class="verify-note"><?= h($verificationNote !== '' ? $verificationNote : 'Belum ada catatan verifikasi.') ?></div>
                                        Bukti:
                                        <?php if (!empty($py['bukti_pembayaran']) && $py['bukti_pembayaran'] !== '-'): ?>
                                            <a class="link-muted" href="../<?= h($py['bukti_pembayaran']) ?>" target="_blank" rel="noopener noreferrer"><?= h(basename((string) $py['bukti_pembayaran'])) ?></a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($canDeletePayment): ?>
                                        <div class="cell-actions">
                                            <form method="POST" class="form-reset" onsubmit="return confirm('Hapus pembayaran ini?')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id_pembayaran" value="<?= h($py['id_pembayaran']) ?>">
                                                <button class="btn btn-del" type="submit">Hapus</button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        Read only
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
        <?php if ($canCreatePayment): ?>
          <script>
              const isPelangganPayment = <?= json_encode($isPelanggan) ?>;
              const reservationDataset = <?= json_encode(array_reduce($payableReservasiRows, static function (array $carry, array $row): array {
                  $reservationId = (string) ($row['id_reservasi'] ?? '');
                  if ($reservationId === '') {
                      return $carry;
                  }
                  $carry[$reservationId] = [
                      'id' => $reservationId,
                      'nama' => (string) ($row['nama_lengkap'] ?? ''),
                      'total' => (int) ($row['total_biaya'] ?? 0),
                      'paid' => (int) ($row['total_bayar'] ?? 0),
                      'remaining' => (int) ($row['sisa'] ?? 0),
                      'billing' => (string) ($row['billing_label'] ?? 'Belum ada'),
                  ];
                  return $carry;
              }, []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
            const reservasiSelect = document.getElementById('id_reservasi');
            const jumlahBayarInput = document.getElementById('jumlah_bayar');
            const tanggalBayarInput = document.getElementById('tanggal_bayar');
            const tanggalBayarDisplayInput = document.getElementById('tanggal_bayar_display');
            const metodeInput = document.getElementById('metode_pembayaran');
            const paymentPlanInput = document.getElementById('payment_plan');
            const statusInput = document.getElementById('status_pembayaran');
            const reservationInfoIdentity = document.getElementById('reservationInfoIdentity');
            const reservationInfoBilling = document.getElementById('reservationInfoBilling');
            const reservationInfoRemaining = document.getElementById('reservationInfoRemaining');
            const previewReservasi = document.getElementById('previewReservasi');
            const previewNama = document.getElementById('previewNama');
            const previewTarget = document.getElementById('previewTarget');
            const previewPaid = document.getElementById('previewPaid');
            const previewRemaining = document.getElementById('previewRemaining');
            const previewBilling = document.getElementById('previewBilling');
            const previewPlan = document.getElementById('previewPlan');
            const previewMetode = document.getElementById('previewMetode');
            const previewUniqueCodeRow = document.getElementById('previewUniqueCodeRow');
            const previewUniqueCode = document.getElementById('previewUniqueCode');
            const previewStatus = document.getElementById('previewStatus');
            const previewJumlah = document.getElementById('previewJumlah');
            const methodButtons = Array.from(document.querySelectorAll('.method-btn'));
            const planButtons = Array.from(document.querySelectorAll('.plan-btn'));
            const statusButtons = Array.from(document.querySelectorAll('.status-btn'));
            const dpPlanButton = planButtons.find((btn) => btn.dataset.value === 'dp50');
            const dpPlanHint = document.getElementById('dpPlanHint');
            const paymentMethodCard = document.getElementById('paymentMethodCard');
            const paymentMethodTitle = document.getElementById('paymentMethodTitle');
            const paymentQrisPanel = document.getElementById('paymentQrisPanel');
            const paymentTransferPanel = document.getElementById('paymentTransferPanel');
            const paymentCashPanel = document.getElementById('paymentCashPanel');
            const paymentMethodAmount = document.getElementById('paymentMethodAmount');
            const paymentTransferAmount = document.getElementById('paymentTransferAmount');
            const paymentTransferCode = document.getElementById('paymentTransferCode');
            const paymentCashAmount = document.getElementById('paymentCashAmount');
            let customerMethodTouched = !isPelangganPayment;

            function formatRupiah(value) {
                return 'Rp' + Number(value || 0).toLocaleString('id-ID');
            }

            function formatDisplayDateTime(dateValue) {
                const source = String(dateValue || '').trim();
                if (!source) {
                    return 'Tanggal pembayaran otomatis';
                }
                const normalized = source.replace('T', ' ');
                const date = new Date(normalized.replace(' ', 'T'));
                if (Number.isNaN(date.getTime())) {
                    return source;
                }
                const day = String(date.getDate()).padStart(2, '0');
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const year = date.getFullYear();
                const hours = String(date.getHours()).padStart(2, '0');
                const minutes = String(date.getMinutes()).padStart(2, '0');
                return `${day}-${month}-${year} ${hours}:${minutes} WIB`;
            }

            function applyPreviewBadge(element, label, tone = 'neutral') {
                if (!element) {
                    return;
                }
                element.textContent = label;
                element.className = 'preview-badge ' + tone;
            }

            function billingTone(label) {
                const normalized = String(label || '').toLowerCase();
                if (normalized.includes('lunas')) {
                    return 'lunas';
                }
                if (normalized.includes('dp')) {
                    return 'dp';
                }
                if (normalized.includes('pending')) {
                    return 'pending';
                }
                return 'neutral';
            }

            function generateTransferUniqueCode(reservationId, plan) {
                const seed = String(reservationId || '').toUpperCase() + '|' + String(plan || '').toLowerCase();
                let sum = 0;
                for (const char of seed) {
                    sum += char.charCodeAt(0);
                }
                return 100 + (sum % 900);
            }

            function calculateCustomerAmount(total, paid, remaining) {
                const targetDp = Math.ceil(total / 2);
                const sisaDp = Math.max(0, targetDp - paid);
                const plan = paymentPlanInput?.value || 'lunas';
                const method = metodeInput?.value || '';
                const reservationId = reservasiSelect?.value || '';
                let amount = plan === 'dp50'
                    ? Math.min(remaining, sisaDp)
                    : (remaining > 0 ? remaining : total);

                if (method === 'Transfer' && reservationId) {
                    amount += generateTransferUniqueCode(reservationId, plan);
                }
                return amount;
            }

            function isInstructionMethod(method) {
                return method === 'QRIS' || method === 'Transfer';
            }

            function updateMethodInstruction(amountValue) {
                if (!isPelangganPayment || !paymentMethodTitle) {
                    return;
                }

                const method = metodeInput?.value || '';
                const amountText = formatRupiah(amountValue || 0);
                const reservationId = reservasiSelect?.value || '';
                const plan = paymentPlanInput?.value || 'lunas';
                const uniqueCode = method === 'Transfer' && reservationId ? generateTransferUniqueCode(reservationId, plan) : 0;
                const shouldShowInstruction = customerMethodTouched && isInstructionMethod(method);

                if (paymentMethodCard) {
                    paymentMethodCard.hidden = !shouldShowInstruction;
                }

                if (previewUniqueCode) {
                    previewUniqueCode.textContent = uniqueCode > 0 ? String(uniqueCode) : '-';
                }
                if (previewUniqueCodeRow) {
                    previewUniqueCodeRow.hidden = method !== 'Transfer';
                }
                if (paymentMethodAmount) {
                    paymentMethodAmount.textContent = amountText;
                }
                if (paymentTransferAmount) {
                    paymentTransferAmount.textContent = amountText;
                }
                if (paymentCashAmount) {
                    paymentCashAmount.textContent = amountText;
                }
                if (paymentTransferCode) {
                    paymentTransferCode.textContent = uniqueCode > 0 ? String(uniqueCode) : '-';
                }

                if (paymentQrisPanel) {
                    paymentQrisPanel.hidden = !shouldShowInstruction || method !== 'QRIS';
                }
                if (paymentTransferPanel) {
                    paymentTransferPanel.hidden = !shouldShowInstruction || method !== 'Transfer';
                }
                if (paymentCashPanel) {
                    paymentCashPanel.hidden = true;
                }

                if (!shouldShowInstruction) {
                    paymentMethodTitle.textContent = 'Instruksi QRIS';
                    return;
                }

                if (method === 'Transfer') {
                    paymentMethodTitle.textContent = 'Instruksi Transfer';
                } else {
                    paymentMethodTitle.textContent = 'Instruksi QRIS';
                }
            }

            function syncPaymentPlanAvailability(total, paid, remaining) {
                if (!isPelangganPayment || !dpPlanButton || !paymentPlanInput) {
                    return;
                }

                const targetDp = Math.ceil(total / 2);
                const sisaDp = Math.max(0, targetDp - paid);
                const dpLocked = sisaDp <= 0 || remaining <= 0;

                dpPlanButton.disabled = dpLocked;
                if (dpPlanHint) {
                    dpPlanHint.textContent = dpLocked
                        ? 'DP sudah terpenuhi, lanjutkan dengan pelunasan'
                        : 'Bayar setengah total tagihan dulu';
                }

                if (dpLocked && paymentPlanInput.value === 'dp50') {
                    paymentPlanInput.value = 'lunas';
                }
            }

              function updatePreviewFromReservasi(autoFill = false) {
                  const reservationId = reservasiSelect?.value || '';
                  const selected = reservationId ? reservationDataset[reservationId] || null : null;
                  if (!selected || !reservationId) {
                      if (reservationInfoIdentity) {
                          reservationInfoIdentity.textContent = 'Belum dipilih';
                      }
                    if (reservationInfoBilling) {
                        reservationInfoBilling.textContent = 'Belum ada';
                    }
                    if (reservationInfoRemaining) {
                        reservationInfoRemaining.textContent = 'Rp0';
                    }
                    previewReservasi.textContent = 'Belum dipilih';
                    previewNama.textContent = '-';
                    previewTarget.textContent = 'Rp0';
                    previewPaid.textContent = 'Rp0';
                    previewRemaining.textContent = 'Rp0';
                    applyPreviewBadge(previewBilling, 'Belum ada', 'neutral');
                    applyPreviewBadge(previewPlan, 'Lunas', 'lunas');
                    previewMetode.textContent = isPelangganPayment ? 'Belum dipilih' : 'QRIS';
                    if (previewUniqueCode) {
                        previewUniqueCode.textContent = '-';
                    }
                    if (previewUniqueCodeRow) {
                        previewUniqueCodeRow.hidden = true;
                    }
                    if (dpPlanButton) {
                        dpPlanButton.disabled = false;
                    }
                    if (dpPlanHint) {
                        dpPlanHint.textContent = 'Bayar setengah total tagihan dulu';
                    }
                    previewJumlah.textContent = formatRupiah(jumlahBayarInput?.value || 0);
                    updateMethodInstruction(Number(jumlahBayarInput?.value || 0));
                    return;
                  }
                  const total = Number(selected.total || 0);
                  const paid = Number(selected.paid || 0);
                  const remaining = Number(selected.remaining || 0);
                  const reservationIdentityText = `${selected.id}${selected.nama ? ` - ${selected.nama}` : ''}`;
                  if (reservationInfoIdentity) {
                      reservationInfoIdentity.textContent = reservationIdentityText;
                  }
                  if (reservationInfoBilling) {
                      reservationInfoBilling.textContent = selected.billing || 'Belum ada';
                  }
                  if (reservationInfoRemaining) {
                      reservationInfoRemaining.textContent = formatRupiah(remaining);
                  }
                  syncPaymentPlanAvailability(total, paid, remaining);
                  const computedAmount = isPelangganPayment ? calculateCustomerAmount(total, paid, remaining) : (remaining > 0 ? remaining : total);
                  const projectedRemaining = Math.max(0, remaining - computedAmount);
                  previewReservasi.textContent = selected.id;
                  previewNama.textContent = selected.nama || '-';
                  previewTarget.textContent = formatRupiah(total);
                  previewPaid.textContent = formatRupiah(paid);
                  previewRemaining.textContent = formatRupiah(isPelangganPayment ? projectedRemaining : remaining);
                  applyPreviewBadge(previewBilling, selected.billing || 'Belum ada', billingTone(selected.billing || ''));
                  applyPreviewBadge(previewPlan, (paymentPlanInput?.value || 'lunas') === 'dp50' ? 'DP 50%' : 'Lunas', (paymentPlanInput?.value || 'lunas') === 'dp50' ? 'dp' : 'lunas');
                  if (jumlahBayarInput) {
                      jumlahBayarInput.value = String(computedAmount);
                  }
                  previewJumlah.textContent = formatRupiah(computedAmount);
                  updateMethodInstruction(computedAmount);
              }

            function setMethod(value) {
                if (isPelangganPayment) {
                    customerMethodTouched = isInstructionMethod(value);
                }
                metodeInput.value = value;
                previewMetode.textContent = value || 'Belum dipilih';
                methodButtons.forEach((btn) => btn.classList.toggle('active', btn.dataset.value === value));
                updatePreviewFromReservasi(true);
            }

            function setPlan(value) {
                if (!paymentPlanInput) {
                    return;
                }
                if (value === 'dp50' && dpPlanButton?.disabled) {
                    return;
                }
                paymentPlanInput.value = value;
                applyPreviewBadge(previewPlan, value === 'dp50' ? 'DP 50%' : 'Lunas', value === 'dp50' ? 'dp' : 'lunas');
                planButtons.forEach((btn) => btn.classList.toggle('active', btn.dataset.value === value));
                updatePreviewFromReservasi(true);
            }

            function setStatus(value) {
                statusInput.value = value;
                const normalized = String(value || '').toLowerCase();
                applyPreviewBadge(
                    previewStatus,
                    normalized === 'dp' ? 'DP' : (normalized === 'lunas' ? 'Lunas' : 'Pending'),
                    normalized === 'dp' ? 'dp' : (normalized === 'lunas' ? 'lunas' : 'pending')
                );
                statusButtons.forEach((btn) => btn.classList.toggle('active', btn.dataset.value === value));
            }

            reservasiSelect?.addEventListener('change', () => updatePreviewFromReservasi(true));
                jumlahBayarInput?.addEventListener('input', () => {
                previewJumlah.textContent = formatRupiah(jumlahBayarInput.value || 0);
                updateMethodInstruction(Number(jumlahBayarInput.value || 0));
            });
            methodButtons.forEach((btn) => btn.addEventListener('click', () => setMethod(btn.dataset.value || 'QRIS')));
            planButtons.forEach((btn) => btn.addEventListener('click', () => setPlan(btn.dataset.value || 'lunas')));
            statusButtons.forEach((btn) => btn.addEventListener('click', () => setStatus(btn.dataset.value || 'pending')));

            updatePreviewFromReservasi(false);
            setPlan(paymentPlanInput?.value || 'lunas');
            if (isPelangganPayment) {
                metodeInput.value = '';
                previewMetode.textContent = 'Belum dipilih';
                methodButtons.forEach((btn) => btn.classList.remove('active'));
                updateMethodInstruction(Number(jumlahBayarInput?.value || 0));
            } else {
                setMethod(metodeInput.value || 'QRIS');
            }
            setStatus(statusInput.value || 'pending');
            previewJumlah.textContent = formatRupiah(jumlahBayarInput?.value || 0);
            if (isPelangganPayment && tanggalBayarInput) {
                tanggalBayarInput.value = <?= json_encode($nowPaymentValue) ?>;
            }
            if (tanggalBayarInput && tanggalBayarDisplayInput) {
                tanggalBayarDisplayInput.value = formatDisplayDateTime(tanggalBayarInput.value || <?= json_encode($nowPaymentValue) ?>);
            }

            const hideStatusBox = (box) => {
                if (!box || box.classList.contains('is-hiding')) {
                    return;
                }
                box.classList.add('is-hiding');
                window.setTimeout(() => box.remove(), 420);
            };

            document.querySelectorAll('[data-close-status]').forEach((button) => {
                button.addEventListener('click', () => hideStatusBox(button.closest('.status-box')));
            });

            if (!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)) {
                window.setTimeout(() => {
                    document.querySelectorAll('.status-box[data-autoclose="true"]').forEach((box) => {
                        hideStatusBox(box);
                    });
                }, 4200);
            }
        </script>
    <?php endif; ?>
</body>
</html>

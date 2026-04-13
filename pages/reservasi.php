<?php
require_once __DIR__ . '/../core/role_helper.php';
$company = require __DIR__ . '/../data/company.php';

$user = ensure_login_and_role(['pelanggan', 'admin', 'kasir', 'owner']);
$flash = pull_flash();
$isPelanggan = $user['role'] === 'pelanggan';
$canCreateOwnReservation = user_has_permission($user, 'reservation.create_own');
$canCreateInternalReservation = user_has_permission($user, 'reservation.create_internal');
$canUpdateOwnReservation = user_has_permission($user, 'reservation.update_own');
$canDeleteOwnReservation = user_has_permission($user, 'reservation.delete_own');
$canViewAllReservations = user_has_permission($user, 'reservation.read_all');
$canUpdateReservationStatus = user_has_permission($user, 'reservation.status.update');
$canDeleteAnyReservation = user_has_permission($user, 'reservation.delete_any');
$promoList = require __DIR__ . '/../data/promos.php';
$coachList = require __DIR__ . '/../data/coaches.php';
$partnerList = require __DIR__ . '/../data/partners.php';
$selectedCourtId = trim((string) ($_GET['court'] ?? ''));
$selectedPromoCode = trim((string) ($_GET['promo'] ?? ''));
$selectedCoachSlug = trim((string) ($_GET['coach'] ?? ''));
$selectedPartnerSlug = trim((string) ($_GET['partner'] ?? ''));
$activeReservationFilter = trim((string) ($_GET['filter'] ?? 'all'));
$selectedPromo = null;
foreach ($promoList as $promoItem) {
    if (($promoItem['code'] ?? '') === $selectedPromoCode) {
        $selectedPromo = $promoItem;
        break;
    }
}
$selectedCoach = null;
foreach ($coachList as $coachItem) {
    if (($coachItem['slug'] ?? '') === $selectedCoachSlug) {
        $selectedCoach = $coachItem;
        break;
    }
}
$selectedPartner = null;
foreach ($partnerList as $partnerItem) {
    if (($partnerItem['slug'] ?? '') === $selectedPartnerSlug) {
        $selectedPartner = $partnerItem;
        break;
    }
}

function reservasi_feedback_box($flash, $role) {
    if (!$flash || !is_array($flash)) {
        return null;
    }

    $message = (string) ($flash['message'] ?? '');
    $type = (string) ($flash['type'] ?? 'info');
    $isPelanggan = ($role === 'pelanggan');

    if ($type === 'success' && $isPelanggan && str_contains($message, 'Reservasi berhasil dibuat')) {
        return [
            'tone' => 'success',
            'title' => 'Reservasi berhasil masuk ke sistem',
            'body' => 'Slot bermainmu sudah tercatat dan saat ini menunggu proses operasional dari venue.',
            'steps' => [
                'Periksa detail jam dan lapangan pada tabel reservasi di bawah.',
                'Lanjutkan ke pembayaran jika ingin langsung mengunci jadwal dengan DP 50% atau pelunasan.',
                'Tunggu admin venue mengonfirmasi reservasimu setelah data dan pembayaran dicek.',
            ],
        ];
    }

    if ($type === 'success' && $isPelanggan && str_contains($message, 'Reservasi berhasil diupdate')) {
        return [
            'tone' => 'success',
            'title' => 'Detail reservasi sudah diperbarui',
            'body' => 'Perubahan jadwal atau lapangan sudah disimpan. Pastikan total biaya dan slot yang dipilih tetap sesuai kebutuhanmu.',
            'steps' => [
                'Cek ulang jam main dan total biaya pada daftar reservasi.',
                'Jika sudah ada pembayaran, sesuaikan langkah berikutnya dari halaman pembayaran.',
            ],
        ];
    }

    if ($type === 'success' && !$isPelanggan && str_contains($message, 'Status reservasi berhasil diupdate')) {
        return [
            'tone' => 'success',
            'title' => 'Status reservasi sudah diperbarui',
            'body' => 'Perubahan status sudah tersimpan dan pelanggan akan melihat pembaruan ini pada akun mereka.',
            'steps' => [
                'Pastikan status baru sudah sesuai dengan kondisi jadwal dan lapangan.',
                'Lanjutkan pengecekan pembayaran bila reservasi ini masih butuh verifikasi.',
            ],
        ];
    }

    if ($type === 'error') {
        return [
            'tone' => 'error',
            'title' => 'Aksi reservasi belum bisa diproses',
            'body' => $message,
            'steps' => [
                'Periksa kembali tanggal, jam mulai, dan jam selesai yang dipilih.',
                'Jika perlu, pilih slot lain yang masih tersedia.',
            ],
        ];
    }

    return [
        'tone' => $type === 'success' ? 'success' : 'info',
        'title' => $type === 'success' ? 'Update reservasi berhasil' : 'Informasi reservasi',
        'body' => $message,
        'steps' => [],
    ];
}

function hitung_total_biaya($idLapangan, $jamMulai, $jamSelesai) {
    $durasi = calculate_durasi_jam($jamMulai, $jamSelesai);
    if ($durasi <= 0) {
        return [0, 0];
    }

    $stmt = db()->prepare('SELECT harga_per_jam FROM lapangan WHERE id_lapangan = :id LIMIT 1');
    $stmt->execute(['id' => $idLapangan]);
    $harga = (int) ($stmt->fetchColumn() ?: 0);
    return [$durasi, $harga * $durasi];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            if ($isPelanggan) {
                ensure_permission($user, 'reservation.create_own', 'reservasi.php');
                $targetUserId = (string) ($user['user_id'] ?? '');
            } else {
                ensure_permission($user, 'reservation.create_internal', 'reservasi.php');
                $targetUserId = trim((string) ($_POST['id_pengguna'] ?? ''));
                if ($targetUserId === '') {
                    redirect_with_flash('reservasi.php', 'error', 'Pilih pelanggan terlebih dahulu sebelum membuat reservasi.');
                }

                $stmt = db()->prepare("SELECT id_pengguna FROM pengguna WHERE id_pengguna = :id AND role = 'pelanggan' LIMIT 1");
                $stmt->execute(['id' => $targetUserId]);
                if (!$stmt->fetchColumn()) {
                    redirect_with_flash('reservasi.php', 'error', 'Pelanggan yang dipilih tidak ditemukan.');
                }
            }

            $id = next_prefixed_id('reservasi', 'id_reservasi', 'RV');
            $idLap = trim($_POST['id_lapangan'] ?? '');
            $tanggal = trim($_POST['tanggal_booking'] ?? '');
            $mulai = trim($_POST['jam_mulai'] ?? '');
            $selesai = trim($_POST['jam_selesai'] ?? '');

            ensure_reservation_schedule_is_available($idLap, $tanggal, $mulai, $selesai);
            [$durasi, $total] = hitung_total_biaya($idLap, $mulai, $selesai);
            if ($durasi <= 0) {
                redirect_with_flash('reservasi.php', 'error', 'Jam selesai harus lebih besar dari jam mulai.');
            }

            $stmt = db()->prepare('INSERT INTO reservasi (id_reservasi, id_pengguna, id_lapangan, tanggal_booking, jam_mulai, jam_selesai, durasi, total_biaya, status_reservasi) VALUES (:id, :id_pengguna, :id_lapangan, :tanggal, :mulai, :selesai, :durasi, :total, :status)');
            $stmt->execute([
                'id' => $id,
                'id_pengguna' => $targetUserId,
                'id_lapangan' => $idLap,
                'tanggal' => $tanggal,
                'mulai' => $mulai,
                'selesai' => $selesai,
                'durasi' => $durasi,
                'total' => $total,
                'status' => 'pending'
            ]);

            redirect_with_flash('reservasi.php', 'success', 'Reservasi berhasil dibuat.');
        }

        if ($action === 'update') {
            redirect_with_flash('reservasi.php', 'error', 'Data reservasi pelanggan tidak bisa diubah lagi. Silakan cek status dan lanjutkan ke pembayaran bila diperlukan.');
            $id = trim($_POST['id_reservasi'] ?? '');
            $idLap = trim($_POST['id_lapangan'] ?? '');
            $tanggal = trim($_POST['tanggal_booking'] ?? '');
            $mulai = trim($_POST['jam_mulai'] ?? '');
            $selesai = trim($_POST['jam_selesai'] ?? '');

            ensure_reservation_schedule_is_available($idLap, $tanggal, $mulai, $selesai, $id);
            [$durasi, $total] = hitung_total_biaya($idLap, $mulai, $selesai);
            if ($durasi <= 0) {
                redirect_with_flash('reservasi.php', 'error', 'Jam selesai harus lebih besar dari jam mulai.');
            }

            $stmt = db()->prepare('UPDATE reservasi SET id_lapangan=:id_lap, tanggal_booking=:tanggal, jam_mulai=:mulai, jam_selesai=:selesai, durasi=:durasi, total_biaya=:total WHERE id_reservasi=:id AND id_pengguna=:id_pengguna');
            $stmt->execute([
                'id' => $id,
                'id_pengguna' => $user['user_id'],
                'id_lap' => $idLap,
                'tanggal' => $tanggal,
                'mulai' => $mulai,
                'selesai' => $selesai,
                'durasi' => $durasi,
                'total' => $total
            ]);

            redirect_with_flash('reservasi.php', 'success', 'Reservasi berhasil diupdate.');
        }

        if ($action === 'update_status') {
            ensure_permission($user, 'reservation.status.update', 'reservasi.php');
            $id = trim($_POST['id_reservasi'] ?? '');
            $status = trim($_POST['status_reservasi'] ?? 'pending');
            if (strtolower($status) === 'dikonfirmasi') {
                $stmt = db()->prepare('SELECT id_lapangan, tanggal_booking, jam_mulai, jam_selesai FROM reservasi WHERE id_reservasi = :id LIMIT 1');
                $stmt->execute(['id' => $id]);
                $reservationToConfirm = $stmt->fetch();
                if (!$reservationToConfirm) {
                    redirect_with_flash('reservasi.php', 'error', 'Reservasi yang akan dikonfirmasi tidak ditemukan.');
                }

                ensure_reservation_schedule_is_available(
                    $reservationToConfirm['id_lapangan'] ?? '',
                    $reservationToConfirm['tanggal_booking'] ?? '',
                    substr((string) ($reservationToConfirm['jam_mulai'] ?? ''), 0, 5),
                    substr((string) ($reservationToConfirm['jam_selesai'] ?? ''), 0, 5),
                    $id
                );
            }
            $stmt = db()->prepare('UPDATE reservasi SET status_reservasi=:status WHERE id_reservasi=:id');
            $stmt->execute([
                'id' => $id,
                'status' => $status
            ]);
            redirect_with_flash('reservasi.php', 'success', 'Status reservasi berhasil diupdate.');
        }

        if ($action === 'delete') {
            $id = trim($_POST['id_reservasi'] ?? '');
            if ($canDeleteOwnReservation) {
                $stmt = db()->prepare('SELECT COALESCE(SUM(jumlah_bayar), 0) FROM pembayaran WHERE id_reservasi = :id');
                $stmt->execute(['id' => $id]);
                $totalPaidForDelete = (float) $stmt->fetchColumn();
                if ($totalPaidForDelete > 0) {
                    redirect_with_flash('reservasi.php', 'error', 'Reservasi yang sudah memiliki pembayaran tidak bisa dihapus lagi. Silakan lanjutkan atau lihat pembayaran yang sudah dibuat.');
                }

                $stmt = db()->prepare('DELETE FROM reservasi WHERE id_reservasi=:id AND id_pengguna=:id_pengguna');
                $stmt->execute([
                    'id' => $id,
                    'id_pengguna' => $user['user_id']
                ]);
            } elseif ($canDeleteAnyReservation) {
                $stmt = db()->prepare('DELETE FROM reservasi WHERE id_reservasi=:id');
                $stmt->execute(['id' => $id]);
            } else {
                ensure_permission($user, 'reservation.delete_any', 'reservasi.php');
            }
            redirect_with_flash('reservasi.php', 'success', 'Reservasi berhasil dihapus.');
        }
    } catch (Throwable $e) {
        redirect_with_flash('reservasi.php', 'error', 'Operasi reservasi gagal: ' . $e->getMessage());
    }
}

$lapangan = db()->query('SELECT id_lapangan, nama_lapangan, jenis_lantai, harga_per_jam, jam_buka, jam_tutup, status FROM lapangan ORDER BY id_lapangan')->fetchAll();
$pelangganOptions = [];
if ($canCreateInternalReservation) {
    $pelangganOptions = db()->query("SELECT id_pengguna, nama_lengkap, email FROM pengguna WHERE role = 'pelanggan' ORDER BY nama_lengkap ASC")->fetchAll();
}
$reservasiBooked = db()->query("SELECT id_pengguna, id_lapangan, tanggal_booking, jam_mulai, jam_selesai, status_reservasi FROM reservasi WHERE status_reservasi <> 'dibatalkan'")->fetchAll();
$paymentTotals = [];
$paymentStatuses = [];
$paymentRows = db()->query('SELECT id_reservasi, jumlah_bayar, status_pembayaran FROM pembayaran')->fetchAll();
foreach ($paymentRows as $paymentRow) {
    $reservationId = (string) ($paymentRow['id_reservasi'] ?? '');
    if ($reservationId === '') {
        continue;
    }
    if (!isset($paymentTotals[$reservationId])) {
        $paymentTotals[$reservationId] = 0.0;
    }
    $paymentTotals[$reservationId] += (float) ($paymentRow['jumlah_bayar'] ?? 0);

    $statusKey = strtolower(trim((string) ($paymentRow['status_pembayaran'] ?? 'pending')));
    if (!isset($paymentStatuses[$reservationId])) {
        $paymentStatuses[$reservationId] = [];
    }
    $paymentStatuses[$reservationId][$statusKey] = true;
}

if ($canViewAllReservations) {
    $reservasiRows = db()->query('SELECT r.*, p.nama_lengkap, l.nama_lapangan FROM reservasi r JOIN pengguna p ON p.id_pengguna=r.id_pengguna JOIN lapangan l ON l.id_lapangan=r.id_lapangan ORDER BY r.created_at DESC')->fetchAll();
} else {
    $stmt = db()->prepare('SELECT r.*, l.nama_lapangan FROM reservasi r JOIN lapangan l ON l.id_lapangan=r.id_lapangan WHERE r.id_pengguna=:id ORDER BY r.created_at DESC');
    $stmt->execute(['id' => $user['user_id']]);
    $reservasiRows = $stmt->fetchAll();
}

$reservationConflicts = [];
if ($canViewAllReservations) {
    $activeReservations = array_values(array_filter($reservasiRows, static function (array $row): bool {
        return strtolower((string) ($row['status_reservasi'] ?? '')) !== 'dibatalkan';
    }));

    $countActiveReservations = count($activeReservations);
    for ($i = 0; $i < $countActiveReservations; $i++) {
        for ($j = $i + 1; $j < $countActiveReservations; $j++) {
            $left = $activeReservations[$i];
            $right = $activeReservations[$j];

            if ((string) ($left['id_lapangan'] ?? '') !== (string) ($right['id_lapangan'] ?? '')) {
                continue;
            }
            if ((string) ($left['tanggal_booking'] ?? '') !== (string) ($right['tanggal_booking'] ?? '')) {
                continue;
            }

            $leftStart = substr((string) ($left['jam_mulai'] ?? ''), 0, 5);
            $leftEnd = substr((string) ($left['jam_selesai'] ?? ''), 0, 5);
            $rightStart = substr((string) ($right['jam_mulai'] ?? ''), 0, 5);
            $rightEnd = substr((string) ($right['jam_selesai'] ?? ''), 0, 5);

            if ($leftStart < $rightEnd && $leftEnd > $rightStart) {
                $leftId = (string) ($left['id_reservasi'] ?? '');
                $rightId = (string) ($right['id_reservasi'] ?? '');
                $reservationConflicts[$leftId][] = [
                    'id' => $rightId,
                    'time' => $rightStart . '-' . $rightEnd,
                    'name' => (string) ($right['nama_lengkap'] ?? ''),
                ];
                $reservationConflicts[$rightId][] = [
                    'id' => $leftId,
                    'time' => $leftStart . '-' . $leftEnd,
                    'name' => (string) ($left['nama_lengkap'] ?? ''),
                ];
            }
        }
    }
}

$reservasiFeedback = reservasi_feedback_box($flash, (string) $user['role']);
$pendingReservationCount = $canViewAllReservations
    ? (int) db()->query("SELECT COUNT(*) FROM reservasi WHERE LOWER(status_reservasi) = 'pending'")->fetchColumn()
    : 0;
$conflictReservationCount = count($reservationConflicts);
if ($canViewAllReservations && $activeReservationFilter === 'conflict') {
    $reservasiRows = array_values(array_filter($reservasiRows, static function (array $row) use ($reservationConflicts): bool {
        $reservationId = (string) ($row['id_reservasi'] ?? '');
        return isset($reservationConflicts[$reservationId]);
    }));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservasi | <?= h($company['short_name']) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../assets/panel-theme.css">
    <style>
        .toolbar { display: flex; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
        .desc-muted { color: #aab5d3; font-size: 13px; }
        .filter-switch {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin: 10px 0 14px;
        }
        .filter-switch a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            padding: 0 14px;
            border-radius: 999px;
            text-decoration: none;
            border: 1px solid rgba(255,255,255,.12);
            background: rgba(255,255,255,.04);
            color: #dbeafe;
            font-size: 13px;
            font-weight: 600;
        }
        .filter-switch a.is-active {
            background: linear-gradient(135deg, rgba(248,113,113,.18), rgba(34,211,238,.18));
            border-color: rgba(248,113,113,.24);
            color: #fff;
        }
        .status-block { display: grid; gap: 8px; max-width: 220px; }
        .status-actions {
            display: grid;
            gap: 8px;
            justify-content: start;
            width: 160px;
        }
        .status-actions > .form-reset,
        .status-actions > a.btn {
            width: 160px !important;
            min-width: 160px !important;
            max-width: 160px !important;
        }
        .status-actions .btn,
        .status-actions a.btn,
        .status-actions .form-reset .btn {
            width: 160px !important;
            min-width: 160px !important;
            max-width: 160px !important;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }
        .status-action-btn {
            width: 160px !important;
            min-width: 160px !important;
            max-width: 160px !important;
            box-sizing: border-box;
        }
        .status-action-btn:disabled,
        .status-action-btn.is-disabled {
            opacity: .5;
            cursor: not-allowed;
            filter: saturate(.65);
            pointer-events: none;
        }
        .amount-line { color: #9eb0d2; font-size: 12px; margin-top: 6px; display: inline-block; }
        .conflict-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-top: 8px;
            padding: 5px 10px;
            border-radius: 999px;
            background: rgba(248, 113, 113, .16);
            color: #fecaca;
            border: 1px solid rgba(248, 113, 113, .22);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .02em;
        }
        .conflict-note {
            display: block;
            margin-top: 6px;
            color: #fca5a5;
            font-size: 12px;
            line-height: 1.7;
        }
        .conflict-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 6px;
            color: #fecaca;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
        }
        .conflict-link:hover {
            text-decoration: underline;
        }
        tr.reservation-row:target td {
            background: rgba(34, 211, 238, .10);
            box-shadow: inset 0 0 0 1px rgba(34, 211, 238, .18);
        }
        .promo-banner {
            margin-bottom: 14px;
            padding: 14px 16px;
            border-radius: 16px;
            border: 1px solid rgba(255, 186, 116, .28);
            background: linear-gradient(135deg, rgba(251,146,60,.14), rgba(34,211,238,.08));
        }
        .promo-banner strong { display:block; color:#ffe8c7; font-size: 15px; margin-bottom: 6px; }
        .promo-banner span { display:inline-flex; margin-top:10px; padding:6px 10px; border-radius:999px; border:1px dashed rgba(255,255,255,.22); color:#fff; font-size:12px; font-weight:700; letter-spacing:.08em; }
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
        .pay-pill {
            display:inline-flex;
            align-items:center;
            justify-content:center;
            border-radius:999px;
            padding:4px 10px;
            font-size:11px;
            font-weight:700;
            margin-top:8px;
        }
        .pay-pill.pending { background: rgba(244, 63, 94, .18); color:#fecdd3; }
        .pay-pill.dp { background: rgba(59, 130, 246, .18); color:#bfdbfe; }
        .pay-pill.lunas { background: rgba(34, 197, 94, .18); color:#bbf7d0; }
        .cinema-booking {
            display: grid;
            gap: 16px;
        }
        .cinema-toolbar {
            display: grid;
            grid-template-columns: minmax(0, 260px) 1fr;
            gap: 14px;
            align-items: start;
        }
        .cinema-card {
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 18px;
            background: linear-gradient(145deg, rgba(31, 16, 54, .94), rgba(16, 8, 31, .92));
            padding: 14px;
        }
        .cinema-card h4 {
            margin: 0 0 8px;
            font-size: 15px;
            color: #eef2ff;
        }
        .cinema-card p {
            margin: 0;
            color: #aab5d3;
            font-size: 13px;
            line-height: 1.7;
        }
        .duration-pills {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        .duration-pill {
            border: 1px solid rgba(255,255,255,.14);
            border-radius: 999px;
            background: rgba(255,255,255,.06);
            color: #e8eeff;
            padding: 9px 12px;
            font: 700 12px 'Plus Jakarta Sans', sans-serif;
            cursor: pointer;
        }
        .duration-pill.active {
            background: linear-gradient(135deg, #bef264, #22d3ee);
            color: #140019;
            border-color: transparent;
        }
        .cinema-screen {
            position: relative;
            margin: 4px auto 2px;
            width: min(520px, 100%);
            text-align: center;
            color: #cfe9ff;
            font-size: 12px;
            letter-spacing: .24em;
            text-transform: uppercase;
        }
        .cinema-screen::before {
            content: "";
            display: block;
            height: 16px;
            border-radius: 999px;
            background: linear-gradient(90deg, rgba(34,211,238,.28), rgba(255,255,255,.9), rgba(34,211,238,.28));
            box-shadow: 0 10px 30px rgba(34,211,238,.18);
            margin-bottom: 12px;
        }
        .seat-legend {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            color: #aab5d3;
            font-size: 12px;
        }
        .seat-legend span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .seat-legend i {
            display: inline-block;
            width: 14px;
            height: 14px;
            border-radius: 4px 4px 8px 8px;
        }
        .court-filter-bar {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 4px;
            margin-bottom: 12px;
        }
        .court-filter-btn {
            border: 1px solid rgba(255,255,255,.14);
            border-radius: 999px;
            background: rgba(255,255,255,.06);
            color: #e8eeff;
            padding: 8px 12px;
            font: 700 12px 'Plus Jakarta Sans', sans-serif;
            cursor: pointer;
        }
        .court-filter-btn.active {
            background: linear-gradient(135deg, #bef264, #22d3ee);
            color: #140019;
            border-color: transparent;
        }
        .court-picker {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }
        .court-chip {
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 18px;
            background: rgba(255,255,255,.05);
            padding: 14px;
            color: #eef2ff;
            text-align: left;
            cursor: pointer;
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        }
        .court-chip strong {
            display: block;
            font-size: 15px;
            margin-bottom: 4px;
        }
        .court-chip span {
            display: block;
            color: #9eb0d2;
            font-size: 12px;
            line-height: 1.6;
        }
        .court-chip.active {
            background: linear-gradient(135deg, rgba(190,242,100,.16), rgba(34,211,238,.16));
            border-color: rgba(190,242,100,.34);
            box-shadow: 0 12px 24px rgba(34,211,238,.12);
        }
        .court-chip:hover {
            transform: translateY(-2px);
        }
        .seat-grid-wrap {
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 22px;
            background: radial-gradient(circle at top, rgba(34,211,238,.08), transparent 30%), rgba(10, 5, 28, .62);
            padding: 18px;
            overflow: auto;
        }
        .seat-time-header,
        .seat-row {
            display: grid;
            grid-template-columns: repeat(17, minmax(56px, 1fr));
            gap: 8px;
            min-width: 980px;
        }
        .seat-time-header {
            margin-bottom: 10px;
            color: #92a4cb;
            font-size: 11px;
            letter-spacing: .06em;
            text-transform: uppercase;
        }
        .seat-time-header div {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
        }
        .seat-row { margin-bottom: 10px; }
        .active-court-bar {
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
            color: #eaf2ff;
        }
        .active-court-bar strong {
            display: block;
            font-size: 17px;
        }
        .active-court-bar span {
            color: #9eb0d2;
            font-size: 13px;
        }
        .seat {
            min-height: 54px;
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 12px 12px 18px 18px;
            background: rgba(255,255,255,.06);
            color: #eff6ff;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font: 700 12px 'Plus Jakarta Sans', sans-serif;
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        }
        .seat small {
            display: block;
            font-size: 10px;
            font-weight: 600;
            color: #a8bddf;
        }
        .seat:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(34,211,238,.12);
        }
        .seat.available { background: rgba(255,255,255,.05); }
        .seat.selected {
            background: linear-gradient(135deg, #bef264, #22d3ee);
            color: #140019;
            border-color: transparent;
            box-shadow: 0 12px 22px rgba(163,230,53,.18);
        }
        .seat.selected small { color: rgba(20,0,25,.72); }
        .seat.booked {
            background: rgba(244,63,94,.22);
            color: #ffd7df;
            border-color: rgba(244,63,94,.28);
            cursor: not-allowed;
        }
        .seat.booked small { color: #ffc7d4; }
        .seat.own-booked {
            background: rgba(34,197,94,.22);
            color: #dcfce7;
            border-color: rgba(34,197,94,.28);
            cursor: not-allowed;
        }
        .seat.own-booked small { color: #bbf7d0; }
        .seat.blocked {
            background: rgba(148,163,184,.18);
            color: #d6deee;
            border-color: rgba(148,163,184,.24);
            cursor: not-allowed;
        }
        .seat-summary {
            display: grid;
            grid-template-columns: 1.2fr .8fr;
            gap: 14px;
            align-items: start;
        }
        .seat-summary-box {
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 18px;
            background: rgba(255,255,255,.04);
            padding: 14px;
        }
        .seat-summary-box h4 {
            margin: 0 0 10px;
            font-size: 15px;
        }
        .seat-summary-box ul {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            gap: 8px;
            color: #d7e1f7;
            font-size: 13px;
        }
        .seat-summary-box li strong {
            color: #f7fbff;
        }
        .seat-empty {
            color: #9eb0d2;
            font-size: 13px;
            line-height: 1.7;
        }
        .seat-hint {
            margin-top: 10px;
            color: #9ed9ff;
            font-size: 13px;
            line-height: 1.7;
        }
        .visually-hidden {
            position: absolute !important;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }
        .internal-reservation-form {
            display: grid;
            gap: 14px;
        }
        .internal-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }
        @media (max-width: 980px) {
            .cinema-toolbar,
            .seat-summary,
            .internal-form-grid {
                grid-template-columns: 1fr;
            }
            .court-picker {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="panel-page">
    <div class="panel-wrap">
        <div class="panel">
            <div class="panel-title">
                <div>
                    <h2>Reservasi <?= h($company['short_name']) ?></h2>
                    <p class="panel-subtitle">Kelola booking lapangan untuk <?= h($company['name']) ?> di <?= h($company['location']) ?>.</p>
                </div>
                <div class="links-inline">
                    <a class="link-muted" href="dashboard.php">Dashboard</a>
                    <a class="link-muted" href="../actions/logout.php">Logout</a>
                </div>
            </div>
            <p class="meta">Login: <strong><?= h($user['name']) ?></strong> (<?= h($user['role']) ?>) • Jam venue <?= h($company['hours']) ?></p>
            <?php if ($reservasiFeedback): ?>
                <div class="status-box <?= h($reservasiFeedback['tone']) ?>"<?= $reservasiFeedback['tone'] === 'success' ? ' data-autoclose="true"' : '' ?>>
                    <div class="status-box-head">
                        <span class="status-box-icon" aria-hidden="true"><?= $reservasiFeedback['tone'] === 'success' ? '&#10003;' : ($reservasiFeedback['tone'] === 'error' ? '!' : '&#105;i') ?></span>
                        <div class="status-box-title"><?= h($reservasiFeedback['title']) ?></div>
                        <button class="status-box-close" type="button" data-close-status aria-label="Tutup notifikasi">&times;</button>
                    </div>
                    <div class="status-box-copy"><?= h($reservasiFeedback['body']) ?></div>
                    <?php if (!empty($reservasiFeedback['steps'])): ?>
                        <ul class="status-box-steps">
                            <?php foreach ($reservasiFeedback['steps'] as $step): ?>
                                <li><?= h($step) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if (!$isPelanggan && $pendingReservationCount > 0): ?>
                <div class="status-box info">
                    <div class="status-box-head">
                        <span class="status-box-icon" aria-hidden="true">&#105;i</span>
                        <div class="status-box-title">Ada <?= h((string) $pendingReservationCount) ?> reservasi yang menunggu tindak lanjut</div>
                        <button class="status-box-close" type="button" data-close-status aria-label="Tutup notifikasi">&times;</button>
                    </div>
                    <div class="status-box-copy">Admin venue perlu memeriksa detail jadwal, kecocokan slot, dan progres pembayaran agar pelanggan tahu langkah berikutnya.</div>
                    <ul class="status-box-steps">
                        <li>Cek reservasi yang masih berstatus pending pada tabel di bawah.</li>
                        <li>Sesuaikan status reservasi setelah data booking dan ketersediaan lapangan diverifikasi.</li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($isPelanggan): ?>
            <div class="panel">
                <h3 style="margin-top:0;">Buat Reservasi Baru</h3>
                <p class="desc-muted" style="margin-top:-4px; margin-bottom:14px;">Pilih slot main yang tersedia di <?= h($company['short_name']) ?>. Booking pelanggan akan masuk ke operasional venue ini.</p>
                <?php if ($selectedPromo): ?>
                    <div class="promo-banner">
                        <strong>Promo aktif: <?= h($selectedPromo['title']) ?></strong>
                        <div class="desc-muted"><?= h($selectedPromo['detail']) ?> | <?= h($selectedPromo['period']) ?></div>
                        <div class="desc-muted" style="margin-top:6px;">Promo ini dibawa dari halaman promo. Verifikasi akhir tetap dilakukan saat proses reservasi atau pembayaran.</div>
                        <span><?= h($selectedPromo['code']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($selectedCoach): ?>
                    <div class="promo-banner" style="border-color: rgba(163, 230, 53, .28); background: linear-gradient(135deg, rgba(163,230,53,.12), rgba(34,211,238,.08));">
                        <strong>Coach dipilih: <?= h($selectedCoach['name']) ?></strong>
                        <div class="desc-muted"><?= h($selectedCoach['specialty']) ?> | <?= h($selectedCoach['level']) ?> | <?= h($selectedCoach['price']) ?></div>
                        <div class="desc-muted" style="margin-top:6px;"><?= h($selectedCoach['availability']) ?></div>
                        <span><?= h($selectedCoach['slug']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($selectedPartner): ?>
                    <div class="promo-banner" style="border-color: rgba(34, 211, 238, .28); background: linear-gradient(135deg, rgba(34,211,238,.12), rgba(163,230,53,.08));">
                        <strong>Partner dipilih: <?= h($selectedPartner['name']) ?></strong>
                        <div class="desc-muted"><?= h($selectedPartner['city']) ?> | <?= h($selectedPartner['skill']) ?> | <?= h($selectedPartner['play']) ?></div>
                        <div class="desc-muted" style="margin-top:6px;">Preferensi jadwal: <?= h($selectedPartner['schedule']) ?></div>
                        <span><?= h($selectedPartner['slug']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($selectedCourtId !== ''): ?>
                    <div class="promo-banner" style="border-color: rgba(255, 255, 255, .22); background: linear-gradient(135deg, rgba(255,255,255,.10), rgba(34,211,238,.08));">
                        <strong>Lapangan dipilih: <?= h($selectedCourtId) ?></strong>
                        <div class="desc-muted">Pilihan lapangan dibawa dari halaman detail court dan akan otomatis dipilih di form reservasi jika tersedia.</div>
                        <span><?= h($selectedCourtId) ?></span>
                    </div>
                <?php endif; ?>
                <form method="POST" class="form-reset" id="cinemaReservationForm">
                    <input type="hidden" name="action" value="create">
                    <div class="cinema-booking">
                        <div class="cinema-toolbar">
                            <div class="cinema-card">
                                <h4>Pilih tanggal main</h4>
                                <input type="date" name="tanggal_booking" id="bookingDateInput" value="<?= h(date('Y-m-d')) ?>" min="<?= h(date('Y-m-d')) ?>" required>
                                <p style="margin-top:10px;">Setelah pilih tanggal, klik kursi slot di bawah. Setiap kursi mewakili jam mulai pada satu lapangan.</p>
                            </div>
                            <div class="cinema-card">
                                <h4>Pilih durasi seperti pilih tiket</h4>
                                <p>Durasi memengaruhi slot yang tersedia. Kalau pilih `2 jam`, hanya kursi dengan slot berurutan yang masih kosong yang bisa dipilih.</p>
                                <div class="duration-pills" id="durationPills">
                                    <button type="button" class="duration-pill active" data-hours="1">1 jam</button>
                                    <button type="button" class="duration-pill" data-hours="2">2 jam</button>
                                    <button type="button" class="duration-pill" data-hours="3">3 jam</button>
                                </div>
                            </div>
                        </div>

                        <div class="cinema-screen">Area Pilih Slot</div>
                        <div class="seat-legend">
                            <span><i style="background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.12);"></i>Tersedia</span>
                            <span><i style="background:linear-gradient(135deg, #bef264, #22d3ee);"></i>Dipilih</span>
                            <span><i style="background:rgba(34,197,94,.22); border:1px solid rgba(34,197,94,.28);"></i>Punya kamu</span>
                            <span><i style="background:rgba(244,63,94,.22); border:1px solid rgba(244,63,94,.28);"></i>Sudah dibooking</span>
                            <span><i style="background:rgba(148,163,184,.18); border:1px solid rgba(148,163,184,.24);"></i>Tidak cocok dengan durasi</span>
                        </div>

                        <div class="court-filter-bar" id="courtFilterBar"></div>
                        <div class="court-picker" id="courtPicker"></div>

                        <div class="seat-grid-wrap">
                            <div class="active-court-bar" id="activeCourtBar"></div>
                            <div class="seat-time-header" id="seatTimeHeader"></div>
                            <div id="seatGrid"></div>
                        </div>

                        <div class="seat-summary">
                            <div class="seat-summary-box">
                                <h4>Ringkasan Reservasi</h4>
                                <div id="seatSummaryEmpty" class="seat-empty">Belum ada slot dipilih. Pilih satu kursi slot untuk mengisi lapangan, jam mulai, dan jam selesai secara otomatis.</div>
                                <div id="seatSummaryHint" class="seat-hint">Pilih tanggal dan durasi untuk melihat estimasi harga real-time.</div>
                                <ul id="seatSummaryList" class="visually-hidden">
                                    <li><strong>Lapangan:</strong> <span id="summaryCourt">-</span></li>
                                    <li><strong>Tanggal:</strong> <span id="summaryDate">-</span></li>
                                    <li><strong>Jam:</strong> <span id="summaryTime">-</span></li>
                                    <li><strong>Durasi:</strong> <span id="summaryDuration">-</span></li>
                                    <li><strong>Estimasi total:</strong> <span id="summaryTotal">-</span></li>
                                </ul>
                            </div>
                        </div>
                        <input type="hidden" name="id_lapangan" id="hiddenCourtInput" value="<?= h($selectedCourtId) ?>" required>
                        <input type="hidden" name="jam_mulai" id="hiddenStartTime" required>
                        <input type="hidden" name="jam_selesai" id="hiddenEndTime" required>
                    </div>
                    <p style="margin-top:10px;"><button class="btn btn-create" type="submit">Buat Reservasi</button></p>
                </form>
            </div>
        <?php elseif ($canCreateInternalReservation): ?>
            <div class="panel">
                <h3 style="margin-top:0;">Tambah Reservasi Pelanggan</h3>
                <p class="desc-muted" style="margin-top:-4px; margin-bottom:14px;">Admin dan kasir bisa membuat reservasi langsung untuk pelanggan dari panel operasional venue ini.</p>
                <form method="POST" class="form-reset internal-reservation-form">
                    <input type="hidden" name="action" value="create">
                    <div class="internal-form-grid">
                        <select name="id_pengguna" required>
                            <option value="">Pilih pelanggan</option>
                            <?php foreach ($pelangganOptions as $pelanggan): ?>
                                <option value="<?= h($pelanggan['id_pengguna']) ?>"><?= h($pelanggan['nama_lengkap'] . ' - ' . $pelanggan['email']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="id_lapangan" required>
                            <option value="">Pilih lapangan</option>
                            <?php foreach ($lapangan as $l): ?>
                                <?php if (($l['status'] ?? '') !== 'tersedia') { continue; } ?>
                                <option value="<?= h($l['id_lapangan']) ?>"><?= h($l['id_lapangan'] . ' - ' . $l['nama_lapangan'] . ' (' . $l['jenis_lantai'] . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="date" name="tanggal_booking" value="<?= h(date('Y-m-d')) ?>" min="<?= h(date('Y-m-d')) ?>" required>
                        <div class="grid-2">
                            <input type="time" name="jam_mulai" min="06:00" max="22:00" required>
                            <input type="time" name="jam_selesai" min="06:00" max="22:00" required>
                        </div>
                    </div>
                    <p style="margin-top:4px;"><button class="btn btn-create" type="submit">Buat Reservasi User</button></p>
                </form>
            </div>
        <?php endif; ?>

        <div class="panel">
            <h3 style="margin-top:0;">Data Reservasi</h3>
            <?php if (!$isPelanggan): ?>
                <div class="filter-switch">
                    <a href="reservasi.php?filter=all" class="<?= $activeReservationFilter !== 'conflict' ? 'is-active' : '' ?>">Semua Reservasi</a>
                    <a href="reservasi.php?filter=conflict" class="<?= $activeReservationFilter === 'conflict' ? 'is-active' : '' ?>">Hanya yang Bentrok<?= $conflictReservationCount > 0 ? ' (' . h((string) $conflictReservationCount) . ')' : '' ?></a>
                </div>
                <?php if ($activeReservationFilter === 'conflict'): ?>
                    <p class="desc-muted" style="margin-top:-4px; margin-bottom:12px;">Menampilkan hanya reservasi yang terdeteksi bentrok jadwal agar lebih cepat dibersihkan oleh admin atau kasir.</p>
                <?php endif; ?>
            <?php endif; ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <?php if (!$isPelanggan): ?><th>Pelanggan</th><?php endif; ?>
                            <th>Detail</th>
                            <th style="width: 250px;">Status / Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$reservasiRows): ?>
                            <tr>
                                <td colspan="<?= $isPelanggan ? '3' : '4' ?>" class="desc-muted" style="padding:16px;">Tidak ada data reservasi untuk filter yang dipilih.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($reservasiRows as $r): ?>
                            <?php
                            $reservationId = (string) ($r['id_reservasi'] ?? '');
                            $totalPaid = (float) ($paymentTotals[$reservationId] ?? 0);
                            $totalCost = (float) ($r['total_biaya'] ?? 0);
                            $remainingCost = max(0, $totalCost - $totalPaid);
                            $statusFlags = $paymentStatuses[$reservationId] ?? [];
                            $conflicts = $reservationConflicts[$reservationId] ?? [];
                            $hasConflict = !empty($conflicts);
                            $firstConflict = $hasConflict ? $conflicts[0] : null;
                            if (isset($statusFlags['lunas'])) {
                                $paymentLabel = 'Lunas';
                                $paymentClass = 'lunas';
                            } elseif (isset($statusFlags['dp'])) {
                                $paymentLabel = 'DP';
                                $paymentClass = 'dp';
                            } elseif (isset($statusFlags['pending']) && $totalPaid > 0) {
                                $paymentLabel = 'Pending';
                                $paymentClass = 'pending';
                            } elseif ($totalPaid <= 0) {
                                $paymentLabel = 'Belum Bayar';
                                $paymentClass = 'pending';
                            } else {
                                $paymentLabel = $totalPaid < $totalCost ? 'DP' : 'Lunas';
                                $paymentClass = $totalPaid < $totalCost ? 'dp' : 'lunas';
                            }
                            ?>
                            <tr id="reservasi-<?= h($reservationId) ?>" class="reservation-row">
                                <td><strong><?= h($r['id_reservasi']) ?></strong></td>
                                <?php if (!$isPelanggan): ?><td><?= h($r['nama_lengkap']) ?></td><?php endif; ?>
                                <td>
                                    <?php if ($isPelanggan): ?>
                                        <strong><?= h($r['nama_lapangan']) ?></strong><br>
                                        <span class="desc-muted"><?= h($r['tanggal_booking']) ?> <?= h(substr((string) $r['jam_mulai'], 0, 5)) ?> - <?= h(substr((string) $r['jam_selesai'], 0, 5)) ?></span><br>
                                        <span class="desc-muted">Durasi <?= h($r['durasi']) ?> jam | Total Rp<?= h(number_format((int) $r['total_biaya'], 0, ',', '.')) ?></span><br>
                                        <span class="desc-muted">Dibayar Rp<?= h(number_format((int) $totalPaid, 0, ',', '.')) ?> | Sisa Rp<?= h(number_format((int) $remainingCost, 0, ',', '.')) ?></span><br>
                                        <span class="pay-pill <?= h($paymentClass) ?>"><?= h($paymentLabel) ?></span>
                                    <?php else: ?>
                                        <strong><?= h($r['nama_lapangan']) ?></strong><br>
                                        <span class="desc-muted"><?= h($r['tanggal_booking']) ?> <?= h(substr((string) $r['jam_mulai'], 0, 5)) ?> - <?= h(substr((string) $r['jam_selesai'], 0, 5)) ?></span><br>
                                        <span class="desc-muted">Durasi <?= h($r['durasi']) ?> jam | Total Rp<?= h(number_format((int) $r['total_biaya'], 0, ',', '.')) ?></span><br>
                                        <span class="desc-muted">Dibayar Rp<?= h(number_format((int) $totalPaid, 0, ',', '.')) ?> | Sisa Rp<?= h(number_format((int) $remainingCost, 0, ',', '.')) ?></span><br>
                                        <span class="pay-pill <?= h($paymentClass) ?>"><?= h($paymentLabel) ?></span>
                                        <?php if ($hasConflict): ?>
                                            <span class="conflict-pill">Bentrok Jadwal</span>
                                            <small class="conflict-note">Bentrok dengan <?= h((string) ($firstConflict['id'] ?? '-')) ?><?= !empty($firstConflict['time']) ? ' pada jam ' . h((string) $firstConflict['time']) : '' ?><?= !empty($firstConflict['name']) ? ' (' . h((string) $firstConflict['name']) . ')' : '' ?>.</small>
                                            <?php if (!empty($firstConflict['id'])): ?>
                                                <a class="conflict-link" href="reservasi.php?filter=<?= h($activeReservationFilter === 'conflict' ? 'conflict' : 'all') ?>#reservasi-<?= h((string) $firstConflict['id']) ?>">
                                                    Lompat ke reservasi pasangan <span aria-hidden="true">&rarr;</span>
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="status-block">
                                        <?php if ($canUpdateReservationStatus): ?>
                                            <form method="POST" class="form-reset">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="id_reservasi" value="<?= h($r['id_reservasi']) ?>">
                                                <select name="status_reservasi">
                                                    <option value="pending" <?= $r['status_reservasi'] === 'pending' ? 'selected' : '' ?>>pending</option>
                                                    <option value="dikonfirmasi" <?= $r['status_reservasi'] === 'dikonfirmasi' ? 'selected' : '' ?>>dikonfirmasi</option>
                                                    <option value="dibatalkan" <?= $r['status_reservasi'] === 'dibatalkan' ? 'selected' : '' ?>>dibatalkan</option>
                                                </select>
                                                <p style="margin-top:6px;"><button class="btn btn-save" type="submit">Update Status</button></p>
                                            </form>
                                        <?php elseif ($isPelanggan): ?>
                                            <span class="pay-pill <?= h($paymentClass) ?>"><?= h($paymentLabel) ?></span>
                                            <small class="amount-line">Status reservasi: <?= h($r['status_reservasi']) ?></small>
                                        <?php else: ?>
                                            <strong><?= h($r['status_reservasi']) ?></strong>
                                            <?php if ($hasConflict): ?>
                                                <small class="conflict-note">Perlu dibersihkan sebelum reservasi dikonfirmasi atau diproses lebih lanjut.</small>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php if ($isPelanggan || $canDeleteAnyReservation || $canDeleteOwnReservation): ?>
                                                <div class="status-actions">
                                                    <?php if ($isPelanggan): ?>
                                                        <?php
                                                        $hasPaymentHistory = $totalPaid > 0;
                                                        $canDeleteReservation = !$hasPaymentHistory;
                                                        $paymentActionLabel = $remainingCost <= 0 ? 'Lihat Pembayaran' : ($hasPaymentHistory ? 'Lanjut Bayar' : 'Bayar');
                                                        ?>
                                                        <a class="btn btn-save status-action-btn" href="pembayaran.php?reservasi=<?= urlencode((string) $r['id_reservasi']) ?>"><?= h($paymentActionLabel) ?></a>
                                                    <?php endif; ?>
                                                    <?php if (($isPelanggan && $canDeleteOwnReservation) || (!$isPelanggan && $canDeleteAnyReservation)): ?>
                                                        <form method="POST" class="form-reset" onsubmit="<?= $isPelanggan && !$canDeleteReservation ? 'return false;' : "return confirm('Hapus reservasi ini?')" ?>">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="id_reservasi" value="<?= h($r['id_reservasi']) ?>">
                                                            <button class="btn btn-del status-action-btn<?= $isPelanggan && !$canDeleteReservation ? ' is-disabled' : '' ?>" type="submit" <?= $isPelanggan && !$canDeleteReservation ? 'disabled title="Reservasi yang sudah memiliki pembayaran tidak bisa dihapus."' : '' ?>>Hapus</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php if ($isPelanggan): ?>
        <script>
    const currentUserId = <?= json_encode((string) ($user['user_id'] ?? '')) ?>;
    const seatCourts = <?= json_encode(array_values(array_filter($lapangan, static function ($l) {
        return ($l['status'] ?? '') === 'tersedia';
    })), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const seatBookings = <?= json_encode($reservasiBooked, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const hourLabels = Array.from({ length: 17 }, (_, idx) => String(idx + 6).padStart(2, '0') + ':00');
    const courtFilterBar = document.getElementById('courtFilterBar');

    const courtPicker = document.getElementById('courtPicker');
    const activeCourtBar = document.getElementById('activeCourtBar');
    const seatTimeHeader = document.getElementById('seatTimeHeader');
    const seatGrid = document.getElementById('seatGrid');
    const bookingDateInput = document.getElementById('bookingDateInput');
    const hiddenCourtInput = document.getElementById('hiddenCourtInput');
    const hiddenStartTime = document.getElementById('hiddenStartTime');
    const hiddenEndTime = document.getElementById('hiddenEndTime');
    const seatSummaryEmpty = document.getElementById('seatSummaryEmpty');
    const seatSummaryHint = document.getElementById('seatSummaryHint');
    const seatSummaryList = document.getElementById('seatSummaryList');
    const summaryCourt = document.getElementById('summaryCourt');
    const summaryDate = document.getElementById('summaryDate');
    const summaryTime = document.getElementById('summaryTime');
    const summaryDuration = document.getElementById('summaryDuration');
    const summaryTotal = document.getElementById('summaryTotal');
    const durationPills = Array.from(document.querySelectorAll('.duration-pill'));

    let selectedDuration = 1;
    let selectedSeat = null;
    let activeCourtId = <?= json_encode($selectedCourtId !== '' ? $selectedCourtId : (($lapangan[0]['id_lapangan'] ?? '') ?: '')) ?>;
    let activeCourtFilter = 'Semua';
    const courtFilters = ['Semua', ...new Set(seatCourts.map((court) => String(court.jenis_lantai || 'Lainnya')))];

    function minutesFromTime(value) {
        const parts = String(value || '').split(':');
        const hour = parseInt(parts[0] || '0', 10);
        const minute = parseInt(parts[1] || '0', 10);
        return (hour * 60) + minute;
    }

    function formatTime(minutes) {
        const hour = Math.floor(minutes / 60);
        const minute = minutes % 60;
        return String(hour).padStart(2, '0') + ':' + String(minute).padStart(2, '0');
    }

    function overlaps(startA, endA, startB, endB) {
        return startA < endB && endA > startB;
    }

    function getSlotBookingState(courtId, dateValue, startMinutes, endMinutes) {
        const bookings = seatBookings.filter((item) => {
            if (String(item.id_lapangan) !== String(courtId)) return false;
            if (String(item.tanggal_booking) !== String(dateValue)) return false;
            return overlaps(startMinutes, endMinutes, minutesFromTime(item.jam_mulai), minutesFromTime(item.jam_selesai));
        });
        if (bookings.length === 0) {
            return null;
        }

        const startOverlapsBooking = bookings.find((item) => overlaps(
            startMinutes,
            startMinutes + 1,
            minutesFromTime(item.jam_mulai),
            minutesFromTime(item.jam_selesai)
        ));

        if (startOverlapsBooking) {
            return String(startOverlapsBooking.id_pengguna) === String(currentUserId) ? 'own-booked' : 'booked';
        }

        return 'duration-mismatch';
    }

    function fillHeader() {
        if (!seatTimeHeader) return;
        seatTimeHeader.innerHTML = '';
        hourLabels.forEach((label) => {
            const node = document.createElement('div');
            node.textContent = label;
            seatTimeHeader.appendChild(node);
        });
    }

    function getFilteredCourts() {
        return seatCourts.filter((court) => activeCourtFilter === 'Semua' || String(court.jenis_lantai || 'Lainnya') === activeCourtFilter);
    }

    function renderCourtFilters() {
        if (!courtFilterBar) return;
        courtFilterBar.innerHTML = '';
        courtFilters.forEach((filterName) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'court-filter-btn' + (filterName === activeCourtFilter ? ' active' : '');
            button.textContent = filterName;
            button.addEventListener('click', () => {
                activeCourtFilter = filterName;
                const visibleCourts = getFilteredCourts();
                activeCourtId = visibleCourts[0] ? String(visibleCourts[0].id_lapangan) : '';
                renderCourtFilters();
                renderCourtPicker();
                renderSeats();
            });
            courtFilterBar.appendChild(button);
        });
    }

    function renderCourtPicker() {
        if (!courtPicker) return;
        courtPicker.innerHTML = '';
        getFilteredCourts().forEach((court) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'court-chip' + (String(court.id_lapangan) === String(activeCourtId) ? ' active' : '');
            button.innerHTML = '<strong>' + court.nama_lapangan + '</strong><span>' + court.id_lapangan + ' • ' + String(court.jenis_lantai || 'Lainnya') + '</span><span>Rp' + Number(court.harga_per_jam).toLocaleString('id-ID') + '/jam • ' + (court.jam_buka || '06:00').slice(0, 5) + ' - ' + (court.jam_tutup || '23:00').slice(0, 5) + '</span>';
            button.addEventListener('click', () => {
                activeCourtId = String(court.id_lapangan);
                renderCourtPicker();
                renderSeats();
            });
            courtPicker.appendChild(button);
        });
    }

    function updateHint(minAvailablePrice) {
        if (!seatSummaryHint) return;
        if (selectedSeat) {
            seatSummaryHint.textContent = 'Harga dihitung real-time dari lapangan yang kamu pilih untuk durasi ' + selectedDuration + ' jam.';
            return;
        }
        if (!bookingDateInput.value) {
            seatSummaryHint.textContent = 'Pilih tanggal dan durasi untuk melihat estimasi harga real-time.';
            return;
        }
        if (minAvailablePrice === null) {
            seatSummaryHint.textContent = 'Belum ada slot yang cocok pada tanggal dan durasi ini.';
            return;
        }
        seatSummaryHint.textContent = 'Estimasi mulai dari Rp' + Number(minAvailablePrice * selectedDuration).toLocaleString('id-ID') + ' untuk ' + selectedDuration + ' jam pada tanggal ini.';
    }

    function updateSummary(minAvailablePrice = null) {
        if (!selectedSeat) {
            seatSummaryEmpty.classList.remove('visually-hidden');
            seatSummaryList.classList.add('visually-hidden');
            updateHint(minAvailablePrice);
            return;
        }
        seatSummaryEmpty.classList.add('visually-hidden');
        seatSummaryList.classList.remove('visually-hidden');
        summaryCourt.textContent = selectedSeat.courtName;
        summaryDate.textContent = bookingDateInput.value || '-';
        summaryTime.textContent = selectedSeat.startLabel + ' - ' + selectedSeat.endLabel;
        summaryDuration.textContent = selectedDuration + ' jam';
        summaryTotal.textContent = 'Rp' + Number(selectedSeat.price * selectedDuration).toLocaleString('id-ID');
        updateHint(minAvailablePrice);
    }

    function syncHiddenFields(minAvailablePrice = null) {
        hiddenCourtInput.value = selectedSeat ? selectedSeat.courtId : '';
        hiddenStartTime.value = selectedSeat ? selectedSeat.startLabel : '';
        hiddenEndTime.value = selectedSeat ? selectedSeat.endLabel : '';
        updateSummary(minAvailablePrice);
    }

    function renderSeats() {
        if (!seatGrid) return;
        const dateValue = bookingDateInput.value;
        seatGrid.innerHTML = '';
        selectedSeat = null;
        let minAvailablePrice = null;
        syncHiddenFields(minAvailablePrice);

        const filteredCourts = getFilteredCourts();
        const court = filteredCourts.find((item) => String(item.id_lapangan) === String(activeCourtId)) || filteredCourts[0];
        if (!court) {
            if (activeCourtBar) {
                activeCourtBar.innerHTML = '<div><strong>Tidak ada lapangan</strong><span>Ganti filter untuk melihat slot yang tersedia.</span></div>';
            }
            updateSummary(minAvailablePrice);
            return;
        }

        activeCourtId = String(court.id_lapangan);
        if (activeCourtBar) {
            activeCourtBar.innerHTML = '<div><strong>' + court.nama_lapangan + '</strong><span>' + court.id_lapangan + ' • ' + String(court.jenis_lantai || 'Lainnya') + ' • Rp' + Number(court.harga_per_jam).toLocaleString('id-ID') + '/jam</span></div><span>Jam buka ' + (court.jam_buka || '06:00').slice(0, 5) + ' - ' + (court.jam_tutup || '23:00').slice(0, 5) + '</span>';
        }

        const row = document.createElement('div');
        row.className = 'seat-row';

        const openMinutes = minutesFromTime(court.jam_buka || '06:00');
        const closeMinutes = minutesFromTime(court.jam_tutup || '23:00');

        hourLabels.forEach((labelTime) => {
            const startMinutes = minutesFromTime(labelTime);
            const endMinutes = startMinutes + (selectedDuration * 60);
            const seat = document.createElement('button');
            seat.type = 'button';
            seat.className = 'seat';

            const withinCourtHours = startMinutes >= openMinutes && endMinutes <= closeMinutes;
            const bookingState = dateValue ? getSlotBookingState(court.id_lapangan, dateValue, startMinutes, endMinutes) : null;

            if (!dateValue || !withinCourtHours) {
                seat.classList.add('blocked');
                seat.disabled = true;
                seat.innerHTML = '<span>' + labelTime.slice(0, 2) + '</span><small>Tutup</small>';
            } else if (bookingState === 'booked' || bookingState === 'own-booked') {
                seat.classList.add(bookingState);
                seat.disabled = true;
                seat.innerHTML = '<span>' + labelTime.slice(0, 2) + '</span><small>' + (bookingState === 'own-booked' ? 'Punyamu' : 'Penuh') + '</small>';
            } else if (bookingState === 'duration-mismatch') {
                seat.classList.add('blocked');
                seat.disabled = true;
                seat.innerHTML = '<span>' + labelTime.slice(0, 2) + '</span><small>Tidak cocok</small>';
            } else {
                seat.classList.add('available');
                seat.innerHTML = '<span>' + labelTime.slice(0, 2) + '</span><small>Kosong</small>';
                if (minAvailablePrice === null || Number(court.harga_per_jam) < minAvailablePrice) {
                    minAvailablePrice = Number(court.harga_per_jam);
                }
                seat.addEventListener('click', () => {
                    document.querySelectorAll('.seat.selected').forEach((node) => node.classList.remove('selected'));
                    seat.classList.add('selected');
                    selectedSeat = {
                        courtId: String(court.id_lapangan),
                        courtName: court.nama_lapangan,
                        startLabel: formatTime(startMinutes),
                        endLabel: formatTime(endMinutes),
                        price: Number(court.harga_per_jam)
                    };
                    syncHiddenFields(minAvailablePrice);
                });
            }

            row.appendChild(seat);
        });

        seatGrid.appendChild(row);

        updateSummary(minAvailablePrice);
    }

    durationPills.forEach((pill) => {
        pill.addEventListener('click', () => {
            durationPills.forEach((node) => node.classList.remove('active'));
            pill.classList.add('active');
            selectedDuration = parseInt(pill.dataset.hours || '1', 10) || 1;
            renderSeats();
        });
    });

    bookingDateInput.addEventListener('input', renderSeats);

    document.getElementById('cinemaReservationForm')?.addEventListener('submit', (event) => {
        if (!hiddenCourtInput.value || !hiddenStartTime.value || !hiddenEndTime.value || !bookingDateInput.value) {
            event.preventDefault();
            alert('Pilih tanggal dan satu kursi slot terlebih dahulu.');
        }
    });

    fillHeader();
    renderCourtFilters();
    renderCourtPicker();
    renderSeats();

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

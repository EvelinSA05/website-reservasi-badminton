<?php
require_once __DIR__ . '/../core/role_helper.php';

$user = ensure_login_and_role(['pelanggan', 'admin', 'kasir', 'owner']);
$flash = pull_flash();
$isPelanggan = $user['role'] === 'pelanggan';
$isAdmin = $user['role'] === 'admin';

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
        if ($action === 'create' && $isPelanggan) {
            $id = next_prefixed_id('reservasi', 'id_reservasi', 'RV');
            $idLap = trim($_POST['id_lapangan'] ?? '');
            $tanggal = trim($_POST['tanggal_booking'] ?? '');
            $mulai = trim($_POST['jam_mulai'] ?? '');
            $selesai = trim($_POST['jam_selesai'] ?? '');

            [$durasi, $total] = hitung_total_biaya($idLap, $mulai, $selesai);
            if ($durasi <= 0) {
                redirect_with_flash('reservasi.php', 'error', 'Jam selesai harus lebih besar dari jam mulai.');
            }

            $stmt = db()->prepare('INSERT INTO reservasi (id_reservasi, id_pengguna, id_lapangan, tanggal_booking, jam_mulai, jam_selesai, durasi, total_biaya, status_reservasi) VALUES (:id, :id_pengguna, :id_lapangan, :tanggal, :mulai, :selesai, :durasi, :total, :status)');
            $stmt->execute([
                'id' => $id,
                'id_pengguna' => $user['user_id'],
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

        if ($action === 'update' && $isPelanggan) {
            $id = trim($_POST['id_reservasi'] ?? '');
            $idLap = trim($_POST['id_lapangan'] ?? '');
            $tanggal = trim($_POST['tanggal_booking'] ?? '');
            $mulai = trim($_POST['jam_mulai'] ?? '');
            $selesai = trim($_POST['jam_selesai'] ?? '');

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

        if ($action === 'update_status' && $isAdmin) {
            $id = trim($_POST['id_reservasi'] ?? '');
            $status = trim($_POST['status_reservasi'] ?? 'pending');
            $stmt = db()->prepare('UPDATE reservasi SET status_reservasi=:status WHERE id_reservasi=:id');
            $stmt->execute([
                'id' => $id,
                'status' => $status
            ]);
            redirect_with_flash('reservasi.php', 'success', 'Status reservasi berhasil diupdate.');
        }

        if ($action === 'delete') {
            $id = trim($_POST['id_reservasi'] ?? '');
            if ($isPelanggan) {
                $stmt = db()->prepare('DELETE FROM reservasi WHERE id_reservasi=:id AND id_pengguna=:id_pengguna');
                $stmt->execute([
                    'id' => $id,
                    'id_pengguna' => $user['user_id']
                ]);
            } elseif ($isAdmin) {
                $stmt = db()->prepare('DELETE FROM reservasi WHERE id_reservasi=:id');
                $stmt->execute(['id' => $id]);
            }
            redirect_with_flash('reservasi.php', 'success', 'Reservasi berhasil dihapus.');
        }
    } catch (Throwable $e) {
        redirect_with_flash('reservasi.php', 'error', 'Operasi reservasi gagal: ' . $e->getMessage());
    }
}

$lapangan = db()->query('SELECT id_lapangan, nama_lapangan, harga_per_jam, status FROM lapangan ORDER BY id_lapangan')->fetchAll();

if ($isPelanggan) {
    $stmt = db()->prepare('SELECT r.*, l.nama_lapangan FROM reservasi r JOIN lapangan l ON l.id_lapangan=r.id_lapangan WHERE r.id_pengguna=:id ORDER BY r.created_at DESC');
    $stmt->execute(['id' => $user['user_id']]);
    $reservasiRows = $stmt->fetchAll();
} else {
    $reservasiRows = db()->query('SELECT r.*, p.nama_lengkap, l.nama_lapangan FROM reservasi r JOIN pengguna p ON p.id_pengguna=r.id_pengguna JOIN lapangan l ON l.id_lapangan=r.id_lapangan ORDER BY r.created_at DESC')->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaksi Reservasi</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../assets/panel-theme.css">
    <style>
        .toolbar { display: flex; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
        .desc-muted { color: #aab5d3; font-size: 13px; }
        .status-block { display: grid; gap: 8px; max-width: 220px; }
        .amount-line { color: #9eb0d2; font-size: 12px; margin-top: 6px; display: inline-block; }
    </style>
</head>
<body class="panel-page">
    <div class="panel-wrap">
        <div class="panel">
            <div class="panel-title">
                <h2>Transaksi Reservasi</h2>
                <div class="links-inline">
                    <a class="link-muted" href="dashboard.php">Dashboard</a>
                    <a class="link-muted" href="../actions/logout.php">Logout</a>
                </div>
            </div>
            <p class="meta">Login: <strong><?= h($user['name']) ?></strong> (<?= h($user['role']) ?>)</p>
            <?php if ($flash): ?>
                <div class="flash <?= $flash['type'] === 'success' ? 'ok' : 'err' ?>"><?= h($flash['message']) ?></div>
            <?php endif; ?>
        </div>

        <?php if ($isPelanggan): ?>
            <div class="panel">
                <h3 style="margin-top:0;">Buat Reservasi Baru</h3>
                <form method="POST" class="form-reset">
                    <input type="hidden" name="action" value="create">
                    <div class="grid-4">
                        <select name="id_lapangan" required>
                            <option value="">Pilih lapangan</option>
                            <?php foreach ($lapangan as $l): ?>
                                <?php if ($l['status'] !== 'tersedia') continue; ?>
                                <option value="<?= h($l['id_lapangan']) ?>"><?= h($l['id_lapangan'] . ' - ' . $l['nama_lapangan'] . ' (Rp' . number_format((int) $l['harga_per_jam'], 0, ',', '.') . '/jam)') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="date" name="tanggal_booking" required>
                        <input type="time" name="jam_mulai" required>
                        <input type="time" name="jam_selesai" required>
                    </div>
                    <p style="margin-top:10px;"><button class="btn btn-create" type="submit">Buat Reservasi</button></p>
                </form>
            </div>
        <?php endif; ?>

        <div class="panel">
            <h3 style="margin-top:0;">Data Reservasi</h3>
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
                        <?php foreach ($reservasiRows as $r): ?>
                            <tr>
                                <td><strong><?= h($r['id_reservasi']) ?></strong></td>
                                <?php if (!$isPelanggan): ?><td><?= h($r['nama_lengkap']) ?></td><?php endif; ?>
                                <td>
                                    <?php if ($isPelanggan): ?>
                                        <form method="POST" class="form-reset">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="id_reservasi" value="<?= h($r['id_reservasi']) ?>">
                                            <div class="grid-2">
                                                <select name="id_lapangan" required>
                                                    <?php foreach ($lapangan as $l): ?>
                                                        <option value="<?= h($l['id_lapangan']) ?>" <?= $l['id_lapangan'] === $r['id_lapangan'] ? 'selected' : '' ?>>
                                                            <?= h($l['id_lapangan'] . ' - ' . $l['nama_lapangan']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <input type="date" name="tanggal_booking" value="<?= h($r['tanggal_booking']) ?>" required>
                                                <input type="time" name="jam_mulai" value="<?= h(substr((string) $r['jam_mulai'], 0, 5)) ?>" required>
                                                <input type="time" name="jam_selesai" value="<?= h(substr((string) $r['jam_selesai'], 0, 5)) ?>" required>
                                            </div>
                                            <small class="amount-line">Durasi: <?= h($r['durasi']) ?> jam | Total: Rp<?= h(number_format((int) $r['total_biaya'], 0, ',', '.')) ?></small>
                                            <p style="margin-top:8px;"><button class="btn btn-save" type="submit">Simpan</button></p>
                                        </form>
                                    <?php else: ?>
                                        <strong><?= h($r['nama_lapangan']) ?></strong><br>
                                        <span class="desc-muted"><?= h($r['tanggal_booking']) ?> <?= h(substr((string) $r['jam_mulai'], 0, 5)) ?> - <?= h(substr((string) $r['jam_selesai'], 0, 5)) ?></span><br>
                                        <span class="desc-muted">Durasi <?= h($r['durasi']) ?> jam | Total Rp<?= h(number_format((int) $r['total_biaya'], 0, ',', '.')) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="status-block">
                                        <?php if ($isAdmin): ?>
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
                                        <?php else: ?>
                                            <strong><?= h($r['status_reservasi']) ?></strong>
                                        <?php endif; ?>

                                        <?php if ($isPelanggan || $isAdmin): ?>
                                            <form method="POST" class="form-reset" onsubmit="return confirm('Hapus reservasi ini?')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id_reservasi" value="<?= h($r['id_reservasi']) ?>">
                                                <button class="btn btn-del" type="submit">Hapus</button>
                                            </form>
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
</body>
</html>

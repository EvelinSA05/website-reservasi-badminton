<?php
require_once __DIR__ . '/../core/role_helper.php';

$user = ensure_login_and_role(['admin', 'kasir', 'owner']);
$flash = pull_flash();
$canEdit = in_array($user['role'], ['admin', 'kasir'], true);
const BUKTI_UPLOAD_DIR = __DIR__ . '/../uploads/bukti';
const BUKTI_DB_PREFIX = 'uploads/bukti/';

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
    if (!$canEdit) {
        redirect_with_flash('pembayaran.php', 'error', 'Role Anda hanya dapat melihat data pembayaran.');
    }

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $id = next_prefixed_id('pembayaran', 'id_pembayaran', 'PY');
            $idReservasi = trim($_POST['id_reservasi'] ?? '');
            $metode = trim($_POST['metode_pembayaran'] ?? '');
            $jumlah = (float) ($_POST['jumlah_bayar'] ?? 0);
            $tanggal = trim($_POST['tanggal_bayar'] ?? '');
            $status = trim($_POST['status_pembayaran'] ?? 'pending');
            $bukti = handle_bukti_upload('bukti_pembayaran_file', '-');
            $idAdmin = $user['user_id'];

            $stmt = db()->prepare('INSERT INTO pembayaran (id_pembayaran, id_reservasi, metode_pembayaran, jumlah_bayar, tanggal_bayar, status_pembayaran, bukti_pembayaran, id_admin_verifikasi) VALUES (:id, :id_res, :metode, :jumlah, :tanggal, :status, :bukti, :admin)');
            $stmt->execute([
                'id' => $id,
                'id_res' => $idReservasi,
                'metode' => $metode,
                'jumlah' => $jumlah,
                'tanggal' => str_replace('T', ' ', $tanggal) . ':00',
                'status' => $status,
                'bukti' => $bukti,
                'admin' => $idAdmin
            ]);
            redirect_with_flash('pembayaran.php', 'success', 'Pembayaran berhasil ditambahkan.');
        }

        if ($action === 'update') {
            $id = trim($_POST['id_pembayaran'] ?? '');
            $metode = trim($_POST['metode_pembayaran'] ?? '');
            $jumlah = (float) ($_POST['jumlah_bayar'] ?? 0);
            $tanggal = trim($_POST['tanggal_bayar'] ?? '');
            $status = trim($_POST['status_pembayaran'] ?? 'pending');
            $oldBukti = trim($_POST['old_bukti_pembayaran'] ?? '-');
            $bukti = handle_bukti_upload('bukti_pembayaran_file', $oldBukti === '' ? '-' : $oldBukti);
            if ($bukti !== $oldBukti) {
                delete_bukti_file($oldBukti);
            }

            $stmt = db()->prepare('UPDATE pembayaran SET metode_pembayaran=:metode, jumlah_bayar=:jumlah, tanggal_bayar=:tanggal, status_pembayaran=:status, bukti_pembayaran=:bukti, id_admin_verifikasi=:admin WHERE id_pembayaran=:id');
            $stmt->execute([
                'id' => $id,
                'metode' => $metode,
                'jumlah' => $jumlah,
                'tanggal' => str_replace('T', ' ', $tanggal) . ':00',
                'status' => $status,
                'bukti' => $bukti,
                'admin' => $user['user_id']
            ]);
            redirect_with_flash('pembayaran.php', 'success', 'Pembayaran berhasil diupdate.');
        }

        if ($action === 'delete') {
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

$reservasiList = db()->query('SELECT r.id_reservasi, p.nama_lengkap, r.total_biaya FROM reservasi r JOIN pengguna p ON p.id_pengguna=r.id_pengguna ORDER BY r.id_reservasi DESC')->fetchAll();
$payRows = db()->query('SELECT py.*, r.id_pengguna, u.nama_lengkap FROM pembayaran py JOIN reservasi r ON r.id_reservasi=py.id_reservasi JOIN pengguna u ON u.id_pengguna=r.id_pengguna ORDER BY py.tanggal_bayar DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaksi Pembayaran</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../assets/panel-theme.css">
    <style>
        .pay-note { color:#aab5d3; font-size: 12px; margin-top: 4px; display: block; }
        .cell-actions { display:grid; gap:8px; max-width:220px; }
    </style>
</head>
<body class="panel-page">
    <div class="panel-wrap">
        <div class="panel">
            <div class="panel-title">
                <h2>Transaksi Pembayaran</h2>
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

        <?php if ($canEdit): ?>
            <div class="panel">
                <h3 style="margin-top:0;">Tambah Pembayaran</h3>
                <form method="POST" class="form-reset" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="create">
                    <div class="grid">
                        <select name="id_reservasi" required>
                            <option value="">Pilih reservasi</option>
                            <?php foreach ($reservasiList as $r): ?>
                                <option value="<?= h($r['id_reservasi']) ?>"><?= h($r['id_reservasi'] . ' - ' . $r['nama_lengkap'] . ' (Rp' . number_format((int) $r['total_biaya'], 0, ',', '.') . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="metode_pembayaran" required>
                            <option value="QRIS">QRIS</option>
                            <option value="Transfer">Transfer</option>
                            <option value="Cash">Cash</option>
                        </select>
                        <input type="number" name="jumlah_bayar" min="0" step="0.01" placeholder="Jumlah bayar" required>
                        <input type="datetime-local" name="tanggal_bayar" required>
                        <select name="status_pembayaran" required>
                            <option value="pending">pending</option>
                            <option value="DP">DP</option>
                            <option value="lunas">lunas</option>
                        </select>
                        <input type="file" name="bukti_pembayaran_file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                    </div>
                    <p style="margin-top:10px;"><button class="btn btn-create" type="submit">Tambah Pembayaran</button></p>
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
                                                    <option value="pending" <?= $py['status_pembayaran'] === 'pending' ? 'selected' : '' ?>>pending</option>
                                                    <option value="DP" <?= $py['status_pembayaran'] === 'DP' ? 'selected' : '' ?>>DP</option>
                                                    <option value="lunas" <?= $py['status_pembayaran'] === 'lunas' ? 'selected' : '' ?>>lunas</option>
                                                </select>
                                                <input type="file" name="bukti_pembayaran_file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                                            </div>
                                            <small class="pay-note">Bukti saat ini:
                                                <?php if (!empty($py['bukti_pembayaran']) && $py['bukti_pembayaran'] !== '-'): ?>
                                                    <a class="link-muted" href="../<?= h($py['bukti_pembayaran']) ?>" target="_blank" rel="noopener noreferrer"><?= h(basename((string) $py['bukti_pembayaran'])) ?></a>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </small>
                                            <p style="margin-top:8px;"><button class="btn btn-save" type="submit">Simpan</button></p>
                                        </form>
                                    <?php else: ?>
                                        Metode: <?= h($py['metode_pembayaran']) ?><br>
                                        Jumlah: Rp<?= h(number_format((int) $py['jumlah_bayar'], 0, ',', '.')) ?><br>
                                        Tanggal: <?= h($py['tanggal_bayar']) ?><br>
                                        Status: <?= h($py['status_pembayaran']) ?><br>
                                        Bukti:
                                        <?php if (!empty($py['bukti_pembayaran']) && $py['bukti_pembayaran'] !== '-'): ?>
                                            <a class="link-muted" href="../<?= h($py['bukti_pembayaran']) ?>" target="_blank" rel="noopener noreferrer"><?= h(basename((string) $py['bukti_pembayaran'])) ?></a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($canEdit): ?>
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
</body>
</html>

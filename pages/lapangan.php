<?php
require_once __DIR__ . '/../core/role_helper.php';

$user = ensure_login_and_role(['admin']);
$flash = pull_flash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $id = next_prefixed_id('lapangan', 'id_lapangan', 'LP');
            $nama = trim($_POST['nama_lapangan'] ?? '');
            $jenis = trim($_POST['jenis_lantai'] ?? '');
            $harga = (int) ($_POST['harga_per_jam'] ?? 0);
            $buka = trim($_POST['jam_buka'] ?? '');
            $tutup = trim($_POST['jam_tutup'] ?? '');
            $status = trim($_POST['status'] ?? 'tersedia');

            if ($nama === '' || $jenis === '' || $harga <= 0 || $buka === '' || $tutup === '') {
                redirect_with_flash('lapangan.php', 'error', 'Data lapangan belum lengkap.');
            }

            $stmt = db()->prepare('INSERT INTO lapangan (id_lapangan, nama_lapangan, jenis_lantai, harga_per_jam, jam_buka, jam_tutup, status) VALUES (:id, :nama, :jenis, :harga, :buka, :tutup, :status)');
            $stmt->execute([
                'id' => $id,
                'nama' => $nama,
                'jenis' => $jenis,
                'harga' => $harga,
                'buka' => $buka,
                'tutup' => $tutup,
                'status' => $status
            ]);
            redirect_with_flash('lapangan.php', 'success', 'Lapangan berhasil ditambahkan.');
        }

        if ($action === 'update') {
            $id = trim($_POST['id_lapangan'] ?? '');
            $nama = trim($_POST['nama_lapangan'] ?? '');
            $jenis = trim($_POST['jenis_lantai'] ?? '');
            $harga = (int) ($_POST['harga_per_jam'] ?? 0);
            $buka = trim($_POST['jam_buka'] ?? '');
            $tutup = trim($_POST['jam_tutup'] ?? '');
            $status = trim($_POST['status'] ?? 'tersedia');

            $stmt = db()->prepare('UPDATE lapangan SET nama_lapangan=:nama, jenis_lantai=:jenis, harga_per_jam=:harga, jam_buka=:buka, jam_tutup=:tutup, status=:status WHERE id_lapangan=:id');
            $stmt->execute([
                'id' => $id,
                'nama' => $nama,
                'jenis' => $jenis,
                'harga' => $harga,
                'buka' => $buka,
                'tutup' => $tutup,
                'status' => $status
            ]);
            redirect_with_flash('lapangan.php', 'success', 'Lapangan berhasil diupdate.');
        }

        if ($action === 'delete') {
            $id = trim($_POST['id_lapangan'] ?? '');
            $stmt = db()->prepare('DELETE FROM lapangan WHERE id_lapangan=:id');
            $stmt->execute(['id' => $id]);
            redirect_with_flash('lapangan.php', 'success', 'Lapangan berhasil dihapus.');
        }
    } catch (Throwable $e) {
        redirect_with_flash('lapangan.php', 'error', 'Operasi gagal: ' . $e->getMessage());
    }
}

$rows = db()->query('SELECT * FROM lapangan ORDER BY id_lapangan ASC')->fetchAll();
$totalLapangan = count($rows);
$totalTersedia = 0;
$totalPerbaikan = 0;
$rataHarga = 0;
foreach ($rows as $r) {
    if (($r['status'] ?? '') === 'tersedia') {
        $totalTersedia++;
    } else {
        $totalPerbaikan++;
    }
    $rataHarga += (int) ($r['harga_per_jam'] ?? 0);
}
if ($totalLapangan > 0) {
    $rataHarga = (int) round($rataHarga / $totalLapangan);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Lapangan</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../assets/panel-theme.css">
    <style>
        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-top: 12px;
        }
        .stat {
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, .12);
            background: rgba(255, 255, 255, .04);
            padding: 12px;
        }
        .stat-label { font-size: 12px; color: #96a0bc; margin-bottom: 5px; }
        .stat-value { font-size: 20px; font-weight: 700; color: #f0f5ff; }
        .toolbar {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 10px;
            margin-bottom: 12px;
        }
        .action-cell { width: 170px; }
        .action-stack {
            display: flex;
            flex-direction: column;
            gap: 8px;
            align-items: flex-start;
        }
        .action-stack .btn { width: 104px; }
        .row-form { display: none; margin-top: 10px; }
        .row-form.open { display: table-row; animation: fadeIn .24s ease; }
        .empty-state { padding: 16px; color: #aab5d3; text-align: center; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-4px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 980px) {
            .stats { grid-template-columns: repeat(2, 1fr); }
            .toolbar { grid-template-columns: 1fr; }
        }

        @media (max-width: 620px) {
            .action-stack,
            .action-stack .btn { width: 100%; }
            .action-cell { width: 160px; }
        }
    </style>
</head>
<body class="panel-page">
    <div class="panel-wrap">
        <div class="panel">
            <div class="panel-title">
                <div>
                    <h2 style="font-size: 28px;">Master Lapangan</h2>
                    <p class="panel-subtitle">Kelola data lapangan, status operasional, dan harga sewa per jam.</p>
                </div>
                <div class="links-inline">
                    <a class="link-muted" href="dashboard.php">Dashboard</a>
                    <a class="link-muted" href="../actions/logout.php">Logout</a>
                </div>
            </div>
            <p class="meta">Login: <strong><?= h($user['name']) ?></strong> (<?= h($user['role']) ?>)</p>
            <div class="stats">
                <div class="stat">
                    <div class="stat-label">Total Lapangan</div>
                    <div class="stat-value"><?= h($totalLapangan) ?></div>
                </div>
                <div class="stat">
                    <div class="stat-label">Tersedia</div>
                    <div class="stat-value" id="stat-available"><?= h($totalTersedia) ?></div>
                </div>
                <div class="stat">
                    <div class="stat-label">Perbaikan</div>
                    <div class="stat-value" id="stat-maintenance"><?= h($totalPerbaikan) ?></div>
                </div>
                <div class="stat">
                    <div class="stat-label">Rata-rata Harga/Jam</div>
                    <div class="stat-value">Rp<?= h(number_format($rataHarga, 0, ',', '.')) ?></div>
                </div>
            </div>
            <?php if ($flash): ?>
                <div class="flash <?= $flash['type'] === 'success' ? 'ok' : 'err' ?>"><?= h($flash['message']) ?></div>
            <?php endif; ?>
        </div>

        <div class="panel">
            <h3 style="margin-top: 0;">Tambah Lapangan Baru</h3>
            <form method="POST" class="form-reset">
                <input type="hidden" name="action" value="create">
                <div class="grid">
                    <input class="input" name="nama_lapangan" placeholder="Nama lapangan" required>
                    <input class="input" name="jenis_lantai" placeholder="Jenis lantai" required>
                    <input class="input" type="number" name="harga_per_jam" placeholder="Harga per jam" min="1000" required>
                    <input class="input" type="time" name="jam_buka" required>
                    <input class="input" type="time" name="jam_tutup" required>
                    <select class="select" name="status" required>
                        <option value="tersedia">Tersedia</option>
                        <option value="perbaikan">Perbaikan</option>
                    </select>
                </div>
                <p style="margin-top: 10px;"><button class="btn btn-create" type="submit">Tambah</button></p>
            </form>
        </div>

        <div class="panel">
            <h3 style="margin-top: 0;">Data Lapangan</h3>
            <div class="toolbar">
                <input id="searchInput" class="input" placeholder="Cari ID, nama lapangan, atau jenis lantai...">
                <select id="statusFilter" class="select">
                    <option value="all">Semua Status</option>
                    <option value="tersedia">Tersedia</option>
                    <option value="perbaikan">Perbaikan</option>
                </select>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nama & Detail</th>
                            <th>Operasional</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="lapanganBody">
                        <?php foreach ($rows as $row): ?>
                            <tr class="lap-row"
                                data-id="<?= h(strtolower((string) $row['id_lapangan'])) ?>"
                                data-name="<?= h(strtolower((string) $row['nama_lapangan'])) ?>"
                                data-jenis="<?= h(strtolower((string) $row['jenis_lantai'])) ?>"
                                data-status="<?= h(strtolower((string) $row['status'])) ?>">
                                <td><strong><?= h($row['id_lapangan']) ?></strong></td>
                                <td>
                                    <div style="font-weight: 700; color: #f2f7ff;"><?= h($row['nama_lapangan']) ?></div>
                                    <small style="color: #9eb0d2;">Jenis lantai: <?= h($row['jenis_lantai']) ?></small><br>
                                    <small style="color: #9eb0d2;">Rp<?= h(number_format((int) $row['harga_per_jam'], 0, ',', '.')) ?>/jam</small>
                                </td>
                                <td><?= h(substr((string) $row['jam_buka'], 0, 5)) ?> - <?= h(substr((string) $row['jam_tutup'], 0, 5)) ?></td>
                                <td>
                                    <?php if ($row['status'] === 'tersedia'): ?>
                                        <span class="badge badge-on">Tersedia</span>
                                    <?php else: ?>
                                        <span class="badge badge-off">Perbaikan</span>
                                    <?php endif; ?>
                                </td>
                                <td class="action-cell">
                                    <div class="action-stack">
                                        <button type="button" class="btn btn-ghost edit-toggle" data-target="form-<?= h($row['id_lapangan']) ?>">Edit</button>
                                        <form method="POST" class="form-reset" onsubmit="return confirm('Hapus lapangan ini?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id_lapangan" value="<?= h($row['id_lapangan']) ?>">
                                            <button class="btn btn-del" type="submit">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <tr class="row-form" id="form-<?= h($row['id_lapangan']) ?>">
                                <td colspan="5">
                                    <form method="POST" class="form-reset">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="id_lapangan" value="<?= h($row['id_lapangan']) ?>">
                                        <div class="grid-2">
                                            <input class="input" name="nama_lapangan" value="<?= h($row['nama_lapangan']) ?>" required>
                                            <input class="input" name="jenis_lantai" value="<?= h($row['jenis_lantai']) ?>" required>
                                            <input class="input" type="number" name="harga_per_jam" value="<?= h($row['harga_per_jam']) ?>" required>
                                            <select class="select" name="status" required>
                                                <option value="tersedia" <?= $row['status'] === 'tersedia' ? 'selected' : '' ?>>Tersedia</option>
                                                <option value="perbaikan" <?= $row['status'] === 'perbaikan' ? 'selected' : '' ?>>Perbaikan</option>
                                            </select>
                                            <input class="input" type="time" name="jam_buka" value="<?= h(substr((string) $row['jam_buka'], 0, 5)) ?>" required>
                                            <input class="input" type="time" name="jam_tutup" value="<?= h(substr((string) $row['jam_tutup'], 0, 5)) ?>" required>
                                        </div>
                                        <p style="margin-top: 10px;"><button class="btn btn-save" type="submit">Simpan Perubahan</button></p>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div id="emptyState" class="empty-state" style="display: none;">Tidak ada data yang cocok dengan filter.</div>
            </div>
        </div>
    </div>

    <script>
        const searchInput = document.getElementById('searchInput');
        const statusFilter = document.getElementById('statusFilter');
        const rows = Array.from(document.querySelectorAll('.lap-row'));
        const emptyState = document.getElementById('emptyState');
        const statAvailable = document.getElementById('stat-available');
        const statMaintenance = document.getElementById('stat-maintenance');

        function applyFilter() {
            const q = (searchInput.value || '').toLowerCase().trim();
            const status = statusFilter.value;

            let visibleCount = 0;
            let availableCount = 0;
            let maintenanceCount = 0;

            rows.forEach((row) => {
                const rowStatus = row.dataset.status;
                const text = `${row.dataset.id} ${row.dataset.name} ${row.dataset.jenis}`;
                const matchesText = q === '' || text.includes(q);
                const matchesStatus = status === 'all' || rowStatus === status;
                const visible = matchesText && matchesStatus;

                row.style.display = visible ? '' : 'none';

                const formId = row.querySelector('.edit-toggle').dataset.target;
                const formRow = document.getElementById(formId);
                if (formRow && !visible) {
                    formRow.style.display = 'none';
                    formRow.classList.remove('open');
                }

                if (visible) {
                    visibleCount++;
                    if (rowStatus === 'tersedia') {
                        availableCount++;
                    } else {
                        maintenanceCount++;
                    }
                }
            });

            emptyState.style.display = visibleCount === 0 ? 'block' : 'none';
            statAvailable.textContent = availableCount;
            statMaintenance.textContent = maintenanceCount;
        }

        document.querySelectorAll('.edit-toggle').forEach((btn) => {
            btn.addEventListener('click', () => {
                const target = document.getElementById(btn.dataset.target);
                if (!target) return;
                const isOpen = target.classList.contains('open');
                target.classList.toggle('open', !isOpen);
                target.style.display = isOpen ? 'none' : 'table-row';
                btn.textContent = isOpen ? 'Edit' : 'Tutup';
            });
        });

        searchInput.addEventListener('input', applyFilter);
        statusFilter.addEventListener('change', applyFilter);
        applyFilter();
    </script>
</body>
</html>

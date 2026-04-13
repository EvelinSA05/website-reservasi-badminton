<?php
session_start();
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/cookies.php';
$company = require __DIR__ . '/../data/company.php';

try {
    try_login_from_remember_cookie();
} catch (Throwable $e) {
    // Halaman promo tetap bisa dibuka tanpa login.
}

$user = current_user();
$isPelanggan = $user && (($user['role'] ?? '') === 'pelanggan');
$promos = require __DIR__ . '/../data/promos.php';
$slug = trim((string) ($_GET['slug'] ?? ''));

$promo = null;
foreach ($promos as $item) {
    if (($item['slug'] ?? '') === $slug) {
        $promo = $item;
        break;
    }
}

if (!$promo) {
    http_response_code(404);
}

$relatedPromos = array_values(array_filter($promos, static function (array $item) use ($slug): bool {
    return ($item['slug'] ?? '') !== $slug;
}));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(($promo['title'] ?? 'Promo tidak ditemukan') . ' | ' . $company['short_name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #110022;
            --bg-dark-2: #330044;
            --text: #e2e8f0;
            --muted: #94a3b8;
            --line: rgba(255,255,255,.12);
            --orange: #fb923c;
            --lime: #a3e635;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at 18% 22%, rgba(34,211,238,.16), transparent 24%),
                radial-gradient(circle at 82% 18%, rgba(255,0,200,.15), transparent 22%),
                radial-gradient(circle at 50% 78%, rgba(34,211,238,.08), transparent 30%),
                linear-gradient(135deg, var(--bg-dark), var(--bg-dark-2));
        }
        .container { width: min(1180px, calc(100% - 28px)); margin: 0 auto; }
        .top { padding: 18px 0; position: sticky; top: 0; z-index: 30; backdrop-filter: blur(8px); }
        .nav {
            border: 1px solid var(--line);
            background: rgba(255,255,255,.05);
            border-radius: 999px;
            padding: 10px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        .brand { display:flex; align-items:center; gap:10px; text-decoration:none; color:#fff; }
        .brand-icon {
            width: 36px; height: 36px; border-radius: 999px;
            display: inline-flex; align-items:center; justify-content:center;
            background: var(--lime); color: #0f172a; font-weight:700;
        }
        .brand-title { font-size: 18px; font-weight: 600; font-family: 'Space Grotesk', sans-serif; }
        .nav-actions { display:flex; gap:8px; flex-wrap:wrap; }
        .btn, .btn-outline {
            display:inline-flex; align-items:center; justify-content:center;
            border-radius:999px; padding:10px 16px; text-decoration:none;
            font-size: 13px; font-weight: 600; border:1px solid transparent;
        }
        .btn { background:#fff; color:#0f172a; }
        .btn-outline { border-color: var(--line); background: rgba(255,255,255,.06); color:#fff; }
        .shell { padding: 28px 0 60px; }
        .layout { display:grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 18px; align-items:start; }
        .main-card, .side-card, .empty-state {
            border:1px solid var(--line);
            background: rgba(255,255,255,.05);
            border-radius: 28px;
        }
        .main-card { padding: 24px; }
        .pill { display:inline-flex; align-items:center; padding:7px 12px; border-radius:999px; background: var(--orange); color:#140019; font-size:12px; font-weight:700; }
        h1 { margin: 16px 0 0; font-size: clamp(34px, 5vw, 58px); line-height: 1.05; font-family: 'Space Grotesk', sans-serif; letter-spacing: -.03em; }
        .lede { margin: 14px 0 0; color:#d9dbe8; font-size:18px; line-height:1.8; max-width:820px; }
        .promo-meta { margin-top: 18px; display:flex; gap:10px; flex-wrap:wrap; }
        .promo-meta span { padding: 8px 12px; border-radius:999px; border:1px solid var(--line); color:#d4d4e3; font-size:13px; }
        .promo-code-box {
            margin-top: 20px;
            padding: 18px;
            border-radius: 22px;
            border:1px dashed rgba(255,255,255,.24);
            background: rgba(20, 8, 30, .46);
        }
        .promo-code-box strong { display:block; font-size:12px; color:#fdba74; text-transform:uppercase; letter-spacing:.16em; }
        .promo-code-box code { display:block; margin-top:10px; font-size:32px; color:#fff; font-family: 'Space Grotesk', sans-serif; }
        .cta-row { margin-top: 18px; display:flex; gap:10px; flex-wrap:wrap; }
        .section-grid { margin-top: 22px; display:grid; gap: 16px; }
        .info-card {
            border:1px solid var(--line);
            border-radius: 24px;
            background: rgba(10, 5, 28, .46);
            padding: 18px;
        }
        .info-card h2 { margin:0 0 10px; font-size: 24px; font-family: 'Space Grotesk', sans-serif; }
        .info-card ul { margin:0; padding-left: 20px; color:#d4d4e3; line-height:1.8; }
        .side-card { padding: 18px; }
        .side-card h3 { margin:0 0 12px; font-size: 22px; font-family: 'Space Grotesk', sans-serif; }
        .side-link { display:block; padding:14px 0; border-top:1px solid var(--line); text-decoration:none; color:inherit; }
        .side-link:first-of-type { border-top:0; padding-top:0; }
        .side-link strong { display:block; font-size: 18px; line-height:1.35; }
        .side-link span { display:block; margin-top:6px; color:#fdba74; font-size:13px; }
        .note { color:#cbd5e1; line-height:1.7; }
        .empty-state { padding: 30px; text-align:center; }
        @media (max-width: 980px) {
            .layout { grid-template-columns: 1fr; }
        }
        @media (max-width: 720px) {
            .container { width: min(100% - 20px, 1180px); }
            .main-card { padding: 18px; }
            h1 { font-size: 36px; }
            .promo-code-box code { font-size: 26px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="top">
            <header class="nav">
                <a href="/index.php" class="brand">
                    <span class="brand-icon" aria-hidden="true">&#127992;</span>
                    <span class="brand-title"><?= htmlspecialchars($company['short_name']) ?></span>
                </a>
                <div class="nav-actions">
                    <a href="/index.php#promo" class="btn-outline">Kembali ke Promo</a>
                    <?php if (!$user): ?>
                        <a href="/login.php" class="btn">Login</a>
                    <?php elseif ($isPelanggan): ?>
                        <a href="/pages/reservasi.php?promo=<?= urlencode($promo['code'] ?? '') ?>" class="btn">Pakai Promo</a>
                    <?php else: ?>
                        <a href="/pages/dashboard.php" class="btn">Dashboard</a>
                    <?php endif; ?>
                </div>
            </header>
        </div>

        <section class="shell">
            <?php if (!$promo): ?>
                <div class="empty-state">
                    <h1>Promo tidak ditemukan</h1>
                    <p class="note">Promo yang diminta belum tersedia atau sudah tidak aktif.</p>
                    <p><a href="/index.php#promo" class="btn" style="margin-top:10px;">Lihat promo aktif</a></p>
                </div>
            <?php else: ?>
                <div class="layout">
                    <article class="main-card">
                        <span class="pill"><?= htmlspecialchars($promo['badge']) ?></span>
                        <h1><?= htmlspecialchars($promo['title']) ?></h1>
                        <p class="lede"><?= htmlspecialchars($promo['summary']) ?></p>
                        <div class="promo-meta">
                            <span><?= htmlspecialchars($promo['detail']) ?></span>
                            <span><?= htmlspecialchars($promo['period']) ?></span>
                        </div>

                        <div class="promo-code-box">
                            <strong>Kode Promo</strong>
                            <code><?= htmlspecialchars($promo['code']) ?></code>
                            <p class="note" style="margin:10px 0 0;">Gunakan kode ini saat masuk ke alur reservasi. Verifikasi akhir tetap mengikuti syarat promo dan pengecekan admin/kasir.</p>
                        </div>

                        <div class="cta-row">
                            <?php if (!$user): ?>
                                <a class="btn" href="/login.php">Login untuk klaim</a>
                            <?php elseif ($isPelanggan): ?>
                                <a class="btn" href="/pages/reservasi.php?promo=<?= urlencode($promo['code']) ?>">Lanjut ke reservasi</a>
                            <?php else: ?>
                                <a class="btn" href="/pages/dashboard.php">Buka dashboard</a>
                            <?php endif; ?>
                            <a class="btn-outline" href="/index.php#promo">Promo lain</a>
                        </div>

                        <div class="section-grid">
                            <section class="info-card">
                                <h2>Benefit Promo</h2>
                                <ul>
                                    <?php foreach (($promo['benefits'] ?? []) as $benefit): ?>
                                        <li><?= htmlspecialchars($benefit) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </section>
                            <section class="info-card">
                                <h2>Cara Klaim</h2>
                                <ul>
                                    <?php foreach (($promo['steps'] ?? []) as $step): ?>
                                        <li><?= htmlspecialchars($step) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </section>
                            <section class="info-card">
                                <h2>Syarat & Ketentuan</h2>
                                <ul>
                                    <?php foreach (($promo['terms'] ?? []) as $term): ?>
                                        <li><?= htmlspecialchars($term) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </section>
                        </div>
                    </article>

                    <aside class="side-card">
                        <h3>Promo Lainnya</h3>
                        <?php foreach ($relatedPromos as $related): ?>
                            <a class="side-link" href="/pages/promo.php?slug=<?= urlencode($related['slug']) ?>">
                                <strong><?= htmlspecialchars($related['title']) ?></strong>
                                <span><?= htmlspecialchars($related['code']) ?> &bull; <?= htmlspecialchars($related['badge']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </aside>
                </div>
            <?php endif; ?>
        </section>
    </div>
</body>
</html>

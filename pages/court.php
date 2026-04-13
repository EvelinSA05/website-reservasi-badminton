<?php
session_start();
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/cookies.php';
$company = require __DIR__ . '/../data/company.php';

try {
    try_login_from_remember_cookie();
} catch (Throwable $e) {
    // Halaman tetap bisa dibuka tanpa login.
}

$user = current_user();
$isPelanggan = $user && (($user['role'] ?? '') === 'pelanggan');
$courts = require __DIR__ . '/../data/courts.php';
$slug = trim((string) ($_GET['slug'] ?? ''));

$court = null;
foreach ($courts as $item) {
    if (($item['slug'] ?? '') === $slug) {
        $court = $item;
        break;
    }
}

if (!$court) {
    http_response_code(404);
}

$relatedCourts = array_values(array_filter($courts, static function (array $item) use ($slug): bool {
    return ($item['slug'] ?? '') !== $slug;
}));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(($court['name'] ?? 'Lapangan tidak ditemukan') . ' | ' . $company['short_name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --bg-dark:#110022; --bg-dark-2:#330044; --text:#e2e8f0; --line:rgba(255,255,255,.12); --lime:#a3e635; --cyan:#22d3ee; }
        * { box-sizing:border-box; }
        body {
            margin:0; font-family:'Plus Jakarta Sans', sans-serif; color:var(--text);
            background:
                radial-gradient(circle at 18% 22%, rgba(34,211,238,.16), transparent 24%),
                radial-gradient(circle at 82% 18%, rgba(255,0,200,.15), transparent 22%),
                radial-gradient(circle at 50% 78%, rgba(34,211,238,.08), transparent 30%),
                linear-gradient(135deg, var(--bg-dark), var(--bg-dark-2));
        }
        .container { width:min(1180px, calc(100% - 28px)); margin:0 auto; }
        .top { padding:18px 0; position:sticky; top:0; z-index:30; backdrop-filter:blur(8px); }
        .nav { border:1px solid var(--line); background:rgba(255,255,255,.05); border-radius:999px; padding:10px 16px; display:flex; align-items:center; justify-content:space-between; gap:10px; }
        .brand { display:flex; align-items:center; gap:10px; text-decoration:none; color:#fff; }
        .brand-icon { width:36px; height:36px; border-radius:999px; display:inline-flex; align-items:center; justify-content:center; background:var(--lime); color:#0f172a; font-weight:700; }
        .brand-title { font-size:18px; font-weight:600; font-family:'Space Grotesk', sans-serif; }
        .nav-actions { display:flex; gap:8px; flex-wrap:wrap; }
        .btn, .btn-outline { display:inline-flex; align-items:center; justify-content:center; border-radius:999px; padding:10px 16px; text-decoration:none; font-size:13px; font-weight:600; border:1px solid transparent; }
        .btn { background:#fff; color:#0f172a; }
        .btn-outline { border-color:var(--line); background:rgba(255,255,255,.06); color:#fff; }
        .shell { padding:28px 0 60px; }
        .layout { display:grid; grid-template-columns:minmax(0,1fr) 320px; gap:18px; align-items:start; }
        .main, .side, .empty { border:1px solid var(--line); background:rgba(255,255,255,.05); border-radius:28px; overflow:hidden; }
        .hero-img { width:100%; height:360px; object-fit:cover; display:block; background:#160322; }
        .main-body { padding:24px; }
        .eyebrow { display:inline-flex; align-items:center; gap:8px; padding:7px 12px; border-radius:999px; background:rgba(34,211,238,.14); color:#67e8f9; font-size:12px; font-weight:700; }
        h1 { margin:16px 0 0; font-size:clamp(34px, 5vw, 58px); line-height:1.05; font-family:'Space Grotesk', sans-serif; letter-spacing:-.03em; }
        .lede { margin:14px 0 0; color:#d9dbe8; font-size:18px; line-height:1.8; max-width:820px; }
        .meta { margin-top:18px; display:flex; gap:10px; flex-wrap:wrap; }
        .meta span { padding:8px 12px; border-radius:999px; border:1px solid var(--line); color:#d4d4e3; font-size:13px; }
        .tags { margin-top:18px; display:flex; gap:8px; flex-wrap:wrap; }
        .tags span { padding:8px 12px; border-radius:999px; background:rgba(255,255,255,.05); border:1px solid var(--line); font-size:13px; }
        .cta-row { margin-top:18px; display:flex; gap:10px; flex-wrap:wrap; }
        .section { margin-top:22px; border:1px solid var(--line); border-radius:24px; background:rgba(10,5,28,.46); padding:18px; }
        .section h2 { margin:0 0 10px; font-size:24px; font-family:'Space Grotesk', sans-serif; }
        .section ul { margin:0; padding-left:20px; color:#d4d4e3; line-height:1.8; }
        .side { padding:18px; }
        .side h3 { margin:0 0 12px; font-size:22px; font-family:'Space Grotesk', sans-serif; }
        .side-link { display:block; padding:14px 0; border-top:1px solid var(--line); text-decoration:none; color:inherit; }
        .side-link:first-of-type { border-top:0; padding-top:0; }
        .side-link strong { display:block; font-size:18px; line-height:1.35; }
        .side-link span { display:block; margin-top:6px; color:#67e8f9; font-size:13px; }
        .empty { padding:30px; text-align:center; }
        @media (max-width:980px) { .layout { grid-template-columns:1fr; } }
        @media (max-width:720px) { .container { width:min(100% - 20px, 1180px); } .hero-img { height:240px; } .main-body { padding:18px; } h1 { font-size:36px; } }
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
                <a href="/index.php#lapangan" class="btn-outline">Kembali ke Lapangan</a>
                <?php if (!$user): ?>
                    <a href="/login.php" class="btn">Login</a>
                <?php elseif ($isPelanggan): ?>
                    <a href="/pages/reservasi.php?court=<?= urlencode($court['db_id'] ?? '') ?>" class="btn">Booking Sekarang</a>
                <?php else: ?>
                    <a href="/pages/dashboard.php" class="btn">Dashboard</a>
                <?php endif; ?>
            </div>
        </header>
    </div>

    <section class="shell">
        <?php if (!$court): ?>
            <div class="empty">
                <h1>Lapangan tidak ditemukan</h1>
                <p>Data lapangan yang diminta belum tersedia.</p>
            </div>
        <?php else: ?>
            <div class="layout">
                <article class="main">
                    <img class="hero-img" src="/<?= htmlspecialchars($court['image']) ?>" alt="<?= htmlspecialchars($court['name']) ?>">
                    <div class="main-body">
                        <span class="eyebrow">Preview Lapangan</span>
                        <h1><?= htmlspecialchars($court['name']) ?></h1>
                        <p class="lede"><?= htmlspecialchars($court['summary']) ?></p>
                        <div class="meta">
                            <span><?= htmlspecialchars($court['type']) ?></span>
                            <span><?= htmlspecialchars($court['price']) ?></span>
                            <span>&#11088; <?= htmlspecialchars($court['rating']) ?></span>
                            <span><?= htmlspecialchars($court['hours']) ?></span>
                            <span><?= htmlspecialchars($court['status']) ?></span>
                        </div>
                        <div class="tags">
                            <?php foreach (($court['tags'] ?? []) as $tag): ?>
                                <span><?= htmlspecialchars($tag) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <div class="cta-row">
                            <?php if (!$user): ?>
                                <a class="btn" href="/login.php">Login untuk booking</a>
                            <?php elseif ($isPelanggan): ?>
                                <a class="btn" href="/pages/reservasi.php?court=<?= urlencode($court['db_id']) ?>">Lanjut ke reservasi</a>
                            <?php else: ?>
                                <a class="btn" href="/pages/dashboard.php">Buka dashboard</a>
                            <?php endif; ?>
                            <a class="btn-outline" href="/index.php#lapangan">Lihat court lain</a>
                        </div>

                        <section class="section">
                            <h2>Fasilitas Utama</h2>
                            <ul>
                                <?php foreach (($court['features'] ?? []) as $feature): ?>
                                    <li><?= htmlspecialchars($feature) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </section>

                        <section class="section">
                            <h2>Info Booking</h2>
                            <ul>
                                <li>Jam operasional: <?= htmlspecialchars($court['hours']) ?></li>
                                <li>Jenis lantai: <?= htmlspecialchars($court['type']) ?></li>
                                <li>Harga saat ini: <?= htmlspecialchars($court['price']) ?></li>
                            </ul>
                        </section>
                    </div>
                </article>

                <aside class="side">
                    <h3>Court Lainnya</h3>
                    <?php foreach ($relatedCourts as $related): ?>
                        <a class="side-link" href="/pages/court.php?slug=<?= urlencode($related['slug']) ?>">
                            <strong><?= htmlspecialchars($related['name']) ?></strong>
                            <span><?= htmlspecialchars($related['price']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </aside>
            </div>
        <?php endif; ?>
    </section>
</div>
</body>
</html>

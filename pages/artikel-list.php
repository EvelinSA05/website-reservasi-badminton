<?php
session_start();
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/cookies.php';
$company = require __DIR__ . '/../data/company.php';

try {
    try_login_from_remember_cookie();
} catch (Throwable $e) {
    // Biarkan halaman tetap dapat diakses.
}

$user = current_user();
$isPelanggan = $user && (($user['role'] ?? '') === 'pelanggan');
$articles = require __DIR__ . '/../data/articles.php';

$search = trim((string) ($_GET['search'] ?? ''));
$category = trim((string) ($_GET['category'] ?? 'Semua'));

$categories = ['Semua'];
foreach ($articles as $article) {
    $label = trim((string) ($article['category'] ?? ''));
    if ($label !== '' && !in_array($label, $categories, true)) {
        $categories[] = $label;
    }
}

$filteredArticles = array_values(array_filter($articles, static function (array $article) use ($search, $category): bool {
    $matchCategory = $category === 'Semua' || (($article['category'] ?? '') === $category);
    $haystack = strtolower(
        trim(
            ($article['title'] ?? '') . ' ' .
            ($article['excerpt'] ?? '') . ' ' .
            ($article['author'] ?? '')
        )
    );
    $matchSearch = $search === '' || str_contains($haystack, strtolower($search));
    return $matchCategory && $matchSearch;
}));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Artikel & Berita | <?= htmlspecialchars($company['short_name']) ?></title>
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
            --cyan: #22d3ee;
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
        .container { width: min(1240px, calc(100% - 28px)); margin: 0 auto; }
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
        .hero { padding: 26px 0 16px; }
        .eyebrow {
            display:inline-flex; align-items:center; gap:8px;
            border-radius:999px; padding:7px 13px; font-size:12px; font-weight:700;
            background: rgba(34,211,238,.14); color:#67e8f9;
        }
        h1 {
            margin: 16px 0 0;
            font-size: clamp(36px, 5vw, 62px);
            line-height: 1.05;
            letter-spacing: -.03em;
            font-family: 'Space Grotesk', sans-serif;
        }
        .hero p { margin: 14px 0 0; max-width: 880px; color: var(--muted); font-size: 18px; line-height: 1.75; }
        .filters {
            margin-top: 22px;
            border: 1px solid var(--line);
            border-radius: 28px;
            background: rgba(255,255,255,.05);
            padding: 16px;
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) auto;
            gap: 14px;
            align-items: center;
        }
        .search-input {
            width: 100%;
            border:1px solid var(--line);
            background: rgba(255,255,255,.06);
            border-radius: 999px;
            padding: 12px 16px;
            color:#fff;
            outline:none;
            font-size: 14px;
        }
        .tabs { display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; }
        .tab {
            display:inline-flex;
            align-items:center;
            justify-content:center;
            border-radius:999px;
            padding:10px 14px;
            border:1px solid var(--line);
            color:#cbd5e1;
            text-decoration:none;
            font-size:13px;
            font-weight:700;
            background: rgba(255,255,255,.04);
        }
        .tab.active {
            background: linear-gradient(135deg, rgba(34,211,238,.9), rgba(163,230,53,.9));
            color:#130019;
            border-color: transparent;
        }
        .summary {
            margin-top: 16px;
            color: #cbd5e1;
            font-size: 14px;
        }
        .grid {
            margin-top: 18px;
            display:grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            align-items: start;
        }
        .card {
            display:flex;
            flex-direction:column;
            height:100%;
            border:1px solid var(--line);
            border-radius: 26px;
            overflow:hidden;
            background: rgba(255,255,255,.05);
            text-decoration:none;
            color:inherit;
            box-shadow: 0 14px 34px rgba(10, 0, 20, .22);
        }
        .card-image {
            width:100%;
            height: 220px;
            object-fit: cover;
            background:#160322;
        }
        .card-body {
            padding: 18px;
            display:flex;
            flex-direction:column;
            gap: 12px;
            flex:1;
        }
        .card-meta {
            display:flex;
            gap:8px;
            flex-wrap:wrap;
            color:#94a3b8;
            font-size:13px;
        }
        .badge {
            display:inline-flex;
            align-items:center;
            width:max-content;
            padding:6px 10px;
            border-radius:999px;
            background: rgba(34,211,238,.14);
            color:#67e8f9;
            font-size:12px;
            font-weight:700;
        }
        .card h2 {
            margin:0;
            font-size: 27px;
            line-height: 1.18;
            font-family: 'Space Grotesk', sans-serif;
        }
        .card p {
            margin:0;
            color:#d4d4e3;
            line-height: 1.7;
            font-size: 14px;
        }
        .more {
            margin-top:auto;
            display:inline-flex;
            align-items:center;
            gap:8px;
            color:#67e8f9;
            font-size:13px;
            font-weight:700;
        }
        .empty {
            margin-top: 20px;
            border:1px solid var(--line);
            border-radius: 26px;
            background: rgba(255,255,255,.05);
            padding: 28px;
            color:#d4d4e3;
        }
        @media (max-width: 1024px) {
            .grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .filters { grid-template-columns: 1fr; }
            .tabs { justify-content:flex-start; }
        }
        @media (max-width: 680px) {
            .container { width: min(100% - 20px, 1240px); }
            .grid { grid-template-columns: 1fr; }
            h1 { font-size: 38px; }
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
                    <a href="/index.php#berita" class="btn-outline">Kembali ke Landing</a>
                    <?php if (!$user): ?>
                        <a href="/login.php" class="btn">Login</a>
                    <?php elseif ($isPelanggan): ?>
                        <a href="/pages/reservasi.php" class="btn">Booking Sekarang</a>
                    <?php else: ?>
                        <a href="/pages/dashboard.php" class="btn">Dashboard</a>
                    <?php endif; ?>
                </div>
            </header>
        </div>

        <section class="hero">
            <span class="eyebrow">&#128240; Portal Artikel</span>
            <h1>Artikel, tips, dan update komunitas <?= htmlspecialchars($company['short_name']) ?> dalam satu tempat.</h1>
            <p>Cari insight latihan, agenda komunitas, dan highlight yang relevan buat pemain aktif. Semua artikel di bawah sudah bisa dicari dan difilter berdasarkan kategori.</p>

            <form class="filters" method="get" action="/pages/artikel-list.php">
                <input
                    class="search-input"
                    type="search"
                    name="search"
                    value="<?= htmlspecialchars($search) ?>"
                    placeholder="Cari judul, ringkasan, atau nama penulis">
                <div class="tabs">
                    <?php foreach ($categories as $itemCategory): ?>
                        <?php
                        $query = [];
                        if ($search !== '') {
                            $query['search'] = $search;
                        }
                        if ($itemCategory !== 'Semua') {
                            $query['category'] = $itemCategory;
                        }
                        $href = '/pages/artikel-list.php' . ($query ? '?' . http_build_query($query) : '');
                        ?>
                        <a class="tab<?= $category === $itemCategory ? ' active' : '' ?>" href="<?= htmlspecialchars($href) ?>">
                            <?= htmlspecialchars($itemCategory) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </form>

            <div class="summary">
                Menampilkan <?= count($filteredArticles) ?> artikel<?= $search !== '' ? ' untuk pencarian "' . htmlspecialchars($search) . '"' : '' ?><?= $category !== 'Semua' ? ' pada kategori ' . htmlspecialchars($category) : '' ?>.
            </div>
        </section>

        <?php if (!$filteredArticles): ?>
            <div class="empty">
                Belum ada artikel yang cocok dengan filter ini. Coba ganti kata kunci atau pilih kategori lain.
            </div>
        <?php else: ?>
            <section class="grid">
                <?php foreach ($filteredArticles as $article): ?>
                    <a class="card" href="/pages/artikel.php?slug=<?= urlencode($article['slug']) ?>">
                        <img class="card-image" src="<?= htmlspecialchars($article['image']) ?>" alt="<?= htmlspecialchars($article['title']) ?>">
                        <div class="card-body">
                            <span class="badge"><?= htmlspecialchars($article['category']) ?></span>
                            <h2><?= htmlspecialchars($article['title']) ?></h2>
                            <p><?= htmlspecialchars($article['excerpt']) ?></p>
                            <div class="card-meta">
                                <span><?= htmlspecialchars($article['publishedAt']) ?></span>
                                <span><?= htmlspecialchars($article['readTime']) ?></span>
                                <span><?= htmlspecialchars($article['author']) ?></span>
                            </div>
                            <span class="more">Buka artikel <span aria-hidden="true">&rarr;</span></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </div>
</body>
</html>

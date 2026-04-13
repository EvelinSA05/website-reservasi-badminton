<?php
session_start();
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/cookies.php';
$company = require __DIR__ . '/../data/company.php';

try {
    try_login_from_remember_cookie();
} catch (Throwable $e) {
    // Abaikan agar halaman artikel tetap bisa dibuka.
}

$user = current_user();
$isPelanggan = $user && (($user['role'] ?? '') === 'pelanggan');
$articles = require __DIR__ . '/../data/articles.php';
$slug = trim((string) ($_GET['slug'] ?? ''));

$article = null;
foreach ($articles as $item) {
    if (($item['slug'] ?? '') === $slug) {
        $article = $item;
        break;
    }
}

if (!$article) {
    http_response_code(404);
}

$relatedArticles = array_values(array_filter($articles, static function (array $item) use ($slug): bool {
    return ($item['slug'] ?? '') !== $slug;
}));
$relatedArticles = array_slice($relatedArticles, 0, 3);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(($article['title'] ?? 'Artikel tidak ditemukan') . ' | ' . $company['short_name']) ?></title>
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
        .container { width: min(1180px, calc(100% - 32px)); margin: 0 auto; }
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
        .article-shell { padding: 28px 0 60px; }
        .article-layout { display:grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 18px; align-items:start; }
        .article-main {
            border:1px solid var(--line);
            background: rgba(255,255,255,.05);
            border-radius: 30px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(10, 0, 20, .28);
        }
        .hero-image { width:100%; max-height:420px; object-fit:cover; display:block; background:#160322; }
        .article-body { padding: 24px; }
        .eyebrow { display:inline-flex; align-items:center; gap:8px; padding:7px 12px; border-radius:999px; background: rgba(34,211,238,.14); color:#67e8f9; font-size:12px; font-weight:700; }
        h1 { margin: 16px 0 0; font-size: clamp(34px, 5vw, 58px); line-height: 1.05; letter-spacing: -.03em; font-family: 'Space Grotesk', sans-serif; }
        .lede { margin: 16px 0 0; color:#dbeafe; font-size: 18px; line-height: 1.8; max-width: 840px; }
        .meta { margin-top: 18px; display:flex; flex-wrap:wrap; gap:10px; color: var(--muted); font-size: 14px; }
        .meta span { padding: 8px 12px; border-radius:999px; border:1px solid var(--line); background: rgba(255,255,255,.04); }
        .article-content { margin-top: 28px; display:grid; gap: 24px; }
        .content-block {
            border:1px solid var(--line);
            border-radius: 24px;
            padding: 20px;
            background: rgba(10, 5, 28, .5);
        }
        .content-block h2 { margin:0; font-size: 28px; font-family: 'Space Grotesk', sans-serif; }
        .content-block p { margin:12px 0 0; color:#d4d4e3; line-height: 1.9; font-size:16px; }
        .content-block ul { margin:14px 0 0; padding-left: 20px; color:#d4d4e3; line-height:1.8; }
        .content-block li + li { margin-top: 6px; }
        .article-side { display:grid; gap: 14px; }
        .side-card {
            border:1px solid var(--line);
            border-radius: 24px;
            background: rgba(255,255,255,.05);
            padding: 18px;
        }
        .side-card h3 { margin:0 0 12px; font-size: 22px; font-family: 'Space Grotesk', sans-serif; }
        .side-card p { margin:0; color:#cbd5e1; line-height:1.7; }
        .side-link {
            display:block;
            padding: 14px 0;
            border-top: 1px solid var(--line);
            text-decoration:none;
            color:inherit;
        }
        .side-link:first-of-type { border-top: 0; padding-top: 0; }
        .side-link strong { display:block; font-size: 18px; line-height: 1.35; }
        .side-link span { display:block; margin-top: 6px; color:#67e8f9; font-size: 13px; }
        .side-cta {
            margin-top: 14px;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            border-radius:999px;
            padding:10px 14px;
            text-decoration:none;
            font-size:13px;
            font-weight:700;
            border:1px solid var(--line);
            background: rgba(255,255,255,.06);
            color:#fff;
        }
        .empty-state {
            border:1px solid var(--line);
            border-radius: 28px;
            background: rgba(255,255,255,.05);
            padding: 32px;
            text-align:center;
        }
        .empty-state h1 { font-size: 40px; }
        .empty-state p { color:#cbd5e1; line-height:1.7; }
        @media (max-width: 980px) {
            .article-layout { grid-template-columns: 1fr; }
        }
        @media (max-width: 720px) {
            .container { width: min(100% - 20px, 1180px); }
            h1 { font-size: 36px; }
            .article-body { padding: 18px; }
            .content-block { padding: 16px; }
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
                    <a href="/index.php#berita" class="btn-outline">Kembali ke Berita</a>
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

        <section class="article-shell">
            <?php if (!$article): ?>
                <div class="empty-state">
                    <span class="eyebrow">Artikel</span>
                    <h1>Artikel tidak ditemukan</h1>
                    <p>Slug yang diminta belum tersedia. Kamu bisa kembali ke halaman artikel <?= htmlspecialchars($company['short_name']) ?> untuk membuka konten lain yang aktif.</p>
                    <p><a href="/index.php#berita" class="btn" style="margin-top:10px;">Lihat daftar artikel</a></p>
                </div>
            <?php else: ?>
                <div class="article-layout">
                    <article class="article-main">
                        <img class="hero-image" src="<?= htmlspecialchars($article['image']) ?>" alt="<?= htmlspecialchars($article['title']) ?>">
                        <div class="article-body">
                            <span class="eyebrow">&#128240; <?= htmlspecialchars($article['category']) ?></span>
                            <h1><?= htmlspecialchars($article['title']) ?></h1>
                            <p class="lede"><?= htmlspecialchars($article['excerpt']) ?></p>
                            <div class="meta">
                                <span><?= htmlspecialchars($article['publishedAt']) ?></span>
                                <span><?= htmlspecialchars($article['readTime']) ?></span>
                                <span><?= htmlspecialchars($article['author']) ?></span>
                            </div>

                            <div class="article-content">
                                <?php foreach (($article['content'] ?? []) as $section): ?>
                                    <section class="content-block">
                                        <?php if (!empty($section['heading'])): ?>
                                            <h2><?= htmlspecialchars($section['heading']) ?></h2>
                                        <?php endif; ?>

                                        <?php foreach (($section['paragraphs'] ?? []) as $paragraph): ?>
                                            <p><?= htmlspecialchars($paragraph) ?></p>
                                        <?php endforeach; ?>

                                        <?php if (!empty($section['bullets'])): ?>
                                            <ul>
                                                <?php foreach ($section['bullets'] as $bullet): ?>
                                                    <li><?= htmlspecialchars($bullet) ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </section>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </article>

                    <aside class="article-side">
                        <div class="side-card">
                            <h3>Ringkasan Cepat</h3>
                            <p>Section berita <?= htmlspecialchars($company['short_name']) ?> dipakai sebagai konten edukasi, highlight komunitas, dan penghubung ke aktivitas booking atau sparring di venue.</p>
                        </div>

                        <div class="side-card">
                            <h3>Artikel Terkait</h3>
                            <?php foreach ($relatedArticles as $related): ?>
                                <a class="side-link" href="/pages/artikel.php?slug=<?= urlencode($related['slug']) ?>">
                                    <strong><?= htmlspecialchars($related['title']) ?></strong>
                                    <span><?= htmlspecialchars($related['category']) ?> &bull; <?= htmlspecialchars($related['readTime']) ?></span>
                                </a>
                            <?php endforeach; ?>
                            <a class="side-cta" href="/pages/artikel-list.php">Lihat semua artikel</a>
                        </div>
                    </aside>
                </div>
            <?php endif; ?>
        </section>
    </div>
</body>
</html>

<?php
session_start();
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/cookies.php';

try {
    try_login_from_remember_cookie();
} catch (Throwable $e) {
    set_flash('error', 'Sesi login tidak valid, silakan login ulang.');
}

$user = current_user();
$flash = pull_flash();
$isPelanggan = $user && (($user['role'] ?? '') === 'pelanggan');

$courts = require __DIR__ . '/data/courts.php';

$coaches = require __DIR__ . '/data/coaches.php';
$partners = require __DIR__ . '/data/partners.php';

$promos = require __DIR__ . '/data/promos.php';
$company = require __DIR__ . '/data/company.php';

$articles = require __DIR__ . '/data/articles.php';
$featuredArticle = $articles[0] ?? null;
$news = array_values(array_filter($articles, static function (array $article): bool {
    return !($article['featured'] ?? false);
}));
$reservationEntryUrl = !$user
    ? 'login.php'
    : ($isPelanggan ? 'pages/reservasi.php' : 'pages/dashboard.php');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($company['short_name']) ?> | Reservasi Badminton</title>
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
            --lime: #a3e635;
            --cyan: #22d3ee;
            --magenta: #ff00c8;
            --orange: #fb923c;
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
        .container { width: min(1540px, calc(100% - 14px)); margin: 0 auto; }
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
        .menu { display:flex; gap: 18px; }
        .menu a { color: #cbd5e1; text-decoration: none; font-size: 14px; }
        .menu a:hover { color: #fff; }
        .btn, .btn-outline {
            display:inline-flex; align-items:center; justify-content:center;
            border-radius:999px; padding:10px 16px; text-decoration:none;
            font-size: 13px; font-weight: 600; border:1px solid transparent;
        }
        .btn { background:#fff; color:#0f172a; }
        .btn-outline { border-color: var(--line); background: rgba(255,255,255,.06); color:#fff; }
        .nav-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            flex-wrap: wrap;
        }
        .profile-menu {
            position: relative;
        }
        .profile-trigger {
            border: 1px solid rgba(255,255,255,.14);
            background:
                linear-gradient(135deg, rgba(34,211,238,.12), rgba(255,255,255,.06)),
                rgba(255,255,255,.05);
            color: #fff;
            border-radius: 999px;
            min-height: 54px;
            min-width: 54px;
            padding: 6px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            box-shadow: 0 14px 26px rgba(2, 6, 23, .18);
        }
        .profile-trigger:hover {
            border-color: rgba(34,211,238,.32);
        }
        .profile-trigger:focus-visible {
            outline: 2px solid rgba(34,211,238,.55);
            outline-offset: 2px;
        }
        .profile-avatar {
            width: 40px;
            height: 40px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--lime), var(--cyan));
            color: #140019;
            font-weight: 800;
            font-size: 14px;
            letter-spacing: .04em;
            flex: 0 0 auto;
        }
        .profile-meta {
            display: grid;
            gap: 2px;
            text-align: left;
            padding-right: 2px;
        }
        .profile-name {
            font-size: 13px;
            font-weight: 700;
            line-height: 1.1;
            color: #f8fafc;
        }
        .profile-role {
            font-size: 11px;
            color: #a5b4cf;
            text-transform: capitalize;
        }
        .profile-caret {
            width: 10px;
            height: 10px;
            border-right: 2px solid rgba(255,255,255,.72);
            border-bottom: 2px solid rgba(255,255,255,.72);
            transform: rotate(45deg) translateY(-1px);
            margin-right: 6px;
            transition: transform .18s ease;
            flex: 0 0 auto;
        }
        .profile-menu.is-open .profile-caret {
            transform: rotate(-135deg) translateY(-1px);
        }
        .profile-dropdown {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 220px;
            padding: 12px;
            border-radius: 22px;
            border: 1px solid rgba(255,255,255,.12);
            background:
                radial-gradient(circle at top right, rgba(34,211,238,.10), transparent 32%),
                linear-gradient(145deg, rgba(17, 0, 34, .96), rgba(40, 12, 64, .94));
            box-shadow: 0 24px 42px rgba(2, 6, 23, .28);
            display: grid;
            gap: 8px;
            opacity: 0;
            transform: translateY(-6px);
            pointer-events: none;
            transition: opacity .2s ease, transform .2s ease;
            z-index: 40;
        }
        .profile-menu.is-open .profile-dropdown {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }
        .profile-dropdown-head {
            padding: 6px 4px 10px;
            border-bottom: 1px solid rgba(255,255,255,.08);
        }
        .profile-dropdown-head strong {
            display: block;
            font-size: 14px;
            color: #f8fafc;
        }
        .profile-dropdown-head span {
            display: block;
            margin-top: 4px;
            font-size: 12px;
            color: #9fb1cc;
            text-transform: capitalize;
        }
        .profile-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            min-height: 46px;
            padding: 0 14px;
            border-radius: 16px;
            text-decoration: none;
            color: #eef2ff;
            font-size: 13px;
            font-weight: 700;
            background: rgba(255,255,255,.04);
            border: 1px solid rgba(255,255,255,.08);
        }
        .profile-link:hover {
            background: rgba(255,255,255,.08);
            border-color: rgba(34,211,238,.24);
        }
        .profile-link.logout-link {
            color: #fecaca;
        }
        .profile-link small {
            display: block;
            font-size: 11px;
            font-weight: 500;
            color: #94a3b8;
        }
        .welcome-flash {
            margin-top: 12px;
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 14px;
            align-items: center;
            padding: 14px 18px;
            border-radius: 22px;
            border: 1px solid rgba(190,242,100,.18);
            background:
                radial-gradient(circle at top right, rgba(34,211,238,.16), transparent 34%),
                linear-gradient(135deg, rgba(163,230,53,.12), rgba(255,255,255,.04));
            box-shadow: 0 18px 34px rgba(15, 23, 42, .18);
            overflow: hidden;
            max-height: 180px;
            opacity: 1;
            transform: translateY(0);
            transition: opacity .45s ease, transform .45s ease, max-height .55s ease, margin-top .55s ease, padding-top .55s ease, padding-bottom .55s ease, border-width .55s ease;
        }
        .welcome-flash.err {
            border-color: rgba(248,113,113,.26);
            background:
                radial-gradient(circle at top right, rgba(248,113,113,.12), transparent 34%),
                linear-gradient(135deg, rgba(127,29,29,.22), rgba(255,255,255,.04));
        }
        .welcome-flash.is-hidden {
            opacity: 0;
            transform: translateY(-10px);
            max-height: 0;
            margin-top: 0;
            padding-top: 0;
            padding-bottom: 0;
            border-width: 0;
            pointer-events: none;
        }
        .welcome-flash-icon {
            width: 48px;
            height: 48px;
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, rgba(190,242,100,.94), rgba(34,211,238,.94));
            color: #140019;
            font-size: 22px;
            box-shadow: 0 12px 24px rgba(34,211,238,.16);
        }
        .welcome-flash.err .welcome-flash-icon {
            background: linear-gradient(135deg, rgba(248,113,113,.94), rgba(251,146,60,.92));
            color: #fff;
        }
        .welcome-flash-copy strong {
            display: block;
            font-size: 16px;
            font-weight: 700;
            color: #f8fafc;
            margin-bottom: 4px;
        }
        .welcome-flash-copy span {
            display: block;
            color: #cbd5e1;
            line-height: 1.6;
            font-size: 14px;
        }
        .hero { padding: 24px 0 10px; }
        .hero-grid { display:grid; grid-template-columns: 1.1fr .9fr; gap: 22px; }
        .badge {
            display:inline-flex; align-items:center; gap:8px;
            border-radius:999px; padding:7px 13px; font-size:12px; font-weight:600;
            background: rgba(163,230,53,.16); color:#bef264;
        }
        .hero h1 {
            margin: 16px 0 0;
            font-size: clamp(34px, 5vw, 62px);
            line-height: 1.06;
            letter-spacing: -.02em;
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;`r`n            max-width: 820px;
        }
        .hero p { margin: 14px 0 0; color: var(--muted); font-size: 18px; line-height: 1.75; max-width: 860px; }
        .cta { margin-top: 22px; display:flex; gap:10px; flex-wrap: wrap; }
        .stats { margin-top: 22px; display:grid; grid-template-columns: repeat(3,1fr); gap: 10px; }
        .stat { border:1px solid var(--line); background: rgba(255,255,255,.05); border-radius: 16px; padding: 14px; }
        .stat strong { display:block; font-size: 30px; font-weight: 700; }
        .stat span { color: var(--muted); font-size: 13px; }
        .preview {
            border:1px solid var(--line);
            background: rgba(255,255,255,.06);
            border-radius: 30px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(132,204,22,.16);
        }
        .preview-image-wrap { position: relative; height: 460px; }
        .preview-image-wrap img { width:100%; height:100%; object-fit: cover; display:block; }
        .overlay { position:absolute; inset:0; background: linear-gradient(to top, rgba(2,6,23,.9), rgba(2,6,23,.15)); }
        .preview-top {
            position:absolute; top:16px; left:16px; right:16px;
            display:flex; justify-content:space-between; gap:8px;
        }
        .pill { padding:6px 10px; border-radius:999px; font-size:12px; font-weight:600; }
        .pill-light { background: rgba(255,255,255,.92); color:#0f172a; }
        .pill-lime { background: var(--lime); color:#0f172a; }
        .preview-bottom {
            position:absolute; left:16px; right:16px; bottom:16px;
            border:1px solid var(--line); background: rgba(2,6,23,.72);
            border-radius: 18px; padding: 14px;
        }
        .preview-bottom h3 { margin:0; font-size:24px; }
        .preview-bottom p { margin:6px 0 0; color:#cbd5e1; font-size: 14px; }
        .preview-tags { margin-top: 10px; display:flex; gap:8px; flex-wrap: wrap; }
        .tag { border:1px solid var(--line); border-radius:999px; padding:4px 10px; font-size: 12px; color:#cbd5e1; }

        section.block { padding: 56px 0 10px; }
        .title-mini { margin:0; color:#bef264; text-transform: uppercase; letter-spacing: .2em; font-size: 12px; font-weight:600; }
        .title-main { margin:10px 0 0; font-size: clamp(28px,4vw,42px); font-weight:700; letter-spacing:-.02em; }
        .title-sub { margin:10px 0 0; max-width: 760px; color: var(--muted); line-height:1.7; }

        .controls { margin-top: 16px; display:flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .search-wrap { position: relative; }
        .search {
            width: 320px; border:1px solid var(--line); background: rgba(255,255,255,.06);
            border-radius: 999px; padding: 10px 14px 10px 38px; color:#fff; outline:none;
        }
        .search-icon { position:absolute; left:14px; top:50%; transform: translateY(-50%); color:#94a3b8; font-size: 14px; }
        .tabs { display:flex; gap:8px; padding:4px; border:1px solid var(--line); border-radius:999px; background: rgba(255,255,255,.05); flex-wrap: wrap; }
        .tab-btn {
            border:0; background: transparent; color:#cbd5e1; border-radius:999px; padding:8px 12px;
            font-weight:600; font-size:12px; cursor:pointer;
        }
        .tab-btn.active { background: var(--lime); color:#0f172a; }

        .court-grid { margin-top: 16px; display:grid; grid-template-columns: repeat(3,1fr); gap: 14px; }
        .court-card {
            border:1px solid var(--line); background: rgba(255,255,255,.05);
            border-radius: 22px; overflow:hidden; cursor:pointer;
            transition: transform .2s ease;
        }
        .court-card:hover { transform: translateY(-4px); }
        .court-img { position:relative; height: 220px; overflow: hidden; }
        .court-img img { width:100%; height:100%; object-fit: cover; transition: transform .4s ease; }
        .court-card:hover .court-img img { transform: scale(1.05); }
        .court-overlay { position:absolute; inset:0; background: linear-gradient(to top, rgba(2,6,23,.9), rgba(2,6,23,.1)); }
        .court-badges { position:absolute; top:12px; left:12px; display:flex; gap:8px; flex-wrap: wrap; }
        .court-body { padding: 14px; }
        .court-body h4 { margin:0; font-size: 21px; }
        .court-body p { margin:6px 0 0; color:#cbd5e1; font-size: 13px; }
        .court-tags { margin-top: 10px; display:flex; gap:7px; flex-wrap: wrap; }
        .court-tags span { border:1px solid var(--line); border-radius:999px; padding:4px 8px; font-size:11px; color:#cbd5e1; }
        .full-btn { margin-top: 12px; width:100%; border:0; border-radius:999px; padding:10px; font-weight:600; cursor:pointer; background:#fff; color:#0f172a; }

        .split-grid { margin-top: 16px; display:grid; grid-template-columns: repeat(2,1fr); gap: 14px; }
        .panel { border:1px solid var(--line); border-radius: 26px; padding: 16px; background: rgba(255,255,255,.05); }
        .panel h3 { margin:10px 0 0; font-size: 32px; line-height:1.2; font-weight: 700; }
        .panel-list { margin-top: 14px; display:grid; gap: 10px; }
        .row { border:1px solid var(--line); border-radius: 16px; background: rgba(2,6,23,.4); padding: 12px; display:flex; justify-content:space-between; gap:10px; align-items:center; }
        .row h5 { margin:0; font-size: 18px; }
        .row p { margin:4px 0 0; color:#cbd5e1; font-size: 12px; }
        .row-right { text-align:right; }
        .row-right .score { font-size:12px; }
        .row-btn { margin-top: 8px; border:1px solid var(--line); background: rgba(255,255,255,.06); color:#fff; border-radius:999px; padding:8px 12px; font-size:12px; font-weight:600; }

        .promo-grid { margin-top: 14px; display:grid; grid-template-columns: repeat(3,1fr); gap: 14px; }
        .promo-card { border:1px solid var(--line); border-radius: 22px; background: rgba(255,255,255,.06); padding: 16px; display:block; text-decoration:none; color:inherit; }
        .promo-badge { display:inline-flex; background: var(--orange); color:#0f172a; border-radius:999px; padding:5px 10px; font-size:12px; font-weight:600; }
        .promo-card h4 { margin:12px 0 0; font-size: 24px; font-weight: 700; }
        .promo-card p { margin:8px 0 0; color:#cbd5e1; line-height:1.6; }
        .promo-code { margin-top: 12px; display:inline-flex; padding:6px 10px; border-radius:999px; border:1px solid rgba(255,255,255,.14); color:#fff; font-size:12px; font-weight:700; letter-spacing:.08em; }
        .link-btn { margin-top: 10px; border:0; background:transparent; color:#bef264; font-weight:600; padding:0; cursor:pointer; }

        .news-grid { margin-top: 14px; display:grid; grid-template-columns: 1.2fr .8fr; gap: 14px; align-items: stretch; }
        .news-main { border:1px solid var(--line); border-radius: 28px; overflow:hidden; background: rgba(255,255,255,.06); display:grid; grid-template-columns: 1fr .95fr; height: 100%; min-height: 100%; }
        .news-main img { width:100%; height:100%; min-height: 100%; object-fit: cover; }
        .news-main-body { padding: 20px; display: flex; flex-direction: column; gap: 18px; min-height: 100%; }
        .news-main-copy { display: grid; gap: 14px; align-content: start; }
        .news-main-body h4 { margin:12px 0 0; font-size: 34px; line-height:1.15; font-weight: 700; }
        .news-main-body p { margin:10px 0 0; color:#cbd5e1; line-height:1.7; }
        .news-support {
            margin-top: 2px;
            color:#b8c7df;
            font-size: 14px;
            line-height: 1.8;
        }
        .news-meta { margin-top: auto; padding-top: 6px; color:#94a3b8; font-size: 13px; display:flex; gap:12px; flex-wrap: wrap; }
        .news-actions { display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
        .news-link {
            display:inline-flex; align-items:center; justify-content:center;
            border-radius:999px; padding:11px 16px; text-decoration:none; font-size:13px; font-weight:700;
            border:1px solid rgba(34,211,238,.24); background: linear-gradient(135deg, rgba(34,211,238,.95), rgba(255,0,200,.86));
            color:#140019; box-shadow: 0 12px 24px rgba(255,0,200,.16);
        }
        .news-link.alt {
            background: rgba(255,255,255,.04); color:#e2e8f0; border-color: var(--line); box-shadow:none;
        }

        .news-side { display:grid; gap: 10px; align-content: stretch; }
        .news-item { border:1px solid var(--line); border-radius: 20px; background: rgba(255,255,255,.05); padding: 14px; height: fit-content; display:block; text-decoration:none; color:inherit; }
        .news-item .cat { color:#67e8f9; font-size: 12px; }
        .news-item h5 { margin:8px 0 0; font-size: 22px; line-height:1.25; font-weight: 700; }
        .news-item p { margin:8px 0 0; color:#cbd5e1; font-size: 13px; }
        .news-item .excerpt { line-height: 1.65; }
        .news-item .more { margin-top: 12px; display:inline-flex; align-items:center; gap:8px; color:#67e8f9; font-size:13px; font-weight:700; }

        
        .site-footer {
            margin: 22px 0 18px;
            border: 1px solid var(--line);
            background: linear-gradient(160deg, rgba(30, 64, 175, .26), rgba(15, 23, 42, .68));
            border-radius: 22px;
            padding: 20px 18px 14px;
        }
        .footer-grid {
            display: grid;
            grid-template-columns: 1.2fr .9fr .9fr;
            gap: 14px;
        }
        .footer-title {
            margin: 0 0 8px;
            font-size: 20px;
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 600;
            color: #f8fafc;
        }
        .footer-copy,
        .footer-list a {
            color: #cbd5e1;
            font-size: 14px;
            text-decoration: none;
            line-height: 1.65;
        }
        .footer-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            gap: 4px;
        }
        .footer-social {
            display: flex;
            gap: 10px;
            margin-top: 6px;
        }
        .footer-social a {
            width: 38px;
            height: 38px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--line);
            background: rgba(255,255,255,.08);
            color: #f8fafc;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
        }
        .footer-bottom {
            margin-top: 14px;
            padding-top: 10px;
            border-top: 1px solid var(--line);
            color: #94a3b8;
            font-size: 12px;
            text-align: center;
        }
        @media (max-width: 1024px) {
            .hero-grid { grid-template-columns: 1fr; }
            .court-grid { grid-template-columns: repeat(2,1fr); }
            .split-grid { grid-template-columns: 1fr; }
            .promo-grid { grid-template-columns: 1fr; }
            .news-grid { grid-template-columns: 1fr; }
            .news-main { grid-template-columns: 1fr; }
            .footer-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 680px) {
            .menu { display:none; }
            .stats { grid-template-columns: 1fr; }
            .court-grid { grid-template-columns: 1fr; }
            .search { width: 100%; min-width: 0; }
            .controls { align-items: stretch; }
            .search-wrap { flex: 1 1 auto; }
            .title-main { font-size: 32px; }
            .panel h3 { font-size: 28px; }
            .news-main-body h4 { font-size: 30px; }
            .welcome-flash { grid-template-columns: 1fr; }
            .nav { padding: 10px 12px; }
            .profile-meta,
            .profile-caret { display: none; }
            .profile-dropdown { width: min(220px, calc(100vw - 28px)); }
        }
    
        #scrollProgress {
            position: fixed;
            top: 0;
            left: 0;
            height: 3px;
            width: 0%;
            z-index: 60;
            background: linear-gradient(90deg, var(--lime), var(--cyan));
            box-shadow: 0 0 12px rgba(34,211,238,.6);
        }

        .reveal {
            opacity: 0;
            transform: translateY(20px) scale(0.985);
            transition: opacity .55s ease, transform .55s ease;
            will-change: opacity, transform;
        }

        .reveal.in-view {
            opacity: 1;
            transform: translateY(0) scale(1);
        }

        .hero-grid {
            transform-style: preserve-3d;
        }

        .preview {
            transition: transform .2s ease, box-shadow .2s ease;
            will-change: transform;
        }

        .court-card,
        .promo-card,
        .news-item,
        .row {
            transition: transform .25s ease, box-shadow .25s ease, opacity .45s ease;
        }

        .court-card:hover,
        .promo-card:hover,
        .news-item:hover,
        .row:hover {
            box-shadow: 0 12px 24px rgba(15, 23, 42, .28);
        }

        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto !important; }
            .reveal,
            .reveal.in-view,
            .preview,
            .court-card,
            .promo-card,
            .news-item,
            .row,
            .welcome-flash {
                transition: none !important;
                transform: none !important;
                opacity: 1 !important;
            }
        }
    </style>
</head>
<body>
    <div id="scrollProgress"></div>
    <div class="container">
        <div class="top">
            <header class="nav">
                <a href="index.php" class="brand">
                    <span class="brand-icon" aria-hidden="true">&#127992;</span>
                    <span class="brand-title"><?= htmlspecialchars($company['short_name']) ?></span>
                </a>
                <nav class="menu">
                    <a href="#lapangan">Lapangan</a>
                    <a href="#pelatih">Pelatih</a>
                    <a href="#teman">Cari Teman</a>
                    <a href="#promo">Promo</a>
                    <a href="#berita">Berita</a>
                </nav>
                <div class="nav-actions">
                    <?php if (!$user): ?>
                        <a href="login.php" class="btn-outline">Login</a>
                    <?php else: ?>
                        <?php
                            $profileName = trim((string) ($user['name'] ?? 'User'));
                            $profileRole = trim((string) ($user['role'] ?? 'akun'));
                            $profileInitials = '';
                            foreach (preg_split('/\s+/', $profileName) ?: [] as $part) {
                                if ($part === '') {
                                    continue;
                                }
                                $profileInitials .= strtoupper(substr($part, 0, 1));
                                if (strlen($profileInitials) >= 2) {
                                    break;
                                }
                            }
                            if ($profileInitials === '') {
                                $profileInitials = 'U';
                            }
                        ?>
                        <div class="profile-menu" id="profileMenu">
                            <button type="button" class="profile-trigger" id="profileTrigger" aria-haspopup="true" aria-expanded="false" aria-controls="profileDropdown">
                                <span class="profile-avatar"><?= htmlspecialchars($profileInitials) ?></span>
                                <span class="profile-meta">
                                    <span class="profile-name"><?= htmlspecialchars($profileName) ?></span>
                                    <span class="profile-role"><?= htmlspecialchars($profileRole) ?></span>
                                </span>
                                <span class="profile-caret" aria-hidden="true"></span>
                            </button>
                            <div class="profile-dropdown" id="profileDropdown" hidden>
                                <div class="profile-dropdown-head">
                                    <strong><?= htmlspecialchars($profileName) ?></strong>
                                    <span><?= htmlspecialchars($profileRole) ?></span>
                                </div>
                                <a href="pages/dashboard.php" class="profile-link">
                                    <span>
                                        Dashboard
                                        <small>Lihat ringkasan akun</small>
                                    </span>
                                </a>
                                <a href="actions/logout.php" class="profile-link logout-link">
                                    <span>
                                        Logout
                                        <small>Keluar dari sesi ini</small>
                                    </span>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </header>
            <?php if ($flash): ?>
                <div id="welcomeFlash" class="welcome-flash <?= $flash['type'] === 'success' ? 'ok' : 'err' ?>">
                    <div class="welcome-flash-icon" aria-hidden="true"><?= $flash['type'] === 'success' ? '&#10024;' : '&#9888;' ?></div>
                    <div class="welcome-flash-copy">
                        <strong><?= $flash['type'] === 'success' ? 'Akun kamu sudah aktif' : 'Ada hal yang perlu dicek' ?></strong>
                        <span><?= htmlspecialchars($flash['message']) ?></span>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <section class="hero">
            <div class="hero-grid">
                <div>
                    <span class="badge"><span aria-hidden="true">&#127934;</span> Venue badminton utama di <?= htmlspecialchars($company['location']) ?></span>
                    <h1><?= htmlspecialchars($company['name']) ?> untuk reservasi lapangan, latihan, dan update aktivitas venue.</h1>
                    <p><?= htmlspecialchars($company['about']) ?> Temukan pilihan court, lihat preview lapangan, atur sesi latihan, dan booking langsung ke venue tanpa alur yang rumit.</p>
                    <div class="cta">
                        <?php if (!$user || $isPelanggan): ?>
                            <a href="<?= htmlspecialchars($reservationEntryUrl) ?>" class="btn">Mulai Reservasi</a>                        <?php else: ?>
                            <a href="pages/dashboard.php" class="btn">Masuk Dashboard</a>
                        <?php endif; ?>
                    </div>
                    <div class="stats">
                        <div class="stat"><strong>120+</strong><span>Lapangan aktif</span></div>
                        <div class="stat"><strong>80+</strong><span>Pelatih terverifikasi</span></div>
                        <div class="stat"><strong>2.4K</strong><span>Pemain komunitas</span></div>
                    </div>
                </div>
                <div class="preview" id="previewCard">
                    <div class="preview-image-wrap">
                        <img id="previewImage" src="<?= htmlspecialchars($courts[0]['image']) ?>" alt="<?= htmlspecialchars($courts[0]['name']) ?>" onerror="this.onerror=null;this.src='assets/images/court-photo-fit.svg';">
                        <div class="overlay"></div>
                        <div class="preview-top">
                            <span class="pill pill-light">Preview Lapangan</span>
                            <span id="previewPrice" class="pill pill-lime"><?= htmlspecialchars($courts[0]['price']) ?></span>
                        </div>
                        <div class="preview-bottom">
                            <h3 id="previewName"><?= htmlspecialchars($courts[0]['name']) ?></h3>
                            <p id="previewLocation">&#11088; <?= htmlspecialchars($courts[0]['rating']) ?></p>
                            <div class="preview-tags" id="previewTags">
                                <?php foreach ($courts[0]['tags'] as $tag): ?>
                                    <span class="tag"><?= htmlspecialchars($tag) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="lapangan" class="block">
            <p class="title-mini">Pilih Lapangan</p>
            <h2 class="title-main">Cari court yang paling cocok buat sesi mainmu</h2>
            <p class="title-sub">Ada preview visual, filter tipe lapangan, lokasi, dan harga agar proses booking terasa cepat dan menyenangkan.</p>

            <div class="controls">
                <div class="search-wrap">
                    <span class="search-icon">S</span>
                    <input id="searchInput" class="search" placeholder="Cari nama venue atau lokasi">
                </div>
                <div class="tabs" id="typeTabs">
                    <button class="tab-btn active" data-type="Semua">Semua</button>
                    <button class="tab-btn" data-type="Indoor Vinyl">Indoor Vinyl</button>
                    <button class="tab-btn" data-type="Karpet Premium">Karpet Premium</button>
                    <button class="tab-btn" data-type="Indoor Basic">Indoor Basic</button>
                </div>
            </div>

            <div class="court-grid" id="courtGrid">
                <?php foreach ($courts as $court): ?>
                    <article class="court-card" data-court='<?= json_encode($court, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>' data-type="<?= htmlspecialchars($court['type']) ?>" data-search="<?= htmlspecialchars(strtolower($court['name'] . ' ' . $court['type'] . ' ' . implode(' ', $court['tags'] ?? []))) ?>">
                        <div class="court-img">
                            <img src="<?= htmlspecialchars($court['image']) ?>" alt="<?= htmlspecialchars($court['name']) ?>" onerror="this.onerror=null;this.src='assets/images/court-photo-fit.svg';">
                            <div class="court-overlay"></div>
                            <div class="court-badges">
                                <span class="pill pill-lime"><?= htmlspecialchars($court['price']) ?></span>
                                <span class="pill" style="background:rgba(0,0,0,.45); color:#fff;"><?= htmlspecialchars($court['type']) ?></span>
                            </div>
                        </div>
                        <div class="court-body">
                            <h4><?= htmlspecialchars($court['name']) ?></h4>
                            <p>&#11088; <?= htmlspecialchars($court['rating']) ?></p>
                            <div class="court-tags">
                                <?php foreach ($court['tags'] as $tag): ?><span><?= htmlspecialchars($tag) ?></span><?php endforeach; ?>
                            </div>
                            <a class="full-btn" href="/pages/court.php?slug=<?= urlencode($court['slug']) ?>" style="display:inline-flex; text-decoration:none;">Lihat Preview & Booking</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="block">
            <div class="split-grid">
                <article id="pelatih" class="panel">
                    <p class="title-mini" style="color:#bef264;">Cari Pelatih</p>
                    <h3>Temukan coach sesuai level bermainmu</h3>
                    <div class="panel-list">
                        <?php foreach ($coaches as $coach): ?>
                            <div class="row">
                                <div>
                                    <h5><?= htmlspecialchars($coach['name']) ?></h5>
                                    <p><?= htmlspecialchars($coach['specialty']) ?> &bull; <?= htmlspecialchars($coach['level']) ?></p>
                                    <p style="color:#bef264;"><?= htmlspecialchars($coach['price']) ?></p>
                                </div>
                                <div class="row-right">
                                    <div class="score">&#11088; <?= htmlspecialchars($coach['rating']) ?></div>
                                    <a class="row-btn" href="/pages/pelatih.php?slug=<?= urlencode($coach['slug']) ?>" style="display:inline-flex; text-decoration:none;">Booking Coach</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </article>

                <article id="teman" class="panel" style="background: linear-gradient(140deg, rgba(132,204,22,.12), rgba(34,211,238,.08));">
                    <p class="title-mini" style="color:#67e8f9;">Cari Teman Main</p>
                    <h3>Matchmaking pemain yang lebih seru dan personal</h3>
                    <div class="panel-list">
                        <?php foreach ($partners as $partner): ?>
                            <div class="row" style="display:block;">
                                <div style="display:flex; justify-content:space-between; gap:8px; align-items:flex-start;">
                                    <div>
                                        <h5><?= htmlspecialchars($partner['name']) ?></h5>
                                        <p><?= htmlspecialchars($partner['city']) ?></p>
                                    </div>
                                    <span class="pill" style="background:rgba(255,255,255,.14); color:#fff;"><?= htmlspecialchars($partner['skill']) ?></span>
                                </div>
                                <p style="margin-top:8px;"><?= htmlspecialchars($partner['play']) ?></p>
                                <a class="row-btn" href="/pages/teman-main.php?slug=<?= urlencode($partner['slug']) ?>" style="margin-top:10px; background:#a3e635; color:#0f172a; border:0; text-decoration:none; display:inline-flex;">Ajak Main</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </article>
            </div>
        </section>

        <section id="promo" class="block">
            <div style="display:flex; justify-content:space-between; gap:10px; align-items:flex-end; flex-wrap:wrap;">
                <div>
                    <p class="title-mini" style="color:#fdba74;">Promosi</p>
                    <h2 class="title-main">Promo yang bikin booking terasa lebih ringan</h2>
                </div>
                <span class="pill" style="background:rgba(255,255,255,.08); color:#cbd5e1;">Promo aktif minggu ini</span>
            </div>
            <div class="promo-grid">
                <?php foreach ($promos as $promo): ?>
                    <a class="promo-card" href="/pages/promo.php?slug=<?= urlencode($promo['slug']) ?>">
                        <span class="promo-badge"><?= htmlspecialchars($promo['badge']) ?></span>
                        <h4><?= htmlspecialchars($promo['title']) ?></h4>
                        <p><?= htmlspecialchars($promo['detail']) ?></p>
                        <span class="promo-code"><?= htmlspecialchars($promo['code']) ?></span>
                        <button class="link-btn" type="button">Klaim promo</button>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <section id="berita" class="block" style="padding-bottom: 60px;">
            <p class="title-mini" style="color:#67e8f9;">Berita & Insight</p>
            <h2 class="title-main">Update badminton yang relevan untuk pemain aktif</h2>
            <p class="title-sub">Bisa diisi artikel tips, highlight turnamen, agenda komunitas, sampai promo event badminton lokal.</p>

            <div class="news-grid">
                <?php if ($featuredArticle): ?>
                    <article class="news-main">
                        <img src="<?= htmlspecialchars($featuredArticle['image']) ?>" alt="<?= htmlspecialchars($featuredArticle['title']) ?>">
                        <div class="news-main-body">
                            <div class="news-main-copy">
                                <span class="pill" style="background:#22d3ee; color:#0f172a;"><?= htmlspecialchars($featuredArticle['category']) ?></span>
                                <h4><?= htmlspecialchars($featuredArticle['title']) ?></h4>
                                <p><?= htmlspecialchars($featuredArticle['excerpt']) ?></p>
                                <p class="news-support">Di <?= htmlspecialchars($company['short_name']) ?>, pendekatan ini bisa diterapkan lewat open play terjadwal, rotasi partner latihan yang lebih jelas, serta update artikel dan agenda komunitas yang membuat pemain punya alasan untuk kembali ke venue secara rutin.</p>
                                <div class="news-actions">
                                    <a class="news-link" href="pages/artikel.php?slug=<?= urlencode($featuredArticle['slug']) ?>">Baca artikel</a>
                                    <a class="news-link alt" href="/pages/artikel-list.php">Semua artikel</a>
                                </div>
                            </div>
                            <div class="news-meta">
                                <span>Date: <?= htmlspecialchars($featuredArticle['publishedAt']) ?></span>
                                <span>Read: <?= htmlspecialchars($featuredArticle['readTime']) ?></span>
                                <span>By: <?= htmlspecialchars($featuredArticle['author']) ?></span>
                            </div>
                        </div>
                    </article>
                <?php endif; ?>

                <aside class="news-side" id="beritaList">
                    <?php foreach ($news as $item): ?>
                        <a class="news-item" href="pages/artikel.php?slug=<?= urlencode($item['slug']) ?>">
                            <div class="cat">&#128240; <?= htmlspecialchars($item['category']) ?></div>
                            <h5><?= htmlspecialchars($item['title']) ?></h5>
                            <p class="excerpt"><?= htmlspecialchars($item['excerpt']) ?></p>
                            <p><?= htmlspecialchars($item['publishedAt']) ?> &bull; <?= htmlspecialchars($item['readTime']) ?></p>
                            <span class="more">Baca selengkapnya <span aria-hidden="true">&rarr;</span></span>
                        </a>
                    <?php endforeach; ?>
                    <a class="news-item" href="/pages/artikel-list.php" style="border-style:dashed; background:rgba(34,211,238,.08);">
                        <div class="cat">&#128214; Portal</div>
                        <h5>Lihat semua artikel dan berita</h5>
                        <p class="excerpt">Buka halaman khusus artikel untuk mencari konten berdasarkan kategori, judul, atau penulis.</p>
                        <span class="more">Masuk ke portal artikel <span aria-hidden="true">&rarr;</span></span>
                    </a>
                </aside>
            </div>
        </section>
        <section class="block" id="venue">
            <p class="title-mini" style="color:#bef264;">Profil Venue</p>
            <h2 class="title-main"><?= htmlspecialchars($company['name']) ?></h2>
            <p class="title-sub">Sistem reservasi ini sekarang diposisikan untuk satu perusahaan utama, yaitu venue badminton di kawasan <?= htmlspecialchars($company['location']) ?>, bukan marketplace multi-venue.</p>
            <div class="split-grid">
                <article class="panel" style="background: linear-gradient(145deg, rgba(34,211,238,.10), rgba(255,255,255,.04));">
                    <p class="title-mini" style="color:#67e8f9;">Informasi Utama</p>
                    <h3 style="margin-top:10px;"><?= htmlspecialchars($company['short_name']) ?></h3>
                    <div class="panel-list">
                        <div class="row" style="display:block;">
                            <h5>Alamat Lengkap</h5>
                            <p><?= htmlspecialchars($company['address']) ?></p>
                        </div>
                        <div class="row" style="display:block;">
                            <h5>Jam Operasional</h5>
                            <p><?= htmlspecialchars($company['hours']) ?></p>
                        </div>
                        <div class="row" style="display:block;">
                            <h5>Kontak Admin</h5>
                            <p>WhatsApp: <?= htmlspecialchars($company['admin_contact']) ?></p>
                            <p>Email: <?= htmlspecialchars($company['email']) ?></p>
                        </div>
                    </div>
                </article>
                <article class="panel" style="background: linear-gradient(145deg, rgba(251,146,60,.10), rgba(255,255,255,.04));">
                    <p class="title-mini" style="color:#fdba74;">Aturan Booking</p>
                    <h3 style="margin-top:10px;">Kebijakan Reservasi Venue</h3>
                    <div class="panel-list">
                        <?php foreach ($company['booking_rules'] as $rule): ?>
                            <div class="row" style="display:block;">
                                <p style="margin:0; color:#e2e8f0; line-height:1.75;"><?= htmlspecialchars($rule) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </article>
            </div>
        </section>
        <footer class="site-footer reveal">
            <div class="footer-grid">
                <div>
                    <h4 class="footer-title"><?= htmlspecialchars($company['short_name']) ?></h4>
                    <p class="footer-copy"><?= htmlspecialchars($company['about']) ?></p>
                </div>
                <div>
                    <h4 class="footer-title">Navigasi</h4>
                    <ul class="footer-list">
                        <li><a href="#lapangan">Lapangan</a></li>
                        <li><a href="#pelatih">Pelatih</a></li>
                        <li><a href="#teman">Cari Teman</a></li>
                        <li><a href="#promo">Promo</a></li>
                        <li><a href="#berita">Berita</a></li>
                        <li><a href="#venue">Profil Venue</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="footer-title">Hubungi Kami</h4>
                    <p class="footer-copy">Alamat: <?= htmlspecialchars($company['address']) ?><br>Email: <?= htmlspecialchars($company['email']) ?><br>WhatsApp: <?= htmlspecialchars($company['admin_contact']) ?><br>Jam operasional: <?= htmlspecialchars($company['hours']) ?></p>
                    <div class="footer-social">
                        <a href="https://www.instagram.com/sonydwikuncorobadmintonhall?utm_source=ig_web_button_share_sheet&igsh=ZDNlZDc0MzIxNw==" target="_blank" rel="noopener noreferrer" aria-label="Instagram">
                            <img src="assets/instagram.png" alt="Instagram" style="width: 35px; height: 20px;">
                        </a>
                        <a href="https://youtube.com/@sonydwikuncoro4296?si=dqAdVRaQP9yJvP2s" target="_blank" rel="noopener noreferrer" aria-label="YouTube">
                            <img src="assets/youtube.png" alt="YouTube" style="width: 35px; height: 40px;">
                        </a>
                        <a href="https://wa.me/6282332328404" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp">
                            <img src="assets/whatsapp.png" alt="WhatsApp" style="width: 24px; height: 24px;">
                        </a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">&copy; <?= date('Y') ?> <?= htmlspecialchars($company['short_name']) ?>. All rights reserved.</div>
        </footer>
    </div>

    <script>
        const courts = <?= json_encode($courts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const previewImage = document.getElementById('previewImage');
        const previewPrice = document.getElementById('previewPrice');
        const previewName = document.getElementById('previewName');
        const previewLocation = document.getElementById('previewLocation');
        const previewTags = document.getElementById('previewTags');

        const searchInput = document.getElementById('searchInput');
        const typeTabs = document.getElementById('typeTabs');
        const cards = Array.from(document.querySelectorAll('.court-card'));
        const progressBar = document.getElementById('scrollProgress');
        const heroGrid = document.querySelector('.hero-grid');
        const previewCard = document.getElementById('previewCard');
        const welcomeFlash = document.getElementById('welcomeFlash');
        const profileMenu = document.getElementById('profileMenu');
        const profileTrigger = document.getElementById('profileTrigger');
        const profileDropdown = document.getElementById('profileDropdown');

        let activeType = 'Semua';

        function closeProfileMenu() {
            if (!profileMenu || !profileTrigger || !profileDropdown) return;
            profileMenu.classList.remove('is-open');
            profileTrigger.setAttribute('aria-expanded', 'false');
            profileDropdown.hidden = true;
        }

        function openProfileMenu() {
            if (!profileMenu || !profileTrigger || !profileDropdown) return;
            profileMenu.classList.add('is-open');
            profileTrigger.setAttribute('aria-expanded', 'true');
            profileDropdown.hidden = false;
        }

        function setPreview(court) {
            previewImage.onerror = () => { previewImage.src = 'assets/images/court-photo-fit.svg'; };
            previewImage.src = court.image;
            previewImage.alt = court.name;
            previewPrice.textContent = court.price;
            previewName.textContent = court.name;
            previewLocation.textContent = `\u2B50 ${court.rating}`;
            previewTags.innerHTML = '';
            (court.tags || []).forEach((tag) => {
                const span = document.createElement('span');
                span.className = 'tag';
                span.textContent = tag;
                previewTags.appendChild(span);
            });
        }

        function applyFilter() {
            const keyword = (searchInput.value || '').toLowerCase().trim();
            cards.forEach((card) => {
                const type = card.dataset.type || '';
                const search = card.dataset.search || '';
                const matchType = activeType === 'Semua' || type === activeType;
                const matchSearch = keyword === '' || search.includes(keyword);
                card.style.display = matchType && matchSearch ? '' : 'none';
            });
        }

        function updateScrollProgress() {
            const scrollTop = window.scrollY || document.documentElement.scrollTop;
            const maxScroll = document.documentElement.scrollHeight - window.innerHeight;
            const progress = maxScroll > 0 ? (scrollTop / maxScroll) * 100 : 0;
            progressBar.style.width = `${Math.min(100, Math.max(0, progress))}%`;
        }

        function animateCount(el) {
            if (el.dataset.counted === '1') return;
            el.dataset.counted = '1';
            const raw = (el.dataset.target || el.textContent || '').trim();

            let end = 0;
            let suffix = '';
            if (/K$/i.test(raw)) {
                end = parseFloat(raw.replace(/[^0-9.]/g, '')) || 0;
                suffix = 'K';
            } else {
                end = parseInt(raw.replace(/[^0-9]/g, ''), 10) || 0;
                suffix = raw.includes('+') ? '+' : '';
            }

            const start = performance.now();
            const duration = 900;

            function tick(now) {
                const p = Math.min(1, (now - start) / duration);
                const eased = 1 - Math.pow(1 - p, 3);
                const val = end * eased;
                if (suffix === 'K') {
                    el.textContent = `${val.toFixed(1)}K`;
                } else {
                    el.textContent = `${Math.round(val)}${suffix}`;
                }
                if (p < 1) requestAnimationFrame(tick);
            }

            requestAnimationFrame(tick);
        }

        cards.forEach((card) => {
            card.addEventListener('click', () => {
                try {
                    const court = JSON.parse(card.dataset.court || '{}');
                    if (court && court.name) setPreview(court);
                } catch (e) {}
            });
        });

        searchInput.addEventListener('input', applyFilter);
        typeTabs.querySelectorAll('.tab-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                activeType = btn.dataset.type || 'Semua';
                typeTabs.querySelectorAll('.tab-btn').forEach((b) => b.classList.remove('active'));
                btn.classList.add('active');
                applyFilter();
            });
        });

        document.querySelectorAll('.menu a[href^="#"]').forEach((a) => {
            a.addEventListener('click', (e) => {
                e.preventDefault();
                const target = document.querySelector(a.getAttribute('href'));
                if (!target) return;
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });

        if (heroGrid && previewCard) {
            heroGrid.addEventListener('mousemove', (e) => {
                const rect = heroGrid.getBoundingClientRect();
                const x = (e.clientX - rect.left) / rect.width;
                const y = (e.clientY - rect.top) / rect.height;
                const rx = (0.5 - y) * 6;
                const ry = (x - 0.5) * 8;
                previewCard.style.transform = `perspective(900px) rotateX(${rx}deg) rotateY(${ry}deg)`;
            });
            heroGrid.addEventListener('mouseleave', () => {
                previewCard.style.transform = 'perspective(900px) rotateX(0deg) rotateY(0deg)';
            });
        }

        if (profileMenu && profileTrigger && profileDropdown) {
            profileTrigger.addEventListener('click', (event) => {
                event.stopPropagation();
                if (profileMenu.classList.contains('is-open')) {
                    closeProfileMenu();
                } else {
                    openProfileMenu();
                }
            });

            document.addEventListener('click', (event) => {
                if (!profileMenu.contains(event.target)) {
                    closeProfileMenu();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeProfileMenu();
                }
            });
        }

        const revealTargets = document.querySelectorAll('.hero, section.block, .court-card, .panel, .promo-card, .news-item, .news-main, .preview, .stat, .row, .site-footer');
        revealTargets.forEach((el, idx) => {
            el.classList.add('reveal');
            if (el.classList.contains('court-card') || el.classList.contains('promo-card') || el.classList.contains('news-item') || el.classList.contains('row')) {
                el.style.transitionDelay = `${(idx % 6) * 55}ms`;
            }
        });

        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('in-view');
                if (entry.target.classList.contains('stat')) {
                    const countEl = entry.target.querySelector('strong');
                    if (countEl) {
                        if (!countEl.dataset.target) countEl.dataset.target = countEl.textContent.trim();
                        animateCount(countEl);
                    }
                }
                revealObserver.unobserve(entry.target);
            });
        }, { threshold: 0.12 });

        revealTargets.forEach((el) => revealObserver.observe(el));

        window.addEventListener('scroll', updateScrollProgress, { passive: true });
        updateScrollProgress();

        if (welcomeFlash && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            window.setTimeout(() => {
                welcomeFlash.classList.add('is-hidden');
            }, 4200);
        }
    </script>
</body>
</html>

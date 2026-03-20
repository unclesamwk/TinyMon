<?php
$appVersion = file_exists(__DIR__ . "/../../../VERSION")
    ? trim(file_get_contents(__DIR__ . "/../../../VERSION"))
    : "dev";
$cacheBuster = file_exists(__DIR__ . "/../../../VERSION")
    ? $appVersion
    : time();
$v = "?v=" . $cacheBuster;
$isDebug = !empty($debug);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <script>
        (function() {
            var tp = localStorage.getItem("themePreference") || "auto";
            var theme = tp;
            if (tp === "auto") {
                var ua = navigator.userAgent || "";
                theme = /iphone|ipad|ipod|macintosh/i.test(ua) ? "ios" : "md";
            }
            document.documentElement.classList.add(theme);
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, minimum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="TinyMon">
    <meta name="theme-color" content="#007aff">
    <title>TinyMon</title>
    <link rel="manifest" href="/backend/manifest.json">
    <link rel="icon" type="image/svg+xml" href="/assets/images/logo.svg">
    <link rel="apple-touch-icon" href="/assets/images/logo.svg">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/framework7-icons@5.0.5/css/framework7-icons.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/framework7@9.0.2/framework7-bundle.min.css">
    <style>
        /* Self-hosted fonts */
        @font-face {
            font-family: 'JetBrains Mono';
            src: url('/assets/fonts/JetBrainsMono-Regular.woff2') format('woff2');
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }
        @font-face {
            font-family: 'JetBrains Mono';
            src: url('/assets/fonts/JetBrainsMono-Medium.woff2') format('woff2');
            font-weight: 500;
            font-style: normal;
            font-display: swap;
        }
        @font-face {
            font-family: 'DM Sans';
            src: url('/assets/fonts/DMSans-Regular.woff2') format('woff2');
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }
        @font-face {
            font-family: 'DM Sans';
            src: url('/assets/fonts/DMSans-Medium.woff2') format('woff2');
            font-weight: 500;
            font-style: normal;
            font-display: swap;
        }
        @font-face {
            font-family: 'DM Sans';
            src: url('/assets/fonts/DMSans-SemiBold.woff2') format('woff2');
            font-weight: 600;
            font-style: normal;
            font-display: swap;
        }
        @font-face {
            font-family: 'DM Sans';
            src: url('/assets/fonts/DMSans-Bold.woff2') format('woff2');
            font-weight: 700;
            font-style: normal;
            font-display: swap;
        }

        /* Dashboard Pro — CSS Custom Properties */
        :root {
            --tm-bg-primary: #f4f4f5;
            --tm-bg-card: #ffffff;
            --tm-bg-card-header: #fafafa;
            --tm-border: #d4d4d8;
            --tm-text-primary: #18181b;
            --tm-text-secondary: #71717a;
            --tm-text-mono: #52525b;
            --tm-font-mono: 'JetBrains Mono', monospace;
            --tm-font-ui: 'DM Sans', sans-serif;
            --tm-ok: #22c55e;
            --tm-warning: #eab308;
            --tm-critical: #ef4444;
            --tm-unknown: #a1a1aa;
            --f7-page-bg-color: var(--tm-bg-primary);
            --f7-navbar-bg-color: var(--tm-bg-card);
            --f7-toolbar-bg-color: var(--tm-bg-card);
        }

        /* Dark mode */
        .ios .dark, .ios.dark,
        .md .dark, .md.dark {
            --tm-bg-primary: #0c0c0c;
            --tm-bg-card: #141414;
            --tm-bg-card-header: #1a1a1a;
            --tm-border: #1e1e1e;
            --tm-text-primary: #e0e0e0;
            --tm-text-secondary: #555;
            --tm-text-mono: #888;
            --tm-unknown: #555;
            --f7-page-bg-color: #0c0c0c;
            --f7-bars-bg-color: #141414;
            --f7-navbar-bg-color: #141414;
            --f7-toolbar-bg-color: #141414;
            --f7-list-bg-color: #141414;
            --f7-list-strong-bg-color: #141414;
            --f7-list-group-title-bg-color: #141414;
            --f7-block-strong-bg-color: #141414;
            --f7-glass-bg-color: #141414cc;
            --f7-input-bg-color: transparent;
            --f7-list-button-bg-color: #141414;
        }

        /* Hide conditional icons until F7 sets theme class */
        .if-ios, .if-md { display: none !important; }
        .ios .if-ios, .md .if-md { display: inherit !important; }
        .ios .if-md, .md .if-ios { display: none !important; }
        .navbar .left, .navbar .right { flex-shrink: 0; }
        .navbar .right a + a { margin-left: 4px; }

        /* Topic groups */
        .topic-group {
            border-radius: 10px;
            overflow: hidden;
            margin: 0.5rem 0.75rem;
            border: 1px solid var(--tm-border);
            background: var(--tm-bg-card);
        }
        .topic-group .list { margin: 0; }
        .topic-group .list ul {
            background: transparent;
            padding: 0;
        }
        .topic-group .list ul::before,
        .topic-group .list ul::after { display: none; }
        .topic-group .accordion-item-content .list { margin: 0; }
        .topic-group .accordion-item-content .list ul { padding: 0; }
        .topic-header {
            background: var(--tm-bg-card-header);
        }
        .topic-header .item-inner::after { display: none; }
        .status-dots { display: inline-flex; gap: 6px; font-size: 0.7rem; margin-left: 0.5rem; font-family: var(--tm-font-mono); }
        .status-dots span { display: inline-flex; align-items: center; gap: 1px; }
        .host-checks-sublist {
            margin-left: 1.5rem;
            border-left: 2px solid var(--tm-border);
            background: var(--tm-bg-card-header);
        }
        .host-checks-sublist .list ul::before,
        .host-checks-sublist .list ul::after { display: none; }
        .host-checks-sublist .list ul { background: transparent; }
        .topic-group .accordion-item { border-left: none; }
        .topic-group .item-inner::after {
            left: 0 !important;
        }

        /* Summary tiles */
        .summary-tiles {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.4rem;
            padding: 0.75rem;
        }
        @media (max-width: 399px) {
            .summary-tiles { grid-template-columns: repeat(2, 1fr); }
        }
        .summary-tile {
            background: var(--tm-bg-card);
            border: 1px solid var(--tm-border);
            border-radius: 8px;
            padding: 0.5rem;
            text-align: center;
            transition: border-color 0.15s;
        }

        /* Sparklines */
        .sparkline {
            display: inline-flex;
            align-items: flex-end;
            gap: 1px;
            height: 16px;
            vertical-align: middle;
        }
        .sparkline .bar {
            width: 2px;
            border-radius: 0.5px;
            min-height: 1px;
        }
        @media (max-width: 479px) {
            .sparkline { display: none; }
        }

        /* Value badge */
        .value-badge {
            font-family: var(--tm-font-mono);
            font-size: 0.6rem;
            color: var(--tm-text-mono);
            background: var(--tm-bg-card-header);
            padding: 0.1rem 0.35rem;
            border-radius: 4px;
            border: 1px solid var(--tm-border);
            white-space: nowrap;
        }

        /* Uptime 24h */
        .uptime-24h {
            display: flex;
            gap: 1px;
            height: 8px;
        }
        .uptime-24h .uptime-block {
            flex: 1;
            border-radius: 1px;
            min-width: 3px;
        }
    </style>
</head>
<body>
    <div id="app">
        <div class="view view-main view-init safe-areas" data-url="/">
            <div class="toolbar toolbar-bottom" style="<?= $isDebug
                ? "background:#f0ad4e;"
                : "" ?>">
                <div class="toolbar-inner" style="justify-content:center; gap:0.4rem;">
                    <?php if (
                        $isDebug
                    ): ?><span style="font-size:1rem; color:#000;">&#x26A0;</span><?php endif; ?>
                    <span style="font-size:0.7rem; color:<?= $isDebug
                        ? "#000"
                        : "gray" ?>;"><?= htmlspecialchars(
    $appVersion,
) ?></span>
                    <?php if (
                        $isDebug
                    ): ?><span style="font-size:0.7rem; color:#000; font-weight:bold;">Debug</span><?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        var APP_DEBUG = <?= $isDebug ? "true" : "false" ?>;
        var APP_VERSION = <?= json_encode((string) $appVersion) ?>;
        var APP_CACHE_BUSTER = <?= json_encode((string) $cacheBuster) ?>;
        var CSRF_TOKEN = <?= json_encode(\App\services\CsrfService::token()) ?>;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/framework7@9.0.2/framework7-bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    <script src="/assets/js/backend-app.js<?= $v ?>"></script>
    <script>
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js<?= $v ?>');
    }
    </script>
</body>
</html>

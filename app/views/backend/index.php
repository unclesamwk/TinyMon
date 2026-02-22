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
        /* Hide conditional icons until F7 sets theme class */
        .if-ios, .if-md { display: none !important; }
        .ios .if-ios, .md .if-md { display: inherit !important; }
        .ios .if-md, .md .if-ios { display: none !important; }
        .topic-group {
            border-radius: 10px;
            overflow: hidden;
            margin: 0.5rem 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            background: var(--f7-list-bg-color, #fff);
        }
        .ios .dark .topic-group, .ios.dark .topic-group,
        .md .dark .topic-group, .md.dark .topic-group {
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
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
            background: rgba(0,0,0,0.03);
        }
        .ios .dark .topic-header, .ios.dark .topic-header,
        .md .dark .topic-header, .md.dark .topic-header {
            background: rgba(255,255,255,0.05);
        }
        .topic-header .item-inner::after { display: none; }
        .status-dots { display: inline-flex; gap: 6px; font-size: 0.7rem; margin-left: 0.5rem; }
        .status-dots span { display: inline-flex; align-items: center; gap: 1px; }
        .host-checks-sublist {
            margin-left: 1.5rem;
            border-left: 2px solid rgba(0,0,0,0.08);
            background: rgba(0,0,0,0.02);
        }
        .host-checks-sublist .list ul::before,
        .host-checks-sublist .list ul::after { display: none; }
        .host-checks-sublist .list ul { background: transparent; }
        .ios .dark .host-checks-sublist, .ios.dark .host-checks-sublist,
        .md .dark .host-checks-sublist, .md.dark .host-checks-sublist {
            border-left-color: rgba(255,255,255,0.1);
            background: rgba(255,255,255,0.03);
        }
        .topic-group .accordion-item { border-left: none; }
        .topic-group .item-inner::after {
            left: 0 !important;
        }
        .ios .dark, .ios.dark,
        .md .dark, .md.dark {
            --f7-page-bg-color: #1c1c1e;
            --f7-bars-bg-color: #2c2c2e;
            --f7-navbar-bg-color: #2c2c2e;
            --f7-toolbar-bg-color: #2c2c2e;
            --f7-list-bg-color: #2c2c2e;
            --f7-list-strong-bg-color: #2c2c2e;
            --f7-list-group-title-bg-color: #2c2c2e;
            --f7-block-strong-bg-color: #2c2c2e;
            --f7-glass-bg-color: #2c2c2ecc;
            --f7-input-bg-color: transparent;
            --f7-list-button-bg-color: #2c2c2e;
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

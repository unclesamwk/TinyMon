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
    <link rel="apple-touch-icon" href="/assets/images/apple-touch-icon.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/framework7-icons@5.0.5/css/framework7-icons.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/framework7@9.0.2/framework7-bundle.min.css">
    <style>
        /* Hide conditional icons until F7 sets theme class */
        .if-ios, .if-md { display: none !important; }
        .ios .if-ios, .md .if-md { display: inherit !important; }
        .ios .if-md, .md .if-ios { display: none !important; }
        .topic-header {
            background: #e8e8ed;
        }
        .ios .dark .topic-header, .ios.dark .topic-header,
        .md .dark .topic-header, .md.dark .topic-header {
            background: #3a3a3c;
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

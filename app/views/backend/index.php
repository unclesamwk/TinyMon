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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, minimum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="MiniMon">
    <meta name="theme-color" content="#007aff">
    <title>MiniMon</title>
    <link rel="manifest" href="/backend/manifest.json">
    <link rel="icon" type="image/svg+xml" href="/assets/images/logo.svg">
    <link rel="apple-touch-icon" href="/assets/images/logo.svg">
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/framework7@9.0.2/framework7-bundle.min.css">
    <style>
        .ios .dark, .ios.dark {
            --f7-page-bg-color: #1c1c1e;
            --f7-bars-bg-color: #2c2c2e;
            --f7-navbar-bg-color: #2c2c2e;
            --f7-toolbar-bg-color: #2c2c2e;
            --f7-list-strong-bg-color: #2c2c2e;
            --f7-list-group-title-bg-color: #2c2c2e;
            --f7-block-strong-bg-color: #2c2c2e;
            --f7-glass-bg-color: #2c2c2ecc;
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
                    ): ?><i class="icon material-icons" style="font-size:1rem; color:#000;">warning</i><?php endif; ?>
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
        var APP_VERSION = <?= json_encode((string) $cacheBuster) ?>;
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

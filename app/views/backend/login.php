<?php
$loginTitle = "TinyMon";
$lang = $_COOKIE["lang"] ?? "en";
$appVersion = file_exists(__DIR__ . "/../../../VERSION")
    ? trim(file_get_contents(__DIR__ . "/../../../VERSION"))
    : "dev";
?>
<!DOCTYPE html>
<html lang="<?= $lang === "en" ? "en" : "de" ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="<?= htmlspecialchars(
        $loginTitle,
    ) ?>">
    <meta name="theme-color" content="#007aff">
    <title><?= htmlspecialchars($loginTitle) ?></title>
    <link rel="manifest" href="/backend/manifest.json">
    <link rel="icon" type="image/svg+xml" href="/assets/images/logo.svg">
    <link rel="apple-touch-icon" href="/assets/images/logo.svg">
    <style>
        @font-face { font-family: 'JetBrains Mono'; src: url('/assets/fonts/JetBrainsMono-Regular.woff2') format('woff2'); font-weight: 400; font-style: normal; font-display: swap; }
        @font-face { font-family: 'DM Sans'; src: url('/assets/fonts/DMSans-Regular.woff2') format('woff2'); font-weight: 400; font-style: normal; font-display: swap; }
        @font-face { font-family: 'DM Sans'; src: url('/assets/fonts/DMSans-Medium.woff2') format('woff2'); font-weight: 500; font-style: normal; font-display: swap; }
        @font-face { font-family: 'DM Sans'; src: url('/assets/fonts/DMSans-Bold.woff2') format('woff2'); font-weight: 700; font-style: normal; font-display: swap; }
        * { box-sizing: border-box; }
        body { font-family: 'DM Sans', sans-serif; margin: 0; background: #f4f4f5; color: #18181b; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .login-card { background: #fff; border: 1px solid #d4d4d8; border-radius: 10px; padding: 2rem; width: 100%; max-width: 360px; box-shadow: none; }
        .login-card h1 { margin: 0 0 1.5rem; font-size: 1.4rem; text-align: center; color: inherit; font-family: 'DM Sans', sans-serif; font-weight: 700; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.3rem; font-weight: 500; font-size: 0.95rem; }
        .form-group input { width: 100%; padding: 0.6rem 0.8rem; border: 1px solid #d4d4d8; border-radius: 6px; font-size: 1rem; background: #fff; color: #18181b; font-family: 'DM Sans', sans-serif; }
        .form-group input:focus { outline: none; border-color: #22c55e; box-shadow: 0 0 0 2px rgba(34,197,94,0.15); }
        .btn { display: block; width: 100%; padding: 0.75rem; background: #22c55e; color: white; border: none; border-radius: 8px; font-size: 1rem; font-family: 'DM Sans', sans-serif; font-weight: 600; cursor: pointer; }
        .btn:hover { background: #16a34a; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 0.6rem 0.8rem; border-radius: 4px; margin-bottom: 1rem; font-size: 0.9rem; }
        .version-label { font-family: 'JetBrains Mono', monospace; font-size: 0.65rem; color: #a1a1aa; }
        @media (prefers-color-scheme: dark) {
            body { background: #0c0c0c; color: #e0e0e0; }
            .login-card { background: #141414; border-color: #1e1e1e; }
            .form-group input { background: #1a1a1a; color: #e0e0e0; border-color: #1e1e1e; }
            .form-group input:focus { border-color: #22c55e; box-shadow: 0 0 0 2px rgba(34,197,94,0.2); }
            .form-group input[readonly] { background: #1a1a1a; }
            .alert-error { background: #1a1214; color: #ef4444; border-color: #2e1418; }
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div style="text-align:center; margin-bottom:1rem;">
            <img src="/assets/images/logo.svg" alt="TinyMon" style="width:80px; height:80px;">
        </div>
        <h1><?= htmlspecialchars($loginTitle) ?></h1>
        <?php if (!empty($error)): ?>
            <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST" action="/backend/login" autocomplete="on">
            <?= \App\services\CsrfService::field() ?>
            <input type="text" name="username" autocomplete="username" value="admin" style="display:none;">
            <div class="form-group">
                <label for="password"><?= $lang === "en"
                    ? "Password"
                    : "Passwort" ?></label>
                <input type="password" id="password" name="password" autocomplete="current-password" required autofocus>
            </div>
            <button type="submit" class="btn"><?= $lang === "en"
                ? "Log in"
                : "Anmelden" ?></button>
        </form>
        <div class="version-label" style="text-align:center; margin-top:1rem;"><?= htmlspecialchars(
            $appVersion,
        ) ?></div>
    </div>
    <div id="ptr-spinner" style="display:none; position:fixed; left:50%; transform:translateX(-50%); z-index:9999;">
        <svg width="24" height="24" viewBox="0 0 24 24" style="animation:ptr-spin 0.8s linear infinite;">
            <circle cx="12" cy="12" r="10" fill="none" stroke="#999" stroke-width="2.5" stroke-dasharray="47" stroke-dashoffset="15" stroke-linecap="round"/>
        </svg>
    </div>
    <style>@keyframes ptr-spin { to { transform: rotate(360deg); } }</style>
    <script>
    (function() {
        var startY = 0;
        var spinner = document.getElementById('ptr-spinner');
        document.addEventListener('touchstart', function(e) {
            startY = e.touches[0].pageY;
        }, { passive: true });
        document.addEventListener('touchmove', function(e) {
            var dy = e.touches[0].pageY - startY;
            if (dy > 40) {
                spinner.style.top = Math.min(dy - 30, 120) + 'px';
                spinner.style.display = 'block';
            } else {
                spinner.style.display = 'none';
            }
        }, { passive: true });
        document.addEventListener('touchend', function(e) {
            var dy = e.changedTouches[0].pageY - startY;
            if (dy > 80) {
                spinner.style.display = 'block';
                window.location.href = window.location.pathname + '?r=' + Date.now();
            } else {
                spinner.style.display = 'none';
            }
        }, { passive: true });
    })();
    </script>
</body>
</html>

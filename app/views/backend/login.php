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
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 0; background: #f5f5f5; color: #333; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .login-card { background: white; border-radius: 8px; padding: 2rem; width: 100%; max-width: 360px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .login-card h1 { margin: 0 0 1.5rem; font-size: 1.4rem; text-align: center; color: inherit; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.3rem; font-weight: 500; font-size: 0.95rem; }
        .form-group input { width: 100%; padding: 0.6rem 0.8rem; border: 1px solid #ccc; border-radius: 4px; font-size: 1rem; background: white; color: #333; }
        .form-group input:focus { outline: none; border-color: #007aff; box-shadow: 0 0 0 2px rgba(0,122,255,0.2); }
        .btn { display: block; width: 100%; padding: 0.75rem; background: #007aff; color: white; border: none; border-radius: 6px; font-size: 1rem; font-weight: 500; cursor: pointer; }
        .btn:hover { background: #0056b3; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 0.6rem 0.8rem; border-radius: 4px; margin-bottom: 1rem; font-size: 0.9rem; }
        @media (prefers-color-scheme: dark) {
            body { background: #1c1c1e; color: #f2f2f7; }
            .login-card { background: #2c2c2e; box-shadow: 0 2px 8px rgba(0,0,0,0.4); }
            .form-group input { background: #3a3a3c; color: #f2f2f7; border-color: #555; }
            .form-group input[readonly] { background: #2a2a2c; }
            .alert-error { background: #3a1c1e; color: #ff6b6b; border-color: #5a2c2e; }
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
            <div class="form-group">
                <label for="username"><?= $lang === "en"
                    ? "User"
                    : "Benutzer" ?></label>
                <input type="text" id="username" name="username" autocomplete="username" value="admin" readonly style="background:#eee; cursor:default;">
            </div>
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
        <div style="text-align:center; margin-top:1rem; font-size:0.7rem; color:gray;"><?= htmlspecialchars(
            $appVersion,
        ) ?></div>
    </div>
    <div id="ptr-spinner" style="display:none; position:fixed; top:16px; left:50%; transform:translateX(-50%); z-index:9999;">
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
            spinner.style.display = dy > 40 ? 'block' : 'none';
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

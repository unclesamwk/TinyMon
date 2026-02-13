<?php $loginTitle = "MiniMon"; ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, minimum-scale=1, user-scalable=no, viewport-fit=cover">
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
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 0; background: #f5f5f5; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .login-card { background: white; border-radius: 8px; padding: 2rem; width: 100%; max-width: 360px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .login-card h1 { margin: 0 0 1.5rem; font-size: 1.4rem; text-align: center; color: #333; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.3rem; font-weight: 500; font-size: 0.95rem; }
        .form-group input { width: 100%; padding: 0.6rem 0.8rem; border: 1px solid #ccc; border-radius: 4px; font-size: 1rem; }
        .form-group input:focus { outline: none; border-color: #007aff; box-shadow: 0 0 0 2px rgba(0,122,255,0.2); }
        .btn { display: block; width: 100%; padding: 0.75rem; background: #007aff; color: white; border: none; border-radius: 6px; font-size: 1rem; font-weight: 500; cursor: pointer; }
        .btn:hover { background: #0056b3; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 0.6rem 0.8rem; border-radius: 4px; margin-bottom: 1rem; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="login-card">
        <div style="text-align:center; margin-bottom:1rem;">
            <img src="/assets/images/logo.svg" alt="MiniMon" style="width:80px; height:80px;">
        </div>
        <h1><?= htmlspecialchars($loginTitle) ?></h1>
        <?php if (!empty($error)): ?>
            <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST" action="/backend/login">
            <?= \App\services\CsrfService::field() ?>
            <div class="form-group">
                <label for="password">Passwort</label>
                <input type="password" id="password" name="password" required autofocus>
            </div>
            <button type="submit" class="btn">Anmelden</button>
        </form>
    </div>
</body>
</html>

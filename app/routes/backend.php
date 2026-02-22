<?php

use App\controllers\BackendController;

Flight::route('GET /backend', [BackendController::class, 'index']);
Flight::route('GET /backend/manifest.json', [BackendController::class, 'manifest']);
Flight::route('GET /backend/login', [BackendController::class, 'showLogin']);
Flight::route('POST /backend/login', [BackendController::class, 'submitLogin']);
Flight::route('POST /backend/logout', [BackendController::class, 'logout']);

// SPA catch-all: serve index for all other /backend/* paths
Flight::route('GET /backend/*', [BackendController::class, 'index']);

// Redirect root to backend
Flight::route('GET /', function () {
    Flight::redirect('/backend');
});

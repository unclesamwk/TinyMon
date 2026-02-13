<?php

Flight::before('start', function () {
    $config = Flight::get('config');
    $request = Flight::request();

    if ($config['debug']) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    if (str_starts_with($request->url, '/api/') && $request->method === 'OPTIONS') {
        Flight::halt(200);
    }
});

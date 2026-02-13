<?php

Flight::before('start', function () {
    $request = Flight::request();

    if (!str_starts_with($request->url, '/api/push/')) {
        return;
    }

    if ($request->method === 'OPTIONS') {
        return;
    }

    $config = Flight::get('config');
    $apiKey = $config['push_api_key'] ?? '';

    if ($apiKey === '') {
        Flight::response()->header('Content-Type', 'application/json');
        Flight::halt(403, json_encode(['error' => 'Push API is disabled. Set PUSH_API_KEY in .env']));
    }

    $authHeader = $request->getHeader('Authorization') ?? '';
    $token = '';
    if (str_starts_with($authHeader, 'Bearer ')) {
        $token = substr($authHeader, 7);
    }

    if ($token === '' || !hash_equals($apiKey, $token)) {
        Flight::response()->header('Content-Type', 'application/json');
        Flight::halt(401, json_encode(['error' => 'Invalid or missing API key']));
    }
});

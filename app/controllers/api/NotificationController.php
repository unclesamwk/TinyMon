<?php

namespace App\controllers\api;

use App\services\WebPushService;
use App\services\Database;
use Flight;
use OpenApi\Attributes as OA;

class NotificationController
{
    #[
        OA\Get(
            path: "/api/notifications/vapid-key",
            summary: "VAPID Public Key abrufen",
            description: "Liefert den VAPID Public Key fuer die Browser Push-Subscription.",
            tags: ["Notifications"],
            security: [["sessionAuth" => []]],
            responses: [
                new OA\Response(
                    response: 200,
                    description: "VAPID Public Key",
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(
                                property: "publicKey",
                                type: "string",
                            ),
                        ],
                    ),
                ),
            ],
        ),
    ]
    public static function vapidKey(): void
    {
        $db = Flight::db();
        $config = Flight::get("config");
        $service = new WebPushService($db, $config);
        Flight::json(["publicKey" => $service->getVapidPublicKey()]);
    }

    #[
        OA\Post(
            path: "/api/notifications/subscribe",
            summary: "Push-Subscription registrieren",
            description: "Speichert eine Browser Push-Subscription fuer Benachrichtigungen.",
            tags: ["Notifications"],
            security: [["sessionAuth" => []]],
            requestBody: new OA\RequestBody(
                required: true,
                content: new OA\JsonContent(
                    required: ["endpoint", "keys"],
                    properties: [
                        new OA\Property(property: "endpoint", type: "string"),
                        new OA\Property(
                            property: "keys",
                            type: "object",
                            properties: [
                                new OA\Property(
                                    property: "p256dh",
                                    type: "string",
                                ),
                                new OA\Property(
                                    property: "auth",
                                    type: "string",
                                ),
                            ],
                        ),
                    ],
                ),
            ),
            responses: [
                new OA\Response(
                    response: 200,
                    description: "Subscription gespeichert",
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(
                                property: "success",
                                type: "boolean",
                            ),
                        ],
                    ),
                ),
                new OA\Response(
                    response: 400,
                    description: "Ungueltige Daten",
                    content: new OA\JsonContent(
                        ref: "#/components/schemas/Error",
                    ),
                ),
            ],
        ),
    ]
    public static function subscribe(): void
    {
        $data = Flight::request()->data;
        $endpoint = $data->endpoint ?? "";
        $keys = $data->keys ?? [];

        if (is_object($keys)) {
            $keys = (array) $keys;
        }

        $p256dh = $keys["p256dh"] ?? "";
        $auth = $keys["auth"] ?? "";

        if ($endpoint === "" || $p256dh === "" || $auth === "") {
            Flight::halt(
                400,
                json_encode([
                    "error" =>
                        "endpoint, keys.p256dh, and keys.auth are required",
                ]),
            );
            return;
        }

        $db = Flight::db();
        $config = Flight::get("config");
        $service = new WebPushService($db, $config);
        $userAgent = Flight::request()->getHeader("User-Agent") ?? "";
        $service->subscribe($endpoint, $p256dh, $auth, $userAgent);

        Flight::json(["success" => true]);
    }

    #[
        OA\Post(
            path: "/api/notifications/unsubscribe",
            summary: "Push-Subscription entfernen",
            tags: ["Notifications"],
            security: [["sessionAuth" => []]],
            requestBody: new OA\RequestBody(
                required: true,
                content: new OA\JsonContent(
                    required: ["endpoint"],
                    properties: [
                        new OA\Property(property: "endpoint", type: "string"),
                    ],
                ),
            ),
            responses: [
                new OA\Response(
                    response: 200,
                    description: "Subscription entfernt",
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(
                                property: "success",
                                type: "boolean",
                            ),
                        ],
                    ),
                ),
            ],
        ),
    ]
    public static function unsubscribe(): void
    {
        $data = Flight::request()->data;
        $endpoint = $data->endpoint ?? "";

        if ($endpoint === "") {
            Flight::halt(400, json_encode(["error" => "endpoint is required"]));
            return;
        }

        $db = Flight::db();
        $config = Flight::get("config");
        $service = new WebPushService($db, $config);
        $service->unsubscribe($endpoint);

        Flight::json(["success" => true]);
    }

    #[
        OA\Get(
            path: "/api/notifications/status",
            summary: "Subscription-Status pruefen",
            description: "Prueft ob ein bestimmter Endpoint als Push-Subscription registriert ist.",
            tags: ["Notifications"],
            security: [["sessionAuth" => []]],
            parameters: [
                new OA\Parameter(
                    name: "endpoint",
                    in: "query",
                    required: true,
                    schema: new OA\Schema(type: "string"),
                ),
            ],
            responses: [
                new OA\Response(
                    response: 200,
                    description: "Subscription-Status",
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(
                                property: "subscribed",
                                type: "boolean",
                            ),
                        ],
                    ),
                ),
            ],
        ),
    ]
    public static function status(): void
    {
        $endpoint = Flight::request()->query->endpoint ?? "";

        $db = Flight::db();
        $config = Flight::get("config");
        $service = new WebPushService($db, $config);

        Flight::json(["subscribed" => $service->isSubscribed($endpoint)]);
    }

    #[
        OA\Post(
            path: "/api/notifications/test",
            summary: "Test-Notification senden",
            description: "Sendet eine Test-Push-Notification an alle registrierten Subscriptions.",
            tags: ["Notifications"],
            security: [["sessionAuth" => []]],
            responses: [
                new OA\Response(
                    response: 200,
                    description: "Test-Ergebnis",
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: "sent", type: "integer"),
                            new OA\Property(
                                property: "failed",
                                type: "integer",
                            ),
                        ],
                    ),
                ),
            ],
        ),
    ]
    public static function test(): void
    {
        $db = Flight::db();
        $config = Flight::get("config");
        $service = new WebPushService($db, $config);
        $result = $service->sendTest();
        Flight::json($result);
    }
}

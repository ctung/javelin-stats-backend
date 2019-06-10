<?php

use Slim\App;
//use Tuupola\Middleware\CorsMiddleware;

return function (App $app) {
    // e.g: $app->add(new \Slim\Csrf\Guard);
    $app->add(new Tuupola\Middleware\CorsMiddleware([
        "origin" => ["http://localhost:4200","https://javelin-stats.com"],
        "methods" => ["GET", "POST", "DELETE"],
        "headers.allow" => ["Authorization", "Content-Type"],
        "headers.expose" => [],
        "credentials" => true,
        "cache" => 86400
    ]));
};

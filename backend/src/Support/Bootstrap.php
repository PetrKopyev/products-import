<?php

declare(strict_types=1);

namespace App\Support;

use Psr\Container\ContainerInterface;
use Slim\App;
use Slim\Factory\AppFactory;

/**
 * Сборка Slim-приложения (используется и web-входом, и тестами).
 */
final class Bootstrap
{
    /** @param array<string, mixed> $containerOverrides подмены определений для тестов */
    public static function createApp(array $containerOverrides = []): App
    {
        /** @var callable(array<string, mixed>): ContainerInterface $factory */
        $factory = require __DIR__ . '/../../config/container.php';
        $container = $factory($containerOverrides);

        AppFactory::setContainer($container);
        $app = AppFactory::create();

        $app->addBodyParsingMiddleware();

        // CORS — креденшелы не нужны: фронт ходит same-origin через nginx-прокси / dev-proxy
        $app->add(function ($request, $handler) {
            $response = $handler->handle($request);

            return $response
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        });

        $app->options('/{routes:.+}', function ($request, $response) {
            return $response;
        });

        (require __DIR__ . '/../../config/routes.php')($app);

        // ApiExceptionMiddleware — ближайший к маршрутам: перехватывает
        // ошибки API до Slim ErrorMiddleware и отдаёт JSON со своим статусом.
        $app->add(new ApiExceptionMiddleware());
        $app->addErrorMiddleware(Env::bool('APP_DEBUG', true), true, true);

        return $app;
    }
}

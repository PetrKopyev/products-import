<?php

declare(strict_types=1);

use App\Controller\AuthController;
use App\Controller\HealthController;
use App\Controller\ImageController;
use App\Controller\ImportController;
use App\Controller\OpenApiController;
use App\Controller\ProductController;
use App\Middleware\AuthMiddleware;
use App\Middleware\RateLimitMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app): void {
    // Публичные endpoints
    $app->get('/api/health', [HealthController::class, 'health']);
    $app->post('/api/auth/login', [AuthController::class, 'login']);
    $app->get('/images/{filename}', [ImageController::class, 'serve']);
    $app->get('/api/docs', [OpenApiController::class, 'swaggerUi']);
    $app->get('/api/openapi.yaml', [OpenApiController::class, 'spec']);

    // Защищённые JWT endpoints
    $app->get('/api/auth/me', [AuthController::class, 'me'])->add(AuthMiddleware::class);

    $app->group('/api', function (RouteCollectorProxy $group): void {
        $group->post('/import', [ImportController::class, 'upload'])
            ->add(RateLimitMiddleware::class);
        $group->get('/import/{id:[0-9]+}', [ImportController::class, 'status']);
        $group->get('/products', [ProductController::class, 'list']);
        $group->get('/products/{id:[0-9]+}', [ProductController::class, 'show']);
    })->add(AuthMiddleware::class);

    // SPA fallback (локальный dev-сервер `php -S`, в docker отдаёт nginx)
    $app->get('/[{path:.*}]', function ($request, $response) {
        $response->getBody()->write('Products Import API. Документация: /api/docs');

        return $response;
    });
};

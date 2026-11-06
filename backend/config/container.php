<?php

declare(strict_types=1);

namespace App\Support;

use App\Controller\AuthController;
use App\Controller\HealthController;
use App\Controller\ImageController;
use App\Controller\ImportController;
use App\Controller\ProductController;
use App\MessageHandler\ImportProductsHandler;
use App\Middleware\AuthMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Repository\ImportJobRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use App\Service\AuthService;
use App\Service\ImageDownloadService;
use App\Service\ImportService;
use App\Service\ProductService;
use App\Service\XlsxParser;
use DI\Container;
use Doctrine\ORM\EntityManager;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * PSR-11 контейнер на PHP-DI (autowiring) + явные определения фабрик.
 *
 * @param array<string, mixed> $overrides подмены определений (используются в тестах)
 */
$factory = static function (array $overrides = []): ContainerInterface {
    $settings = require __DIR__ . '/settings.php';

    $definitions = [
        'settings' => $settings['settings'],

        LoggerInterface::class => static function (ContainerInterface $c): LoggerInterface {
            /** @var array{logger: array{name: string, path: string, level: string}} $s */
            $s = $c->get('settings');
            $logger = new Logger($s['logger']['name']);
            $handler = new StreamHandler($s['logger']['path'], $s['logger']['level']);
            $handler->setFormatter(new LineFormatter(null, 'Y-m-d H:i:s', true, true));
            $logger->pushHandler($handler);

            return $logger;
        },

        EntityManager::class => static function (ContainerInterface $c): EntityManager {
            /** @var array<string, mixed> $s */
            $s = $c->get('settings');

            return DoctrineFactory::create($s, Env::bool('APP_DEBUG', true));
        },

        ProductRepository::class => static fn (ContainerInterface $c): ProductRepository
            => new ProductRepository($c->get(EntityManager::class)),

        ImportJobRepository::class => static fn (ContainerInterface $c): ImportJobRepository
            => new ImportJobRepository($c->get(EntityManager::class)),

        UserRepository::class => static fn (ContainerInterface $c): UserRepository
            => new UserRepository($c->get(EntityManager::class)),

        XlsxParser::class => static fn (): XlsxParser => new XlsxParser(),

        ImageDownloadService::class => static function (ContainerInterface $c): ImageDownloadService {
            /** @var array{images: array{storage_path: string, max_bytes: int, timeout: int}} $s */
            $s = $c->get('settings');

            return new ImageDownloadService(
                storagePath: $s['images']['storage_path'],
                maxBytes: $s['images']['max_bytes'],
                timeout: $s['images']['timeout'],
            );
        },

        ImportService::class => static function (ContainerInterface $c): ImportService {
            /** @var array{upload: array{max_bytes: int}} $s */
            $s = $c->get('settings');

            return new ImportService(
                entityManager: $c->get(EntityManager::class),
                parser: $c->get(XlsxParser::class),
                imageDownloader: $c->get(ImageDownloadService::class),
                maxUploadBytes: $s['upload']['max_bytes'],
            );
        },

        ImportProductsHandler::class => static fn (ContainerInterface $c): ImportProductsHandler
            => new ImportProductsHandler($c->get(ImportService::class), $c->get(EntityManager::class)),

        AuthService::class => static function (ContainerInterface $c): AuthService {
            /** @var array{jwt: array{secret: string, ttl: int}} $s */
            $s = $c->get('settings');

            return new AuthService($c->get(UserRepository::class), $s['jwt']['secret'], $s['jwt']['ttl']);
        },

        ProductService::class => static fn (ContainerInterface $c): ProductService
            => new ProductService($c->get(ProductRepository::class)),

        ImportController::class => static function (ContainerInterface $c): ImportController {
            /** @var array{upload: array{max_bytes: int, allowed_extensions: array<string>, allowed_mime_types: array<string>}, images: array{storage_path: string}} $s */
            $s = $c->get('settings');

            return new ImportController(
                jobs: $c->get(ImportJobRepository::class),
                dispatchBus: MessengerFactory::createDispatchBus($c),
                uploadMaxBytes: $s['upload']['max_bytes'],
                allowedExtensions: $s['upload']['allowed_extensions'],
                allowedMimeTypes: $s['upload']['allowed_mime_types'],
                storagePath: $s['images']['storage_path'],
            );
        },

        ProductController::class => static fn (ContainerInterface $c): ProductController
            => new ProductController($c->get(ProductService::class)),

        AuthController::class => static fn (ContainerInterface $c): AuthController
            => new AuthController($c->get(AuthService::class)),

        HealthController::class => static fn (ContainerInterface $c): HealthController
            => new HealthController($c->get(EntityManager::class)),

        ImageController::class => static function (ContainerInterface $c): ImageController {
            /** @var array{images: array{storage_path: string}} $s */
            $s = $c->get('settings');

            return new ImageController($s['images']['storage_path']);
        },

        AuthMiddleware::class => static fn (ContainerInterface $c): AuthMiddleware
            => new AuthMiddleware($c->get(AuthService::class)),

        RateLimitMiddleware::class => static function (ContainerInterface $c): RateLimitMiddleware {
            /** @var array{rate_limit: array{max_requests: int, period_seconds: int}} $s */
            $s = $c->get('settings');

            return new RateLimitMiddleware(
                maxRequests: $s['rate_limit']['max_requests'],
                periodSeconds: $s['rate_limit']['period_seconds'],
            );
        },
    ];

    return new Container(array_merge($definitions, $overrides));
};

return $factory;

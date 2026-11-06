<?php

declare(strict_types=1);

namespace App\Support;

use App\Message\ImportProducts;
use App\MessageHandler\ImportProductsHandler;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Worker;

/**
 * Настройка Symfony Messenger.
 *
 * Два режима (MESSENGER_TRANSPORT_DSN):
 *  - amqp://... — сообщение отправляется в RabbitMQ, обработка асинхронно в worker'е;
 *  - sync:// (или пусто) — handler выполняется в текущем процессе (локальная разработка, тесты).
 */
final class MessengerFactory
{
    private const IMPORT_TRANSPORT_ID = 'messenger.transport.import';

    public static function createDispatchBus(ContainerInterface $container): MessageBusInterface
    {
        $dsn = Env::string('MESSENGER_TRANSPORT_DSN', 'sync://');

        if (!self::isAmqp($dsn)) {
            return new MessageBus([
                new HandleMessageMiddleware(new HandlersLocator([
                    ImportProducts::class => [$container->get(ImportProductsHandler::class)],
                ])),
            ]);
        }

        $serviceLocator = new MapContainer([
            self::IMPORT_TRANSPORT_ID => self::createAmqpTransport($dsn),
        ]);

        return new MessageBus([
            new SendMessageMiddleware(new SendersLocator([
                ImportProducts::class => [self::IMPORT_TRANSPORT_ID],
            ], $serviceLocator)),
        ]);
    }

    public static function runConsumer(ContainerInterface $container): void
    {
        $dsn = Env::string('MESSENGER_TRANSPORT_DSN', 'sync://');

        if (!self::isAmqp($dsn)) {
            fwrite(STDERR, "MESSENGER_TRANSPORT_DSN не указывает на RabbitMQ — консьюмер не требуется (sync-режим).\n");

            return;
        }

        $transport = self::createAmqpTransport($dsn);
        $bus = new MessageBus([
            new HandleMessageMiddleware(new HandlersLocator([
                ImportProducts::class => [$container->get(ImportProductsHandler::class)],
            ])),
        ]);

        (new Worker([$transport], $bus))->run();
    }

    private static function isAmqp(string $dsn): bool
    {
        return str_starts_with($dsn, 'amqp://') || str_starts_with($dsn, 'amqps://');
    }

    /**
     * @return \Symfony\Component\Messenger\Transport\TransportInterface
     */
    private static function createAmqpTransport(string $dsn): mixed
    {
        if (!\extension_loaded('amqp')) {
            throw new RuntimeException(
                'Для amqp-транспорта нужно PHP-расширение amqp. Локально используйте MESSENGER_TRANSPORT_DSN=sync://.',
            );
        }

        /** @var class-string $factoryClass */
        $factoryClass = 'Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransportFactory';

        return (new $factoryClass())->createTransport($dsn, []);
    }
}

/**
 * Минимальный PSR-11 контейнер для SendersLocator.
 */
final class MapContainer implements ContainerInterface
{
    /** @param array<string, mixed> $services */
    public function __construct(private readonly array $services)
    {
    }

    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new class ($id) extends RuntimeException implements \Psr\Container\NotFoundExceptionInterface {
            };
        }

        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return \array_key_exists($id, $this->services);
    }
}

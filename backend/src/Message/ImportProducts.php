<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Асинхронная задача импорта товаров (RabbitMQ через Symfony Messenger).
 */
final readonly class ImportProducts
{
    public function __construct(
        public int $jobId,
        public string $filePath,
    ) {
    }
}

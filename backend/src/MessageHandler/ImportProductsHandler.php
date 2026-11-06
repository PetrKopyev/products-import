<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ImportProducts;
use App\Service\ImportService;
use Doctrine\ORM\EntityManager;

/**
 * Обработчик очереди импорта. Работает в long-running процессе (worker),
 * поэтому после каждого сообщения очищает EntityManager.
 */
final class ImportProductsHandler
{
    public function __construct(
        private readonly ImportService $importService,
        private readonly EntityManager $entityManager,
    ) {
    }

    public function __invoke(ImportProducts $message): void
    {
        try {
            $this->importService->importFromFile($message->jobId, $message->filePath);
        } finally {
            $this->entityManager->clear();
        }
    }
}

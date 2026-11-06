<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Entity\ImportJob;
use App\Domain\Entity\Product;
use App\Repository\ImportJobRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManager;
use Throwable;

/**
 * Импорт товаров из распарсенного xlsx: upsert по external_code,
 * атомарная запись товара + атрибутов + изображений, отчёт об ошибках по строкам.
 */
final class ImportService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly XlsxParser $parser,
        private readonly ImageDownloadService $imageDownloader,
        private readonly int $maxUploadBytes,
    ) {
    }

    /**
     * @return array{rows: int, created: int, updated: int, warnings: array<string>}
     */
    public function importFromFile(int $jobId, string $filePath): array
    {
        $job = $this->requireJob($jobId);

        $job->markProcessing();
        $this->entityManager->flush();

        try {
            $this->assertFileAllowed($filePath);
            $rows = $this->parser->parse($filePath);
        } catch (Throwable $e) {
            $job->markFailed($e->getMessage());
            $this->entityManager->flush();

            return ['rows' => 0, 'created' => 0, 'updated' => 0, 'warnings' => []];
        }

        $job->setTotal(\count($rows));
        $this->entityManager->flush();

        $stats = ['rows' => \count($rows), 'created' => 0, 'updated' => 0, 'warnings' => []];

        foreach ($rows as $row) {
            try {
                $outcome = $this->importRow($row, $job);
                $stats[$outcome]++;
            } catch (Throwable $e) {
                $job->addError($row->rowNumber, $row->externalCode, [$e->getMessage()]);
            }

            $job->incrementProcessed();
            $this->entityManager->flush();
        }

        $job->markCompleted();
        $this->entityManager->flush();

        return $stats;
    }

    /**
     * Одна строка: скачивание изображений вне транзакции,
     * затем атомарная запись товара + атрибутов + изображений.
     *
     * @return 'created'|'updated'
     */
    private function importRow(ProductRow $row, ImportJob $job): string
    {
        $downloaded = $this->imageDownloader->downloadAll($row->imageUrls);
        foreach ($downloaded as $result) {
            if ($result['error'] !== null) {
                $job->addError($row->rowNumber, $row->externalCode, [
                    sprintf('Изображение не скачано (%s): %s', $result['url'], $result['error']),
                ]);
            }
        }

        $outcome = 'created';

        $this->entityManager->wrapInTransaction(function () use ($row, $downloaded, &$outcome): void {
            $repository = new ProductRepository($this->entityManager);

            $product = $repository->findByExternalCode($row->externalCode);
            if ($product !== null) {
                $outcome = 'updated';
                $product->update($row->name, $row->description, $row->price, $row->discount);
                $product->replaceAttributes($row->attributes);
                $product->clearImages();
            } else {
                $product = new Product(
                    externalCode: $row->externalCode,
                    name: $row->name,
                    description: $row->description,
                    price: $row->price,
                    discount: $row->discount,
                );
                $product->replaceAttributes($row->attributes);
                $repository->save($product);
            }

            foreach ($row->imageUrls as $url) {
                $product->addImage($url, $downloaded[$url]['path'] ?? null);
            }

            $this->entityManager->flush();
        });

        return $outcome;
    }

    private function requireJob(int $jobId): ImportJob
    {
        $repository = new ImportJobRepository($this->entityManager);
        $job = $repository->findById($jobId);

        if ($job === null) {
            throw new \RuntimeException(sprintf('Задача импорта #%d не найдена', $jobId));
        }

        return $job;
    }

    private function assertFileAllowed(string $filePath): void
    {
        $size = filesize($filePath);
        if ($size === false || $size > $this->maxUploadBytes) {
            throw new \RuntimeException('Превышен максимальный размер файла импорта');
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Entity\ImportJob;
use App\Message\ImportProducts;
use App\Repository\ImportJobRepository;
use App\Support\ApiException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class ImportController
{
    public function __construct(
        private readonly ImportJobRepository $jobs,
        private readonly MessageBusInterface $dispatchBus,
        private readonly int $uploadMaxBytes,
        private readonly array $allowedExtensions,
        private readonly array $allowedMimeTypes,
        private readonly string $storagePath,
    ) {
    }

    /**
     * POST /api/import — загрузка .xlsx, постановка задачи в очередь.
     */
    public function upload(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        /** @var array<string, UploadedFileInterface> $files */
        $files = $request->getUploadedFiles();
        $file = $files['file'] ?? null;

        if ($file === null || $file->getError() !== UPLOAD_ERR_OK) {
            throw new ApiException('Файл не загружен (ожидается поле "file")', 422);
        }

        $this->validateFile($file);

        $uploadsDir = rtrim($this->storagePath, '/') . '/imports';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0o775, true);
        }

        $storedName = sprintf('%s.xlsx', bin2hex(random_bytes(16)));
        $file->moveTo($uploadsDir . '/' . $storedName);
        $filePath = $uploadsDir . '/' . $storedName;

        $job = new ImportJob();
        $this->jobs->save($job);

        $this->dispatchBus->dispatch(new ImportProducts($job->getId(), $filePath));

        $payload = [
            'jobId' => $job->getId(),
            'status' => $job->getStatus(),
            'statusUrl' => sprintf('/api/import/%d', $job->getId()),
        ];

        return $this->json($response, $payload, 202);
    }

    /**
     * GET /api/import/{id} — статус задачи: прогресс + отчёт об ошибках (для polling).
     */
    public function status(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $job = $this->jobs->findById((int) ($args['id'] ?? 0));
        if ($job === null) {
            throw new ApiException('Задача импорта не найдена', 404);
        }

        return $this->json($response, ['data' => $job->toArray()]);
    }

    private function validateFile(UploadedFileInterface $file): void
    {
        $size = $file->getSize();
        if ($size === null || $size <= 0) {
            throw new ApiException('Пустой файл', 422);
        }
        if ($size > $this->uploadMaxBytes) {
            throw new ApiException(
                sprintf('Превышен максимальный размер файла (%d байт)', $this->uploadMaxBytes),
                422,
            );
        }

        $clientName = $file->getClientFilename() ?? '';
        $extension = strtolower(pathinfo($clientName, PATHINFO_EXTENSION));
        if (!\in_array($extension, $this->allowedExtensions, true)) {
            throw new ApiException('Допустим только файл .xlsx', 422);
        }

        $mimeType = strtolower((string) $file->getClientMediaType());
        if (!\in_array($mimeType, $this->allowedMimeTypes, true)) {
            throw new ApiException(sprintf('Недопустимый MIME-тип файла: %s', $mimeType), 422);
        }
    }

    /** @param array<string, mixed> $data */
    private function json(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write((string) json_encode($data, JSON_UNESCAPED_UNICODE));

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}

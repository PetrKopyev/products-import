<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Статус асинхронной задачи импорта: прогресс + отчёт об ошибках по строкам.
 */
#[ORM\Entity]
#[ORM\Table(name: 'import_jobs')]
class ImportJob
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public function __construct(
        #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
        private int $id = 0,
        #[ORM\Column(type: 'string', length: 32)]
        private string $status = self::STATUS_PENDING,
        #[ORM\Column(type: 'integer')]
        private int $total = 0,
        #[ORM\Column(type: 'integer')]
        private int $processed = 0,
        /**
         * @var array<int, array{row: int, externalCode: string|null, messages: array<string>}>
         */
        #[ORM\Column(type: 'json')]
        private array $errors = [],
        #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
        private \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
        #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
        private \DateTimeImmutable $updatedAt = new \DateTimeImmutable(),
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getProcessed(): int
    {
        return $this->processed;
    }

    /** @return array<int, array{row: int, externalCode: string|null, messages: array<string>}> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function markProcessing(): void
    {
        $this->status = self::STATUS_PROCESSING;
        $this->touch();
    }

    public function setTotal(int $total): void
    {
        $this->total = $total;
        $this->touch();
    }

    public function incrementProcessed(): void
    {
        $this->processed++;
        $this->touch();
    }

    /** @param array<string> $messages */
    public function addError(int $row, ?string $externalCode, array $messages): void
    {
        $this->errors[] = ['row' => $row, 'externalCode' => $externalCode, 'messages' => $messages];
        $this->touch();
    }

    public function markCompleted(): void
    {
        $this->status = self::STATUS_COMPLETED;
        $this->touch();
    }

    public function markFailed(string $message): void
    {
        $this->status = self::STATUS_FAILED;
        $this->errors[] = ['row' => 0, 'externalCode' => null, 'messages' => [$message]];
        $this->touch();
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'total' => $this->total,
            'processed' => $this->processed,
            'errors' => $this->errors,
            'createdAt' => $this->createdAt->format(\DateTimeInterface::ATOM),
            'updatedAt' => $this->updatedAt->format(\DateTimeInterface::ATOM),
        ];
    }
}

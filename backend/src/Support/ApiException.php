<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Ошибка API с HTTP-статусом и списком деталей (валидация и т.п.).
 */
final class ApiException extends RuntimeException
{
    /** @param array<string> $errors */
    public function __construct(
        string $message,
        private readonly int $statusCode,
        public readonly array $errors = [],
    ) {
        parent::__construct($message, $statusCode);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /** @return array{message: string, errors: array<string>} */
    public function payload(): array
    {
        return ['message' => $this->getMessage(), 'errors' => $this->errors];
    }
}

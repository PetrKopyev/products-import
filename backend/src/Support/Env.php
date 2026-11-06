<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Типизированное чтение переменных окружения.
 */
final class Env
{
    public static function string(string $key, string $default = ''): string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        return \is_string($value) && $value !== '' ? $value : $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        return \is_string($value) && is_numeric($value) ? (int) $value : $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if (!\is_string($value) || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}

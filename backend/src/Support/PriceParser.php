<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Парсинг денежных строк из xlsx ("1320,00", "1 320.50") в целые копейки
 * и точные расчёты без float-ошибок.
 */
final class PriceParser
{
    /**
     * "1320,00" / "1 320.50" / "0.99" -> 132000 / 132050 / 99 (копейки).
     */
    public static function toCents(string $raw): int
    {
        $value = trim(str_replace(["\u{00A0}", ' '], '', $raw));

        if ($value === '') {
            throw new InvalidArgumentException('Пустое значение цены');
        }

        // Русский формат: запятая — десятичный разделитель, точка может быть разделителем тысяч
        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }

        if (!preg_match('/^\d+(\.\d{1,2})?$/', $value)) {
            throw new InvalidArgumentException(sprintf('Некорректное значение цены "%s"', $raw));
        }

        /** @var array{0: string, 1?: string} $parts */
        $parts = explode('.', $value);
        $rubles = (int) $parts[0];
        $cents = isset($parts[1]) ? (int) str_pad($parts[1], 2, '0') : 0;

        return $rubles * 100 + $cents;
    }

    /**
     * 132000 копеек -> "1320.00" (decimal-строка для NUMERIC-колонки).
     */
    public static function centsToDecimal(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return sprintf('%s%d.%02d', $sign, intdiv($cents, 100), $cents % 100);
    }

    /**
     * Скидка в % = (цена продажи − закупочная) / цена продажи × 100.
     * Считается в целых копейках, округление до 2 знаков.
     */
    public static function discountPercent(int $saleCents, int $purchaseCents): ?string
    {
        if ($saleCents <= 0) {
            return null;
        }

        $basisPoints = (int) round(($saleCents - $purchaseCents) * 10000 / $saleCents);

        return self::centsToDecimal($basisPoints);
    }
}

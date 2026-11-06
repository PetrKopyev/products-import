<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Support\PriceParser;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PriceParserTest extends TestCase
{
    /**
     * @return array<string, array{string, int}>
     */
    public static function pricesProvider(): array
    {
        return [
            'русский формат с запятой' => ['1320,00', 132000],
            'дробная часть одна цифра' => ['1320,5', 132050],
            'точка как разделитель' => ['0.99', 99],
            'пробелы-разделители тысяч' => ['1 320.50', 132050],
            'неразрывный пробел' => ['1 320,50', 132050],
            'целое без копеек' => ['100', 10000],
        ];
    }

    #[DataProvider('pricesProvider')]
    public function testToCents(string $raw, int $expected): void
    {
        self::assertSame($expected, PriceParser::toCents($raw));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidPricesProvider(): array
    {
        return [
            'пустая строка' => [''],
            'буквы' => ['abc'],
            'две точки' => ['1.2.3'],
            'отрицательное' => ['-100'],
        ];
    }

    #[DataProvider('invalidPricesProvider')]
    public function testToCentsInvalid(string $raw): void
    {
        $this->expectException(InvalidArgumentException::class);
        PriceParser::toCents($raw);
    }

    public function testCentsToDecimal(): void
    {
        self::assertSame('1320.00', PriceParser::centsToDecimal(132000));
        self::assertSame('0.99', PriceParser::centsToDecimal(99));
        self::assertSame('0.05', PriceParser::centsToDecimal(5));
    }

    public function testDiscountPercent(): void
    {
        // (1320 − 880) / 1320 × 100 = 33.33%
        self::assertSame('33.33', PriceParser::discountPercent(132000, 88000));
        self::assertSame('50.00', PriceParser::discountPercent(20000, 10000));
        self::assertSame('0.00', PriceParser::discountPercent(10000, 10000));
        // цена продажи 0 → скидка не вычисляется
        self::assertNull(PriceParser::discountPercent(0, 0));
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\InvalidRowException;
use App\Service\XlsxParser;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class XlsxParserTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../../fixtures/import-example.xlsx';

    public function testParseRealFixture(): void
    {
        $parser = new XlsxParser();
        $rows = $parser->parse(self::FIXTURE);

        self::assertCount(40, $rows);

        $first = $rows[0];
        self::assertSame(2, $first->rowNumber);
        self::assertSame('3UHfAid1jaMiwgBuNvnsf3', $first->externalCode);
        self::assertSame('Бермуды мужские, Grigio/Verde, OMSA, 46(M), РОССИЯ', $first->name);
        self::assertSame('1320.00', $first->price);
        self::assertSame('33.33', $first->discount);
        self::assertSame('46(M)', $first->attributes['Размер']);
        self::assertSame('Grigio/Verde', $first->attributes['Цвет']);
        self::assertArrayNotHasKey('Ссылки на фото', $first->attributes);
        self::assertCount(3, $first->imageUrls);
        self::assertStringStartsWith('http://catalog.collant.ru/', $first->imageUrls[0]);
    }

    public function testEmptyAttributeSkipped(): void
    {
        $parser = new XlsxParser();
        $rows = $parser->parse(self::FIXTURE);

        // «Доп. поле: seo h1» пуст во всём файле → в атрибуты не попадает
        foreach ($rows as $row) {
            self::assertArrayNotHasKey('seo h1', $row->attributes);
        }
    }

    public function testInvalidRowThrows(): void
    {
        $path = $this->makeXlsx([
            ['Внешний код', 'Наименование', 'Цена: Цена продажи'],
            ['CODE-1', 'Товар без цены', ''],
        ]);

        $parser = new XlsxParser();

        try {
            $parser->parse($path);
            self::fail('Ожидался InvalidRowException');
        } catch (InvalidRowException $e) {
            self::assertSame(2, $e->rowNumber);
            self::assertNotEmpty($e->messages);
        } finally {
            unlink($path);
        }
    }

    public function testMissingRequiredColumnThrows(): void
    {
        $path = $this->makeXlsx([
            ['Наименование'],
            ['Только имя'],
        ]);

        $parser = new XlsxParser();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('обязательные колонки');
        try {
            $parser->parse($path);
        } finally {
            unlink($path);
        }
    }

    /**
     * @param array<array<string>> $rows
     */
    private function makeXlsx(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        foreach ($rows as $r => $row) {
            foreach ($row as $c => $value) {
                $sheet->setCellValue([$c + 1, $r + 1], $value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'xlsx-test-') . '.xlsx';
        (new XlsxWriter($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }
}

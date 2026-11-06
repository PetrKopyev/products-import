<?php

declare(strict_types=1);

namespace App\Service;

use App\Support\PriceParser;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

/**
 * Результат парсинга одной строки xlsx.
 */
final class ProductRow
{
    /**
     * @param array<string, string> $attributes доп. поля (ключ => значение), без пустых
     * @param array<string>         $imageUrls  ссылки на изображения из «Доп. поле: Ссылки на фото»
     */
    public function __construct(
        public readonly int $rowNumber,
        public readonly string $externalCode,
        public readonly string $name,
        public readonly ?string $description,
        public readonly string $price,
        public readonly ?string $discount,
        public readonly array $attributes,
        public readonly array $imageUrls,
    ) {
    }
}

/**
 * Ошибка в конкретной строке файла — не прерывает весь импорт.
 */
final class InvalidRowException extends RuntimeException
{
    /** @param array<string> $messages */
    public function __construct(
        public readonly int $rowNumber,
        public readonly array $messages,
    ) {
        parent::__construct(implode('; ', $messages));
    }
}

/**
 * Невалидная строка файла: уходит в отчёт об ошибках импорта.
 */
final class InvalidRow
{
    /** @param array<string> $messages */
    public function __construct(
        public readonly int $rowNumber,
        public readonly ?string $externalCode,
        public readonly array $messages,
    ) {
    }
}

/**
 * Результат парсинга: валидные строки + ошибки по невалидным.
 */
final class ParseResult
{
    /**
     * @param array<ProductRow> $rows
     * @param array<InvalidRow> $errors
     */
    public function __construct(
        public readonly array $rows,
        public readonly array $errors,
    ) {
    }
}

/**
 * Парсинг .xlsx в DTO по заголовкам колонок.
 *
 * Ожидаемые колонки: «Внешний код», «Наименование», «Описание»,
 * «Цена: Цена продажи», «Закупочная цена», любые «Доп. поле: *»
 * (кроме «Ссылки на фото» — это источник изображений).
 */
final class XlsxParser
{
    private const COL_EXTERNAL_CODE = 'Внешний код';
    private const COL_NAME = 'Наименование';
    private const COL_DESCRIPTION = 'Описание';
    private const COL_PRICE = 'Цена: Цена продажи';
    private const COL_PURCHASE_PRICE = 'Закупочная цена';
    private const ATTRIBUTE_PREFIX = 'Доп. поле: ';
    private const IMAGE_COLUMN = 'Доп. поле: Ссылки на фото';

    /**
     * Парсинг .xlsx в DTO по заголовкам колонок.
     *
     * Ожидаемые колонки: «Внешний код», «Наименование», «Описание»,
     * «Цена: Цена продажи», «Закупочная цена», любые «Доп. поле: *»
     * (кроме «Ссылки на фото» — это источник изображений).
     *
     * Невалидные строки не прерывают парсинг — собираются в ParseResult->errors
     * (фатальные проблемы файла, например отсутствие обязательных колонок, по-прежнему
     * бросают RuntimeException).
     */
    public function parse(string $filePath): ParseResult
    {
        if (!is_file($filePath)) {
            throw new RuntimeException(sprintf('Файл не найден: %s', $filePath));
        }

        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);

        try {
            $sheet = $spreadsheet->getActiveSheet();
            $columnMap = $this->buildColumnMap($sheet);

            $rows = [];
            $errors = [];
            $highestRow = $sheet->getHighestDataRow();

            for ($rowIndex = 2; $rowIndex <= $highestRow; $rowIndex++) {
                try {
                    $row = $this->parseRow($sheet, $columnMap, $rowIndex);
                    if ($row !== null) {
                        $rows[] = $row;
                    }
                } catch (InvalidRowException $e) {
                    $code = $this->readString($sheet, $columnMap->externalCode, $e->rowNumber);

                    $errors[] = new InvalidRow(
                        rowNumber: $e->rowNumber,
                        externalCode: $code !== '' ? $code : null,
                        messages: $e->messages,
                    );
                }
            }

            return new ParseResult($rows, $errors);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    /**
     * Индексы колонок (1-based) по заголовкам первой строки.
     */
    private function buildColumnMap(Worksheet $sheet): ColumnMap
    {
        $map = new ColumnMap();

        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $value = $sheet->getCell([$col, 1])->getValue();
            if (!\is_string($value)) {
                continue;
            }
            $header = trim($value);

            match ($header) {
                self::COL_EXTERNAL_CODE => $map->externalCode = $col,
                self::COL_NAME => $map->name = $col,
                self::COL_DESCRIPTION => $map->description = $col,
                self::COL_PRICE => $map->price = $col,
                self::COL_PURCHASE_PRICE => $map->purchasePrice = $col,
                self::IMAGE_COLUMN => $map->images = $col,
                default => null,
            };

            if (str_starts_with($header, self::ATTRIBUTE_PREFIX) && $header !== self::IMAGE_COLUMN) {
                $attributeName = trim(substr($header, \strlen(self::ATTRIBUTE_PREFIX)));
                if ($attributeName !== '') {
                    $map->attributes[$attributeName] = $col;
                }
            }
        }

        $missing = [];
        if ($map->externalCode === null) {
            $missing[] = self::COL_EXTERNAL_CODE;
        }
        if ($map->name === null) {
            $missing[] = self::COL_NAME;
        }
        if ($map->price === null) {
            $missing[] = self::COL_PRICE;
        }

        if ($missing !== []) {
            throw new RuntimeException('В файле отсутствуют обязательные колонки: ' . implode(', ', $missing));
        }

        return $map;
    }

    /**
     * @param ColumnMap $columnMap
     */
    private function parseRow(Worksheet $sheet, ColumnMap $columnMap, int $rowIndex): ?ProductRow
    {
        $errors = [];

        $externalCode = $this->readString($sheet, $columnMap->externalCode, $rowIndex);
        $name = $this->readString($sheet, $columnMap->name, $rowIndex);
        $priceRaw = $this->readString($sheet, $columnMap->price, $rowIndex);
        $purchaseRaw = $columnMap->purchasePrice !== null
            ? $this->readString($sheet, $columnMap->purchasePrice, $rowIndex)
            : null;

        // Пропуск полностью пустых строк (хвосты листов)
        if ($externalCode === '' && $name === '' && $priceRaw === '') {
            return null;
        }

        if ($externalCode === '' || mb_strlen($externalCode) > 64) {
            $errors[] = '«Внешний код» пуст или длиннее 64 символов';
        }
        if ($name === '' || mb_strlen($name) > 512) {
            $errors[] = '«Наименование» пусто или длиннее 512 символов';
        }

        $priceCents = null;
        try {
            $priceCents = PriceParser::toCents($priceRaw);
        } catch (\InvalidArgumentException $e) {
            $errors[] = sprintf('«Цена продажи»: %s', $e->getMessage());
        }

        $discount = null;
        if ($purchaseRaw !== null && $purchaseRaw !== '') {
            try {
                $purchaseCents = PriceParser::toCents($purchaseRaw);
                $discount = $priceCents !== null
                    ? PriceParser::discountPercent($priceCents, $purchaseCents)
                    : null;
            } catch (\InvalidArgumentException $e) {
                $errors[] = sprintf('«Закупочная цена»: %s', $e->getMessage());
            }
        }

        $attributes = [];
        foreach ($columnMap->attributes as $key => $col) {
            $value = $this->readString($sheet, $col, $rowIndex);
            if ($value !== '') {
                $attributes[$key] = $value;
            }
        }

        $imageUrls = [];
        if ($columnMap->images !== null) {
            $imageCell = $this->readString($sheet, $columnMap->images, $rowIndex);
            foreach (explode(',', $imageCell) as $url) {
                $url = trim($url);
                if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL)) {
                    $imageUrls[] = $url;
                } elseif ($url !== '') {
                    $errors[] = sprintf('Некорректная ссылка на изображение: "%s"', $url);
                }
            }
        }

        if ($errors !== []) {
            throw new InvalidRowException($rowIndex, $errors);
        }

        return new ProductRow(
            rowNumber: $rowIndex,
            externalCode: $externalCode,
            name: $name,
            description: $columnMap->description !== null
                ? $this->readString($sheet, $columnMap->description, $rowIndex) ?: null
                : null,
            price: PriceParser::centsToDecimal($priceCents ?? 0),
            discount: $discount,
            attributes: $attributes,
            imageUrls: $imageUrls,
        );
    }

    private function readString(Worksheet $sheet, int $column, int $row): string
    {
        $value = $sheet->getCell([$column, $row])->getValue();

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        if ($value instanceof \Stringable) {
            return trim((string) $value);
        }

        return '';
    }
}

/**
 * @internal карта колонок: индексы 1-based, null — колонка не найдена
 */
final class ColumnMap
{
    public ?int $externalCode = null;
    public ?int $name = null;
    public ?int $description = null;
    public ?int $price = null;
    public ?int $purchasePrice = null;
    public ?int $images = null;

    /** @var array<string, int> */
    public array $attributes = [];
}

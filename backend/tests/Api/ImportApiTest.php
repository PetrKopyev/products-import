<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Service\ImageDownloadService;
use App\Tests\TestCase;

/**
 * Сквозной сценарий: загрузка xlsx → импорт (sync-транспорт) →
 * проверка статуса задачи и списка товаров → повторный импорт (upsert).
 *
 * Скачивание изображений заглушено (сетевой доступ в тестах не требуется).
 */
final class ImportApiTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../../fixtures/import-example.xlsx';

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = $this->authToken();
    }

    protected static function containerOverrides(): array
    {
        return [
            // Stub: имитируем успешное скачивание без сети
            ImageDownloadService::class => static fn (): ImageDownloadService => new class ('stub', 1024, 1) extends ImageDownloadService {
                /** @param array<string> $urls */
                public function downloadAll(array $urls): array
                {
                    $results = [];
                    foreach ($urls as $url) {
                        $results[$url] = ['url' => $url, 'path' => 'stub-' . md5($url) . '.jpg', 'error' => null];
                    }

                    return $results;
                }
            },
        ];
    }

    public function testImportFlow(): void
    {
        // 1. Загрузка файла → 202 + jobId
        $upload = $this->uploadRequest(
            '/api/import',
            self::FIXTURE,
            'import-example.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ['Authorization' => 'Bearer ' . $this->token],
        );

        self::assertSame(202, $upload->getStatusCode(), (string) $upload->getBody());

        $payload = $this->decode($upload);
        self::assertArrayHasKey('jobId', $payload);
        self::assertSame('/api/import/' . $payload['jobId'], $payload['statusUrl']);
        $jobId = (int) $payload['jobId'];

        // 2. Статус задачи (sync-транспорт: уже completed)
        $status = $this->jsonRequest('GET', '/api/import/' . $jobId, null, [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        self::assertSame(200, $status->getStatusCode());

        $job = $this->decode($status)['data'];
        self::assertSame('completed', $job['status']);
        self::assertSame(40, $job['total']);
        self::assertSame(40, $job['processed']);

        // 3. Список товаров: 40 штук, поля корректны
        $list = $this->jsonRequest('GET', '/api/products?limit=100', null, [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $listPayload = $this->decode($list);
        self::assertSame(40, $listPayload['meta']['total']);

        $first = $listPayload['data'][0];
        self::assertSame('3UHfAid1jaMiwgBuNvnsf3', $first['externalCode']);
        self::assertSame('Бермуды мужские, Grigio/Verde, OMSA, 46(M), РОССИЯ', $first['name']);
        self::assertEquals(1320.0, $first['price']);
        self::assertEquals(33.33, $first['discount']);
        self::assertCount(3, $first['images']);
        self::assertNotNull($first['images'][0]['path']);

        $attributes = array_column($first['attributes'], 'value', 'key');
        self::assertSame('46(M)', $attributes['Размер']);
        self::assertSame('OMSA', $attributes['Бренд']);

        // 4. Повторный импорт → upsert, дубликатов нет
        $reUpload = $this->uploadRequest(
            '/api/import',
            self::FIXTURE,
            'import-example.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ['Authorization' => 'Bearer ' . $this->token],
        );
        self::assertSame(202, $reUpload->getStatusCode());

        $listAfter = $this->jsonRequest('GET', '/api/products?limit=100', null, [
            'Authorization' => 'Bearer ' . $this->token,
        ]);
        self::assertSame(40, $this->decode($listAfter)['meta']['total']);
    }

    public function testImportRequiresAuth(): void
    {
        $response = $this->uploadRequest(
            '/api/import',
            self::FIXTURE,
            'import-example.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );

        self::assertSame(401, $response->getStatusCode());
    }

    public function testImportRejectsWrongExtension(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'import-test-');
        self::assertNotFalse($tmp);
        file_put_contents($tmp, 'not an xlsx');

        $response = $this->uploadRequest(
            '/api/import',
            $tmp,
            'import-example.csv',
            'text/csv',
            ['Authorization' => 'Bearer ' . $this->token],
        );

        self::assertSame(422, $response->getStatusCode());

        $payload = $this->decode($response);
        self::assertSame('Допустим только файл .xlsx', $payload['message']);

        unlink($tmp);
    }

    public function testImportStatusNotFound(): void
    {
        $response = $this->jsonRequest('GET', '/api/import/99999', null, [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        self::assertSame(404, $response->getStatusCode());
    }

    public function testImportLatest(): void
    {
        $response = $this->jsonRequest('GET', '/api/import/latest', null, [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        // После setUp задач нет — 404
        self::assertSame(404, $response->getStatusCode());

        $this->uploadRequest(
            '/api/import',
            self::FIXTURE,
            'import-example.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ['Authorization' => 'Bearer ' . $this->token],
        );

        $latest = $this->jsonRequest('GET', '/api/import/latest', null, [
            'Authorization' => 'Bearer ' . $this->token,
        ]);
        self::assertSame(200, $latest->getStatusCode());

        $job = $this->decode($latest)['data'];
        self::assertSame('completed', $job['status']);
        self::assertSame(40, $job['total']);
    }

    public function testInvalidRowDoesNotBreakImport(): void
    {
        $path = $this->makeXlsx([
            ['Внешний код', 'Наименование', 'Цена: Цена продажи'],
            ['MIX-1', 'Валидный товар', '1500,00'],
            ['MIX-2', '', 'не-число'],
        ]);

        try {
            $upload = $this->uploadRequest(
                '/api/import',
                $path,
                'mixed.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ['Authorization' => 'Bearer ' . $this->token],
            );
            self::assertSame(202, $upload->getStatusCode(), (string) $upload->getBody());

            $jobId = (int) $this->decode($upload)['jobId'];

            $status = $this->jsonRequest('GET', '/api/import/' . $jobId, null, [
                'Authorization' => 'Bearer ' . $this->token,
            ]);

            $job = $this->decode($status)['data'];
            self::assertSame('completed', $job['status']);
            self::assertSame(2, $job['total']);
            self::assertSame(2, $job['processed']);
            self::assertCount(1, $job['errors']);
            self::assertSame('MIX-2', $job['errors'][0]['externalCode']);

            $list = $this->jsonRequest('GET', '/api/products?name=' . urlencode('Валидный товар'), null, [
                'Authorization' => 'Bearer ' . $this->token,
            ]);
            self::assertSame(1, $this->decode($list)['meta']['total']);
        } finally {
            unlink($path);
        }
    }

    /**
     * @param array<array<string>> $rows
     */
    private function makeXlsx(array $rows): string
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        foreach ($rows as $r => $row) {
            foreach ($row as $c => $value) {
                $sheet->setCellValue([$c + 1, $r + 1], $value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'xlsx-api-test-') . '.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }
}

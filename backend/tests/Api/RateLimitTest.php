<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\TestCase;

final class RateLimitTest extends TestCase
{
    public function testImportEndpointRateLimited(): void
    {
        $token = $this->authToken();
        $tmp = tempnam(sys_get_temp_dir(), 'rate-limit-test-');
        self::assertNotFalse($tmp);
        file_put_contents($tmp, 'dummy');

        // Лимит 5 запросов/мин (env в phpunit.xml) — 6-й должен получить 429
        $statuses = [];
        for ($i = 1; $i <= 6; $i++) {
            $response = $this->uploadRequest(
                '/api/import',
                $tmp,
                'dummy.xlsx',
                'application/octet-stream',
                ['Authorization' => 'Bearer ' . $token],
                ['REMOTE_ADDR' => '10.55.0.1'],
            );
            $statuses[] = $response->getStatusCode();
        }

        unlink($tmp);

        self::assertSame([202, 202, 202, 202, 202, 429], $statuses);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\TestCase;

final class HealthTest extends TestCase
{
    public function testHealthReturnsOk(): void
    {
        $response = $this->jsonRequest('GET', '/api/health');

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->decode($response);
        self::assertSame('ok', $payload['status']);
        self::assertSame('up', $payload['db']);
    }
}

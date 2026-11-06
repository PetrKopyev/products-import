<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\TestCase;

final class AuthTest extends TestCase
{
    public function testLoginSuccess(): void
    {
        $response = $this->jsonRequest('POST', '/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'admin123',
        ]);

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->decode($response);
        self::assertNotEmpty($payload['token']);
        self::assertSame('admin@example.com', $payload['user']['email']);
    }

    public function testLoginWrongPassword(): void
    {
        $response = $this->jsonRequest('POST', '/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong',
        ]);

        self::assertSame(401, $response->getStatusCode());

        $payload = $this->decode($response);
        self::assertSame('Неверный email или пароль', $payload['message']);
    }

    public function testLoginValidation(): void
    {
        $response = $this->jsonRequest('POST', '/api/auth/login', [
            'email' => 'not-an-email',
            'password' => '',
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testMeWithToken(): void
    {
        $token = $this->authToken();

        $response = $this->jsonRequest('GET', '/api/auth/me', null, [
            'Authorization' => 'Bearer ' . $token,
        ]);

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->decode($response);
        self::assertSame('admin@example.com', $payload['user']['email']);
    }

    public function testProtectedRouteWithoutToken(): void
    {
        $response = $this->jsonRequest('GET', '/api/products');

        self::assertSame(401, $response->getStatusCode());
    }

    public function testProtectedRouteWithGarbageToken(): void
    {
        $response = $this->jsonRequest('GET', '/api/products', null, [
            'Authorization' => 'Bearer garbage',
        ]);

        self::assertSame(401, $response->getStatusCode());
    }
}

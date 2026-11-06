<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AuthService;
use App\Support\ApiException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AuthController
{
    public function __construct(private readonly AuthService $auth)
    {
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        /** @var array<string, mixed> $body */
        $body = (array) $request->getParsedBody();

        $email = \is_string($body['email'] ?? null) ? trim($body['email']) : '';
        $password = \is_string($body['password'] ?? null) ? $body['password'] : '';

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ApiException('Поле email обязательно и должно быть корректным адресом', 422);
        }
        if ($password === '') {
            throw new ApiException('Поле password обязательно', 422);
        }

        try {
            $result = $this->auth->login($email, $password);
        } catch (\RuntimeException $e) {
            throw new ApiException($e->getMessage(), $e->getCode() === 401 ? 401 : 500);
        }

        return $this->json($response, $result);
    }

    public function me(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        /** @var array<string, mixed> $user */
        $user = $request->getAttribute('user');

        return $this->json($response, ['user' => $user]);
    }

    /** @param array<string, mixed> $data */
    private function json(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write((string) json_encode($data, JSON_UNESCAPED_UNICODE));

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}

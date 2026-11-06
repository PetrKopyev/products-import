<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\ORM\EntityManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class HealthController
{
    public function __construct(private readonly EntityManager $entityManager)
    {
    }

    public function health(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $db = 'up';
        $status = 200;

        try {
            $this->entityManager->getConnection()->executeQuery('SELECT 1');
        } catch (\Throwable) {
            $db = 'down';
            $status = 503;
        }

        $response->getBody()->write((string) json_encode([
            'status' => $status === 200 ? 'ok' : 'fail',
            'db' => $db,
            'time' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ], JSON_UNESCAPED_UNICODE));

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}

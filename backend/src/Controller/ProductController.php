<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ProductService;
use App\Support\ApiException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ProductController
{
    private const MAX_LIMIT = 100;

    public function __construct(private readonly ProductService $products)
    {
    }

    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();

        $page = $this->positiveInt($params, 'page', 1);
        $limit = min($this->positiveInt($params, 'limit', 20), self::MAX_LIMIT);

        $filters = [
            'name' => $this->stringParam($params, 'name'),
            'price_min' => $this->floatParam($params, 'price_min'),
            'price_max' => $this->floatParam($params, 'price_max'),
        ];

        $sort = $this->stringParam($params, 'sort');
        if (!\in_array($sort, ['', 'price_asc', 'price_desc'], true)) {
            throw new ApiException('Параметр sort должен быть одним из: price_asc, price_desc', 422);
        }
        $filters['sort'] = $sort;

        if ($filters['price_min'] !== null && $filters['price_max'] !== null
            && $filters['price_min'] > $filters['price_max']) {
            throw new ApiException('price_min не может быть больше price_max', 422);
        }

        $payload = $this->products->list($page, $limit, $filters);

        return $this->json($response, $payload);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) {
            throw new ApiException('Некорректный id товара', 422);
        }

        try {
            $product = $this->products->detail($id);
        } catch (\RuntimeException $e) {
            throw new ApiException($e->getMessage(), $e->getCode() === 404 ? 404 : 500);
        }

        return $this->json($response, ['data' => $product]);
    }

    /** @param array<string, mixed> $params */
    private function positiveInt(array $params, string $key, int $default): int
    {
        $value = $params[$key] ?? null;
        if ($value === null || $value === '') {
            return $default;
        }

        if (!is_numeric($value) || (int) $value < 1) {
            throw new ApiException(sprintf('Параметр %s должен быть положительным целым числом', $key), 422);
        }

        return (int) $value;
    }

    /** @param array<string, mixed> $params */
    private function floatParam(array $params, string $key): ?float
    {
        $value = $params[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            throw new ApiException(sprintf('Параметр %s должен быть числом', $key), 422);
        }

        return (float) $value;
    }

    /** @param array<string, mixed> $params */
    private function stringParam(array $params, string $key): string
    {
        $value = $params[$key] ?? null;

        return \is_string($value) ? trim($value) : '';
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

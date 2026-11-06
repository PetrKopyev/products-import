<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Entity\Product;
use App\Repository\ProductRepository;
use RuntimeException;

/**
 * Чтение товаров: пагинация, фильтрация, карточка.
 */
final class ProductService
{
    public function __construct(private readonly ProductRepository $products)
    {
    }

    /**
     * @param array{name?: string, price_min?: float|null, price_max?: float|null, sort?: string} $filters
     * @return array{data: array<array<string, mixed>>, meta: array{page: int, limit: int, total: int, totalPages: int}}
     */
    public function list(int $page, int $limit, array $filters): array
    {
        $result = $this->products->findPaginated($page, $limit, $filters);
        $totalPages = $result['total'] > 0 ? (int) ceil($result['total'] / $limit) : 0;

        return [
            'data' => array_map(
                static fn (Product $product): array => $product->toArray(),
                $result['items'],
            ),
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $result['total'],
                'totalPages' => $totalPages,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function detail(int $id): array
    {
        $product = $this->products->findById($id);

        if ($product === null) {
            throw new RuntimeException('Товар не найден', 404);
        }

        return $product->toArray();
    }
}

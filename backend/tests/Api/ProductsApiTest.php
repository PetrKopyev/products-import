<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Domain\Entity\Product;
use App\Repository\ProductRepository;
use App\Tests\TestCase;

final class ProductsApiTest extends TestCase
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedProducts();
        $this->token = $this->authToken();
    }

    public function testListReturnsPaginatedPayload(): void
    {
        $response = $this->jsonRequest('GET', '/api/products', null, [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->decode($response);
        self::assertCount(3, $payload['data']);
        self::assertSame(3, $payload['meta']['total']);
        self::assertSame(1, $payload['meta']['page']);
        self::assertSame(20, $payload['meta']['limit']);
        self::assertSame(1, $payload['meta']['totalPages']);

        $first = $payload['data'][0];
        self::assertArrayHasKey('externalCode', $first);
        self::assertArrayHasKey('attributes', $first);
        self::assertArrayHasKey('images', $first);
        self::assertEquals(1500.0, $first['price']);
    }

    public function testPaginationLimit(): void
    {
        $response = $this->jsonRequest('GET', '/api/products?page=1&limit=2', null, [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $payload = $this->decode($response);
        self::assertCount(2, $payload['data']);
        self::assertSame(3, $payload['meta']['total']);
        self::assertSame(2, $payload['meta']['totalPages']);

        $page2 = $this->jsonRequest('GET', '/api/products?page=2&limit=2', null, [
            'Authorization' => 'Bearer ' . $this->token,
        ]);
        $payload2 = $this->decode($page2);
        self::assertCount(1, $payload2['data']);
    }

    public function testFilterByName(): void
    {
        $response = $this->jsonRequest('GET', '/api/products?' . http_build_query(['name' => 'Футболка']), null, [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $payload = $this->decode($response);
        self::assertSame(2, $payload['meta']['total']);
    }

    public function testFilterByPriceRange(): void
    {
        $response = $this->jsonRequest('GET', '/api/products?' . http_build_query([
            'price_min' => 1200,
            'price_max' => 2600,
        ]), null, ['Authorization' => 'Bearer ' . $this->token]);

        $payload = $this->decode($response);
        self::assertSame(2, $payload['meta']['total']);
        $names = array_column($payload['data'], 'name');
        self::assertContains('Футболка белая', $names);
        self::assertContains('Кроссовки беговые', $names);
    }

    public function testInvalidPriceFilter(): void
    {
        $response = $this->jsonRequest('GET', '/api/products?price_min=abc', null, [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testSortByPrice(): void
    {
        $asc = $this->jsonRequest('GET', '/api/products?' . http_build_query([
            'sort' => 'price_asc',
            'limit' => 100,
        ]), null, ['Authorization' => 'Bearer ' . $this->token]);

        $payload = $this->decode($asc);
        self::assertSame(
            [1000.0, 1500.0, 2500.0],
            array_map(static fn (array $p): float => (float) $p['price'], $payload['data']),
        );

        $desc = $this->jsonRequest('GET', '/api/products?' . http_build_query([
            'sort' => 'price_desc',
            'limit' => 100,
        ]), null, ['Authorization' => 'Bearer ' . $this->token]);

        $payloadDesc = $this->decode($desc);
        self::assertSame(
            [2500.0, 1500.0, 1000.0],
            array_map(static fn (array $p): float => (float) $p['price'], $payloadDesc['data']),
        );
    }

    public function testInvalidSort(): void
    {
        $response = $this->jsonRequest('GET', '/api/products?sort=name_asc', null, [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testDetailReturnsFullProduct(): void
    {
        $repository = new ProductRepository(self::$em);
        $product = $repository->findByExternalCode('API-1');
        self::assertNotNull($product);

        $response = $this->jsonRequest('GET', '/api/products/' . $product->getId(), null, [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->decode($response);
        self::assertSame('Футболка белая', $payload['data']['name']);
        self::assertSame('33.33', (string) $payload['data']['discount']);
        self::assertCount(1, $payload['data']['attributes']);
        self::assertSame('Размер', $payload['data']['attributes'][0]['key']);
        self::assertCount(1, $payload['data']['images']);
    }

    public function testDetailNotFound(): void
    {
        $response = $this->jsonRequest('GET', '/api/products/99999', null, [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        self::assertSame(404, $response->getStatusCode());

        $payload = $this->decode($response);
        self::assertSame('Товар не найден', $payload['message']);
    }

    private function seedProducts(): void
    {
        $repository = new ProductRepository(self::$em);

        $p1 = new Product(externalCode: 'API-1', name: 'Футболка белая', price: '1500.00', discount: '33.33');
        $p1->replaceAttributes(['Размер' => 'L']);
        $p1->addImage('http://example.com/1.jpg', 'fake-1.jpg');
        $repository->save($p1);

        $repository->save(new Product(externalCode: 'API-2', name: 'Футболка чёрная', price: '1000.00', discount: null));
        $repository->save(new Product(externalCode: 'API-3', name: 'Кроссовки беговые', price: '2500.00', discount: '20.00'));
        self::$em->flush();
    }
}

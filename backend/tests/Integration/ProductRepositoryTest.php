<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Domain\Entity\Product;
use App\Repository\ProductRepository;
use App\Tests\TestCase;

/**
 * CRUD-операции репозитория товаров.
 */
final class ProductRepositoryTest extends TestCase
{
    public function testCreateAndRead(): void
    {
        $product = new Product(
            externalCode: 'CRUD-001',
            name: 'Тестовый товар',
            description: 'Описание',
            price: '1500.00',
            discount: '33.33',
        );
        $product->replaceAttributes(['Размер' => 'M', 'Цвет' => 'чёрный']);
        $product->addImage('http://example.com/1.jpg', 'a1b2.jpg');

        $repository = new ProductRepository(self::$em);
        $repository->save($product);
        self::$em->flush();

        self::assertGreaterThan(0, $product->getId());

        $found = $repository->findByExternalCode('CRUD-001');
        self::assertNotNull($found);
        self::assertSame('Тестовый товар', $found->getName());
        self::assertSame('1500.00', $found->getPrice());
        self::assertSame('33.33', $found->getDiscount());
        self::assertCount(2, $found->getAttributes());
        self::assertCount(1, $found->getImages());
    }

    public function testUpdate(): void
    {
        $repository = new ProductRepository(self::$em);

        $product = new Product(externalCode: 'CRUD-002', name: 'Было', price: '100.00', discount: null);
        $product->replaceAttributes(['Старое' => 'да']);
        $repository->save($product);
        self::$em->flush();
        $id = $product->getId();

        $product->update('Стало', 'Новое описание', '200.00', '10.00');
        $product->replaceAttributes(['Новое' => 'да', 'Цвет' => 'белый']);
        self::$em->flush();
        self::$em->clear();

        $found = $repository->findById($id);
        self::assertNotNull($found);
        self::assertSame('Стало', $found->getName());
        self::assertSame('200.00', $found->getPrice());
        self::assertSame('10.00', $found->getDiscount());
        self::assertCount(2, $found->getAttributes());
        $keys = array_map(static fn ($a): string => $a->getKey(), $found->getAttributes()->toArray());
        self::assertContains('Новое', $keys);
        self::assertNotContains('Старое', $keys);
    }

    public function testDeleteCascadesAttributesAndImages(): void
    {
        $repository = new ProductRepository(self::$em);

        $product = new Product(externalCode: 'CRUD-003', name: 'Удаляемый', price: '10.00', discount: null);
        $product->replaceAttributes(['A' => '1']);
        $product->addImage('http://example.com/x.jpg', null);
        $repository->save($product);
        self::$em->flush();
        $id = $product->getId();

        $repository->remove($product);
        self::$em->flush();

        self::assertNull($repository->findById($id));

        $attributeCount = (int) self::$em->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(\App\Domain\Entity\ProductAttribute::class, 'a')
            ->getQuery()
            ->getSingleScalarResult();
        $imageCount = (int) self::$em->createQueryBuilder()
            ->select('COUNT(i.id)')
            ->from(\App\Domain\Entity\ProductImage::class, 'i')
            ->getQuery()
            ->getSingleScalarResult();

        self::assertSame(0, $attributeCount);
        self::assertSame(0, $imageCount);
    }

    public function testFindPaginatedWithFilters(): void
    {
        $repository = new ProductRepository(self::$em);

        foreach (
            [
                ['P-1', 'Футболка белая', '1000.00'],
                ['P-2', 'Футболка чёрная', '2000.00'],
                ['P-3', 'Джинсы синие', '3000.00'],
            ] as [$code, $name, $price]
        ) {
            $product = new Product(externalCode: $code, name: $name, price: $price, discount: null);
            $repository->save($product);
        }
        self::$em->flush();

        // без фильтров
        $result = $repository->findPaginated(1, 2, []);
        self::assertCount(2, $result['items']);
        self::assertSame(3, $result['total']);

        // вторая страница
        $page2 = $repository->findPaginated(2, 2, []);
        self::assertCount(1, $page2['items']);

        // фильтр по name (LIKE; в PostgreSQL сравнение регистронезависимо через LOWER)
        $byName = $repository->findPaginated(1, 20, ['name' => 'Футболка']);
        self::assertSame(2, $byName['total']);

        // диапазон цены
        $byPrice = $repository->findPaginated(1, 20, ['price_min' => 1500.0, 'price_max' => 3000.0]);
        self::assertSame(2, $byPrice['total']);
        $prices = array_map(static fn (Product $p): string => $p->getPrice(), $byPrice['items']);
        sort($prices);
        self::assertSame(['2000.00', '3000.00'], $prices);
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'product_attributes')]
#[ORM\Index(name: 'idx_attribute_product', columns: ['product_id'])]
class ProductAttribute
{
    public function __construct(
        #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
        private int $id,
        #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'attributes')]
        #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private Product $product,
        #[ORM\Column(name: '`key`', type: 'string', length: 255)]
        private string $key,
        #[ORM\Column(type: 'text')]
        private string $value,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    /** @return array{key: string, value: string} */
    public function toArray(): array
    {
        return ['key' => $this->key, 'value' => $this->value];
    }
}

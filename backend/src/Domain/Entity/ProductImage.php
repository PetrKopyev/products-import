<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'product_images')]
#[ORM\Index(name: 'idx_image_product', columns: ['product_id'])]
class ProductImage
{
    public function __construct(
        #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
        private int $id,
        #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'images')]
        #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private Product $product,
        #[ORM\Column(type: 'text')]
        private string $url,
        #[ORM\Column(type: 'string', length: 255, nullable: true)]
        private ?string $path,
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

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    /** @return array{id: int, url: string, path: string|null} */
    public function toArray(): array
    {
        return ['id' => $this->id, 'url' => $this->url, 'path' => $this->path];
    }
}

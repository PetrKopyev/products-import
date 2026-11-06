<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'products')]
class Product
{
    /** @var Collection<int, ProductAttribute> */
    #[ORM\OneToMany(targetEntity: ProductAttribute::class, mappedBy: 'product', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $attributes;

    /** @var Collection<int, ProductImage> */
    #[ORM\OneToMany(targetEntity: ProductImage::class, mappedBy: 'product', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $images;

    public function __construct(
        #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
        private int $id = 0,
        #[ORM\Column(name: 'external_code', type: 'string', length: 64, unique: true)]
        private string $externalCode = '',
        #[ORM\Column(type: 'string', length: 512)]
        private string $name = '',
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $description = null,
        #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
        private string $price = '0.00',
        #[ORM\Column(type: 'decimal', precision: 6, scale: 2, nullable: true)]
        private ?string $discount = null,
        #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
        private \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
        #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
        private \DateTimeImmutable $updatedAt = new \DateTimeImmutable(),
    ) {
        $this->attributes = new ArrayCollection();
        $this->images = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getExternalCode(): string
    {
        return $this->externalCode;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Цена продажи (decimal из БД приходит строкой, нормализуем до 2 знаков —
     * SQLite возвращает '200' вместо '200.00').
     */
    public function getPrice(): string
    {
        return number_format((float) $this->price, 2, '.', '');
    }

    /**
     * Скидка в %, вычисляется при импорте как (цена продажи − закупочная) / цена продажи × 100.
     */
    public function getDiscount(): ?string
    {
        return $this->discount !== null ? number_format((float) $this->discount, 2, '.', '') : null;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, ProductAttribute> */
    public function getAttributes(): Collection
    {
        return $this->attributes;
    }

    /** @return Collection<int, ProductImage> */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function update(
        string $name,
        ?string $description,
        string $price,
        ?string $discount,
    ): void {
        $this->name = $name;
        $this->description = $description;
        $this->price = $price;
        $this->discount = $discount;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function replaceAttributes(array $attributes): void
    {
        foreach ($this->attributes as $existing) {
            $this->attributes->removeElement($existing);
        }

        foreach ($attributes as $key => $value) {
            $attribute = new ProductAttribute(id: 0, product: $this, key: (string) $key, value: (string) $value);
            $this->attributes->add($attribute);
        }
    }

    public function addImage(string $url, ?string $path): void
    {
        $this->images->add(new ProductImage(id: 0, product: $this, url: $url, path: $path));
    }

    public function clearImages(): void
    {
        foreach ($this->images as $image) {
            $this->images->removeElement($image);
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'externalCode' => $this->externalCode,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->getPrice(),
            'discount' => $this->getDiscount() !== null ? (float) $this->getDiscount() : null,
            'createdAt' => $this->createdAt->format(\DateTimeInterface::ATOM),
            'updatedAt' => $this->updatedAt->format(\DateTimeInterface::ATOM),
            'attributes' => array_map(
                static fn (ProductAttribute $a): array => $a->toArray(),
                $this->attributes->toArray(),
            ),
            'images' => array_map(
                static fn (ProductImage $i): array => $i->toArray(),
                $this->images->toArray(),
            ),
        ];
    }
}

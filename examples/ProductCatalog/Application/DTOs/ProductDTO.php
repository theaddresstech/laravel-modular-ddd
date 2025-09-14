<?php

declare(strict_types=1);

namespace Modules\ProductCatalog\Application\DTOs;

use Modules\ProductCatalog\Domain\Models\Product;

readonly class ProductDTO
{
    public function __construct(
        public string $id,
        public string $name,
        public string $description,
        public array $price,
        public string $status,
        public ?string $categoryId,
        public array $images,
        public array $attributes,
        public string $createdAt,
        public ?string $updatedAt,
        public ?string $publishedAt
    ) {}

    public static function fromAggregate(Product $product): self
    {
        return new self(
            $product->getId()->toString(),
            $product->getName(),
            $product->getDescription(),
            [
                'amount' => $product->getPrice()->getAmount(),
                'currency' => $product->getPrice()->getCurrency(),
                'formatted' => $product->getPrice()->format(),
                'float_amount' => $product->getPrice()->getFloatAmount(),
            ],
            $product->getStatus()->value,
            $product->getCategoryId()?->toString(),
            $product->getImages(),
            $product->getAttributes(),
            $product->getCreatedAt()->format('Y-m-d H:i:s'),
            $product->getUpdatedAt()?->format('Y-m-d H:i:s'),
            $product->getPublishedAt()?->format('Y-m-d H:i:s')
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'],
            $data['name'],
            $data['description'],
            $data['price'] ?? [],
            $data['status'],
            $data['category_id'] ?? null,
            $data['images'] ?? [],
            $data['attributes'] ?? [],
            $data['created_at'],
            $data['updated_at'] ?? null,
            $data['published_at'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'status' => $this->status,
            'category_id' => $this->categoryId,
            'images' => $this->images,
            'attributes' => $this->attributes,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'published_at' => $this->publishedAt,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function getPrimaryImage(): ?string
    {
        $primaryImages = array_filter(
            $this->images,
            fn($image) => $image['is_primary'] ?? false
        );

        if (!empty($primaryImages)) {
            return array_values($primaryImages)[0]['url'];
        }

        // Return first image if no primary is set
        return !empty($this->images) ? $this->images[0]['url'] : null;
    }

    public function getFormattedPrice(): string
    {
        return $this->price['formatted'] ?? '';
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'published' => 'Published',
            'archived' => 'Archived',
            'out_of_stock' => 'Out of Stock',
            'discontinued' => 'Discontinued',
            default => ucfirst($this->status),
        };
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function hasImages(): bool
    {
        return !empty($this->images);
    }

    public function hasAttributes(): bool
    {
        return !empty($this->attributes);
    }

    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    public function withStatus(string $status): self
    {
        return new self(
            $this->id,
            $this->name,
            $this->description,
            $this->price,
            $status,
            $this->categoryId,
            $this->images,
            $this->attributes,
            $this->createdAt,
            $this->updatedAt,
            $this->publishedAt
        );
    }

    public function withImages(array $images): self
    {
        return new self(
            $this->id,
            $this->name,
            $this->description,
            $this->price,
            $this->status,
            $this->categoryId,
            $images,
            $this->attributes,
            $this->createdAt,
            $this->updatedAt,
            $this->publishedAt
        );
    }
}
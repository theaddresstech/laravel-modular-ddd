<?php

declare(strict_types=1);

namespace Modules\ProductCatalog\Domain\Models;

use Modules\ProductCatalog\Domain\ValueObjects\ProductId;
use Modules\ProductCatalog\Domain\ValueObjects\CategoryId;
use Modules\ProductCatalog\Domain\ValueObjects\Money;
use Modules\ProductCatalog\Domain\ValueObjects\ProductStatus;
use Modules\ProductCatalog\Domain\Events\ProductCreated;
use Modules\ProductCatalog\Domain\Events\ProductUpdated;
use Modules\ProductCatalog\Domain\Events\ProductPriceChanged;
use Modules\ProductCatalog\Domain\Events\ProductPublished;
use Modules\ProductCatalog\Domain\Exceptions\ProductAlreadyPublishedException;
use TaiCrm\LaravelModularDdd\Foundation\AggregateRoot;

class Product extends AggregateRoot
{
    private function __construct(
        private ProductId $id,
        private string $name,
        private string $description,
        private Money $price,
        private ProductStatus $status,
        private ?CategoryId $categoryId = null,
        private array $images = [],
        private array $attributes = [],
        private \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
        private ?\DateTimeImmutable $updatedAt = null,
        private ?\DateTimeImmutable $publishedAt = null
    ) {
        parent::__construct();
    }

    public static function create(
        ProductId $id,
        string $name,
        string $description,
        Money $price,
        ?CategoryId $categoryId = null
    ): self {
        $product = new self(
            $id,
            $name,
            $description,
            $price,
            ProductStatus::Draft,
            $categoryId,
            [],
            [],
            new \DateTimeImmutable()
        );

        $product->recordEvent(new ProductCreated(
            $id,
            $name,
            $description,
            $price,
            $categoryId,
            $product->createdAt
        ));

        return $product;
    }

    public static function reconstitute(
        ProductId $id,
        string $name,
        string $description,
        Money $price,
        ProductStatus $status,
        ?CategoryId $categoryId = null,
        array $images = [],
        array $attributes = [],
        \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
        ?\DateTimeImmutable $updatedAt = null,
        ?\DateTimeImmutable $publishedAt = null
    ): self {
        return new self(
            $id,
            $name,
            $description,
            $price,
            $status,
            $categoryId,
            $images,
            $attributes,
            $createdAt,
            $updatedAt,
            $publishedAt
        );
    }

    public function updateDetails(string $name, string $description): void
    {
        if ($this->name === $name && $this->description === $description) {
            return;
        }

        $oldName = $this->name;
        $oldDescription = $this->description;

        $this->name = $name;
        $this->description = $description;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new ProductUpdated(
            $this->id,
            $oldName,
            $name,
            $oldDescription,
            $description,
            $this->updatedAt
        ));
    }

    public function changePrice(Money $newPrice): void
    {
        if ($this->price->equals($newPrice)) {
            return;
        }

        $oldPrice = $this->price;
        $this->price = $newPrice;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new ProductPriceChanged(
            $this->id,
            $oldPrice,
            $newPrice,
            $this->updatedAt
        ));
    }

    public function assignToCategory(CategoryId $categoryId): void
    {
        if ($this->categoryId && $this->categoryId->equals($categoryId)) {
            return;
        }

        $this->categoryId = $categoryId;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function removeFromCategory(): void
    {
        if ($this->categoryId === null) {
            return;
        }

        $this->categoryId = null;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function addImage(string $imageUrl, bool $isPrimary = false): void
    {
        // If this is the primary image, mark others as non-primary
        if ($isPrimary) {
            $this->images = array_map(
                fn($image) => ['url' => $image['url'], 'is_primary' => false],
                $this->images
            );
        }

        $this->images[] = [
            'url' => $imageUrl,
            'is_primary' => $isPrimary || empty($this->images),
        ];

        $this->updatedAt = new \DateTimeImmutable();
    }

    public function removeImage(string $imageUrl): void
    {
        $this->images = array_filter(
            $this->images,
            fn($image) => $image['url'] !== $imageUrl
        );

        // If we removed the primary image, make the first remaining image primary
        if (!empty($this->images) && !$this->hasPrimaryImage()) {
            $this->images[0]['is_primary'] = true;
        }

        $this->updatedAt = new \DateTimeImmutable();
    }

    public function setAttribute(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function removeAttribute(string $name): void
    {
        unset($this->attributes[$name]);
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function publish(): void
    {
        if ($this->status === ProductStatus::Published) {
            throw new ProductAlreadyPublishedException($this->id);
        }

        $this->status = ProductStatus::Published;
        $this->publishedAt = new \DateTimeImmutable();
        $this->updatedAt = $this->publishedAt;

        $this->recordEvent(new ProductPublished(
            $this->id,
            $this->publishedAt
        ));
    }

    public function unpublish(): void
    {
        if ($this->status === ProductStatus::Draft) {
            return;
        }

        $this->status = ProductStatus::Draft;
        $this->publishedAt = null;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function archive(): void
    {
        $this->status = ProductStatus::Archived;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function isPublished(): bool
    {
        return $this->status === ProductStatus::Published;
    }

    public function isDraft(): bool
    {
        return $this->status === ProductStatus::Draft;
    }

    public function isArchived(): bool
    {
        return $this->status === ProductStatus::Archived;
    }

    public function getPrimaryImage(): ?string
    {
        $primaryImage = array_filter(
            $this->images,
            fn($image) => $image['is_primary'] ?? false
        );

        return !empty($primaryImage) ? array_values($primaryImage)[0]['url'] : null;
    }

    private function hasPrimaryImage(): bool
    {
        return !empty(array_filter(
            $this->images,
            fn($image) => $image['is_primary'] ?? false
        ));
    }

    // Getters
    public function getId(): ProductId
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getPrice(): Money
    {
        return $this->price;
    }

    public function getStatus(): ProductStatus
    {
        return $this->status;
    }

    public function getCategoryId(): ?CategoryId
    {
        return $this->categoryId;
    }

    public function getImages(): array
    {
        return $this->images;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id->toString(),
            'name' => $this->name,
            'description' => $this->description,
            'price' => [
                'amount' => $this->price->getAmount(),
                'currency' => $this->price->getCurrency(),
            ],
            'status' => $this->status->value,
            'category_id' => $this->categoryId?->toString(),
            'images' => $this->images,
            'attributes' => $this->attributes,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
            'published_at' => $this->publishedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
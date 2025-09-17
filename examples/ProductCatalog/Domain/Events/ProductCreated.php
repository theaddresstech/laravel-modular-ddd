<?php

declare(strict_types=1);

namespace Modules\ProductCatalog\Domain\Events;

use DateTimeImmutable;
use Modules\ProductCatalog\Domain\ValueObjects\CategoryId;
use Modules\ProductCatalog\Domain\ValueObjects\Money;
use Modules\ProductCatalog\Domain\ValueObjects\ProductId;
use TaiCrm\LaravelModularDdd\Foundation\DomainEvent;

readonly class ProductCreated extends DomainEvent
{
    public function __construct(
        public ProductId $productId,
        public string $name,
        public string $description,
        public Money $price,
        public ?CategoryId $categoryId = null,
        ?DateTimeImmutable $occurredOn = null,
    ) {
        parent::__construct($occurredOn);
    }

    public function getAggregateId(): string
    {
        return $this->productId->getValue();
    }

    public function getEventName(): string
    {
        return 'product.created';
    }

    public function getPayload(): array
    {
        return [
            'product_id' => $this->productId->getValue(),
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price->toArray(),
            'category_id' => $this->categoryId?->getValue(),
        ];
    }
}

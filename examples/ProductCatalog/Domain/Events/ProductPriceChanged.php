<?php

declare(strict_types=1);

namespace Modules\ProductCatalog\Domain\Events;

use DateTimeImmutable;
use Modules\ProductCatalog\Domain\ValueObjects\Money;
use Modules\ProductCatalog\Domain\ValueObjects\ProductId;
use TaiCrm\LaravelModularDdd\Foundation\DomainEvent;

readonly class ProductPriceChanged extends DomainEvent
{
    public function __construct(
        public ProductId $productId,
        public Money $oldPrice,
        public Money $newPrice,
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
        return 'product.price_changed';
    }

    public function getPayload(): array
    {
        return [
            'product_id' => $this->productId->getValue(),
            'old_price' => $this->oldPrice->toArray(),
            'new_price' => $this->newPrice->toArray(),
        ];
    }
}

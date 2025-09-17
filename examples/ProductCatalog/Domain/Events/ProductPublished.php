<?php

declare(strict_types=1);

namespace Modules\ProductCatalog\Domain\Events;

use DateTimeImmutable;
use Modules\ProductCatalog\Domain\ValueObjects\ProductId;
use TaiCrm\LaravelModularDdd\Foundation\DomainEvent;

readonly class ProductPublished extends DomainEvent
{
    public function __construct(
        public ProductId $productId,
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
        return 'product.published';
    }

    public function getPayload(): array
    {
        return [
            'product_id' => $this->productId->getValue(),
        ];
    }
}

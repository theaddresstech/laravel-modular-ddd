<?php

declare(strict_types=1);

namespace Modules\ProductCatalog\Domain\Events;

use DateTimeImmutable;
use Modules\ProductCatalog\Domain\ValueObjects\ProductId;
use TaiCrm\LaravelModularDdd\Foundation\DomainEvent;

readonly class ProductUpdated extends DomainEvent
{
    public function __construct(
        public ProductId $productId,
        public array $changes,
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
        return 'product.updated';
    }

    public function getPayload(): array
    {
        return [
            'product_id' => $this->productId->getValue(),
            'changes' => $this->changes,
        ];
    }
}

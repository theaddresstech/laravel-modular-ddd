<?php

declare(strict_types=1);

namespace Modules\ProductCatalog\Domain\ValueObjects;

enum ProductStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
    case OutOfStock = 'out_of_stock';
    case Discontinued = 'discontinued';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Archived => 'Archived',
            self::OutOfStock => 'Out of Stock',
            self::Discontinued => 'Discontinued',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Draft => 'Product is being prepared and not visible to customers',
            self::Published => 'Product is live and available for purchase',
            self::Archived => 'Product is no longer active but kept for historical purposes',
            self::OutOfStock => 'Product is temporarily unavailable',
            self::Discontinued => 'Product is permanently discontinued',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'yellow',
            self::Published => 'green',
            self::Archived => 'gray',
            self::OutOfStock => 'orange',
            self::Discontinued => 'red',
        };
    }

    public function isAvailableForPurchase(): bool
    {
        return $this === self::Published;
    }

    public function isVisibleToCustomers(): bool
    {
        return in_array($this, [self::Published, self::OutOfStock]);
    }

    public function canBeEdited(): bool
    {
        return in_array($this, [self::Draft, self::Published, self::OutOfStock]);
    }

    public function canTransitionTo(self $newStatus): bool
    {
        return match ($this) {
            self::Draft => in_array($newStatus, [self::Published, self::Archived]),
            self::Published => in_array($newStatus, [self::Draft, self::OutOfStock, self::Archived, self::Discontinued]),
            self::OutOfStock => in_array($newStatus, [self::Published, self::Discontinued, self::Archived]),
            self::Archived => $newStatus === self::Draft,
            self::Discontinued => false, // Cannot transition from discontinued
        };
    }

    public static function getAvailableStatuses(): array
    {
        return [
            self::Draft->value => self::Draft->getLabel(),
            self::Published->value => self::Published->getLabel(),
            self::Archived->value => self::Archived->getLabel(),
            self::OutOfStock->value => self::OutOfStock->getLabel(),
            self::Discontinued->value => self::Discontinued->getLabel(),
        ];
    }

    public static function getPublicStatuses(): array
    {
        return array_filter(
            self::getAvailableStatuses(),
            static fn ($status) => self::from(array_search($status, self::getAvailableStatuses()))->isVisibleToCustomers(),
        );
    }
}

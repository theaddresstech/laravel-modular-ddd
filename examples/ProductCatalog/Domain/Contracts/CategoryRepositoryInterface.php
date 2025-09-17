<?php

declare(strict_types=1);

namespace Modules\ProductCatalog\Domain\Contracts;

use Modules\ProductCatalog\Domain\ValueObjects\CategoryId;

interface CategoryRepositoryInterface
{
    public function existsById(CategoryId $id): bool;

    public function findById(CategoryId $id): ?array;

    public function findAll(): array;
}

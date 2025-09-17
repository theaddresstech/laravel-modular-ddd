<?php

declare(strict_types=1);

namespace Modules\ProductCatalog\Domain\Contracts;

use Modules\ProductCatalog\Domain\Models\Product;
use Modules\ProductCatalog\Domain\ValueObjects\ProductId;

interface ProductRepositoryInterface
{
    public function save(Product $product): void;

    public function findById(ProductId $id): ?Product;

    public function findByName(string $name): ?Product;

    public function findAll(): array;

    public function delete(ProductId $id): void;

    public function existsById(ProductId $id): bool;

    public function existsByName(string $name): bool;
}

<?php

declare(strict_types=1);

namespace Modules\ProductCatalog\Application\Commands\CreateProduct;

use Modules\ProductCatalog\Domain\Models\Product;
use Modules\ProductCatalog\Domain\ValueObjects\ProductId;
use Modules\ProductCatalog\Domain\ValueObjects\CategoryId;
use Modules\ProductCatalog\Domain\ValueObjects\Money;
use Modules\ProductCatalog\Domain\Repositories\ProductRepositoryInterface;
use Modules\ProductCatalog\Domain\Repositories\CategoryRepositoryInterface;
use Modules\ProductCatalog\Domain\Exceptions\CategoryNotFoundException;
use Modules\ProductCatalog\Application\DTOs\ProductDTO;
use TaiCrm\LaravelModularDdd\Communication\EventBus;
use Psr\Log\LoggerInterface;

readonly class CreateProductHandler
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private CategoryRepositoryInterface $categoryRepository,
        private EventBus $eventBus,
        private LoggerInterface $logger
    ) {}

    public function handle(CreateProductCommand $command): ProductDTO
    {
        // Validate command
        $validationErrors = $command->validate();
        if (!empty($validationErrors)) {
            throw new \InvalidArgumentException(
                'Invalid command data: ' . json_encode($validationErrors)
            );
        }

        $this->logger->info('Creating new product', $command->toArray());

        try {
            // Validate category exists if provided
            $categoryId = null;
            if ($command->categoryId) {
                $categoryId = CategoryId::fromString($command->categoryId);
                $category = $this->categoryRepository->findById($categoryId);

                if (!$category) {
                    throw new CategoryNotFoundException($categoryId);
                }
            }

            // Create the product
            $productId = ProductId::generate();
            $price = Money::fromAmount($command->priceAmount, strtoupper($command->currency));

            $product = Product::create(
                $productId,
                trim($command->name),
                trim($command->description),
                $price,
                $categoryId
            );

            // Add images if provided
            foreach ($command->images as $index => $imageUrl) {
                $isPrimary = $index === 0; // First image is primary
                $product->addImage($imageUrl, $isPrimary);
            }

            // Add attributes if provided
            foreach ($command->attributes as $name => $value) {
                $product->setAttribute($name, $value);
            }

            // Save the product
            $this->productRepository->save($product);

            // Dispatch domain events
            $events = $product->releaseEvents();
            $this->eventBus->dispatchMany($events);

            $this->logger->info('Product created successfully', [
                'product_id' => $productId->toString(),
                'name' => $command->name,
                'price' => $price->format(),
                'events_dispatched' => count($events),
            ]);

            // Return DTO
            return ProductDTO::fromAggregate($product);

        } catch (\Exception $e) {
            $this->logger->error('Failed to create product', [
                'command' => $command->toArray(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
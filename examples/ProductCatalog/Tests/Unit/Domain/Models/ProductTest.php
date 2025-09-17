<?php

declare(strict_types=1);

namespace Modules\ProductCatalog\Tests\Unit\Domain\Models;

use DateTimeImmutable;
use Modules\ProductCatalog\Domain\Events\ProductCreated;
use Modules\ProductCatalog\Domain\Events\ProductPriceChanged;
use Modules\ProductCatalog\Domain\Events\ProductPublished;
use Modules\ProductCatalog\Domain\Events\ProductUpdated;
use Modules\ProductCatalog\Domain\Exceptions\ProductAlreadyPublishedException;
use Modules\ProductCatalog\Domain\Models\Product;
use Modules\ProductCatalog\Domain\ValueObjects\CategoryId;
use Modules\ProductCatalog\Domain\ValueObjects\Money;
use Modules\ProductCatalog\Domain\ValueObjects\ProductId;
use Modules\ProductCatalog\Domain\ValueObjects\ProductStatus;
use PHPUnit\Framework\TestCase;

class ProductTest extends TestCase
{
    public function testCanCreateProduct(): void
    {
        // Arrange
        $id = ProductId::generate();
        $name = 'Test Product';
        $description = 'A test product description';
        $price = Money::fromAmount(1999, 'USD');
        $categoryId = CategoryId::generate();

        // Act
        $product = Product::create($id, $name, $description, $price, $categoryId);

        // Assert
        $this->assertSame($id, $product->getId());
        $this->assertSame($name, $product->getName());
        $this->assertSame($description, $product->getDescription());
        $this->assertSame($price, $product->getPrice());
        $this->assertSame(ProductStatus::Draft, $product->getStatus());
        $this->assertSame($categoryId, $product->getCategoryId());
        $this->assertTrue($product->isDraft());
        $this->assertFalse($product->isPublished());
        $this->assertTrue($product->hasUncommittedEvents());

        // Check domain event
        $events = $product->getUncommittedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(ProductCreated::class, $events[0]);
    }

    public function testCanCreateProductWithoutCategory(): void
    {
        // Arrange
        $id = ProductId::generate();
        $name = 'Test Product';
        $description = 'A test product description';
        $price = Money::fromAmount(1999, 'USD');

        // Act
        $product = Product::create($id, $name, $description, $price);

        // Assert
        $this->assertNull($product->getCategoryId());
    }

    public function testCanUpdateProductDetails(): void
    {
        // Arrange
        $product = $this->createProduct();
        $newName = 'Updated Product Name';
        $newDescription = 'Updated product description';

        // Act
        $product->updateDetails($newName, $newDescription);

        // Assert
        $this->assertSame($newName, $product->getName());
        $this->assertSame($newDescription, $product->getDescription());
        $this->assertNotNull($product->getUpdatedAt());
        $this->assertCount(2, $product->getUncommittedEvents()); // Created + Updated

        // Check domain event
        $events = $product->getUncommittedEvents();
        $this->assertInstanceOf(ProductUpdated::class, $events[1]);
    }

    public function testDoesNotUpdateWhenDetailsAreSame(): void
    {
        // Arrange
        $product = $this->createProduct();
        $originalName = $product->getName();
        $originalDescription = $product->getDescription();
        $originalEventsCount = count($product->getUncommittedEvents());

        // Act
        $product->updateDetails($originalName, $originalDescription);

        // Assert
        $this->assertSame($originalName, $product->getName());
        $this->assertSame($originalDescription, $product->getDescription());
        $this->assertCount($originalEventsCount, $product->getUncommittedEvents());
    }

    public function testCanChangePrice(): void
    {
        // Arrange
        $product = $this->createProduct();
        $newPrice = Money::fromAmount(2999, 'USD');

        // Act
        $product->changePrice($newPrice);

        // Assert
        $this->assertSame($newPrice, $product->getPrice());
        $this->assertNotNull($product->getUpdatedAt());
        $this->assertCount(2, $product->getUncommittedEvents()); // Created + PriceChanged

        // Check domain event
        $events = $product->getUncommittedEvents();
        $this->assertInstanceOf(ProductPriceChanged::class, $events[1]);
    }

    public function testDoesNotChangePriceWhenSame(): void
    {
        // Arrange
        $product = $this->createProduct();
        $originalPrice = $product->getPrice();
        $originalEventsCount = count($product->getUncommittedEvents());

        // Act
        $product->changePrice($originalPrice);

        // Assert
        $this->assertSame($originalPrice, $product->getPrice());
        $this->assertCount($originalEventsCount, $product->getUncommittedEvents());
    }

    public function testCanAssignToCategory(): void
    {
        // Arrange
        $product = $this->createProduct();
        $categoryId = CategoryId::generate();

        // Act
        $product->assignToCategory($categoryId);

        // Assert
        $this->assertSame($categoryId, $product->getCategoryId());
        $this->assertNotNull($product->getUpdatedAt());
    }

    public function testCanRemoveFromCategory(): void
    {
        // Arrange
        $categoryId = CategoryId::generate();
        $product = $this->createProduct($categoryId);

        // Act
        $product->removeFromCategory();

        // Assert
        $this->assertNull($product->getCategoryId());
        $this->assertNotNull($product->getUpdatedAt());
    }

    public function testCanAddImages(): void
    {
        // Arrange
        $product = $this->createProduct();
        $imageUrl1 = 'https://example.com/image1.jpg';
        $imageUrl2 = 'https://example.com/image2.jpg';

        // Act
        $product->addImage($imageUrl1, true); // Primary image
        $product->addImage($imageUrl2, false);

        // Assert
        $images = $product->getImages();
        $this->assertCount(2, $images);
        $this->assertSame($imageUrl1, $product->getPrimaryImage());
        $this->assertTrue($images[0]['is_primary']);
        $this->assertFalse($images[1]['is_primary']);
    }

    public function testFirstImageBecomesPrimaryByDefault(): void
    {
        // Arrange
        $product = $this->createProduct();
        $imageUrl = 'https://example.com/image.jpg';

        // Act
        $product->addImage($imageUrl);

        // Assert
        $images = $product->getImages();
        $this->assertTrue($images[0]['is_primary']);
        $this->assertSame($imageUrl, $product->getPrimaryImage());
    }

    public function testCanRemoveImages(): void
    {
        // Arrange
        $product = $this->createProduct();
        $imageUrl1 = 'https://example.com/image1.jpg';
        $imageUrl2 = 'https://example.com/image2.jpg';
        $product->addImage($imageUrl1);
        $product->addImage($imageUrl2);

        // Act
        $product->removeImage($imageUrl1);

        // Assert
        $images = $product->getImages();
        $this->assertCount(1, $images);
        $this->assertSame($imageUrl2, $images[0]['url']);
    }

    public function testCanSetAndGetAttributes(): void
    {
        // Arrange
        $product = $this->createProduct();

        // Act
        $product->setAttribute('color', 'red');
        $product->setAttribute('size', 'large');

        // Assert
        $this->assertSame('red', $product->getAttribute('color'));
        $this->assertSame('large', $product->getAttribute('size'));
        $this->assertNull($product->getAttribute('nonexistent'));
        $this->assertSame('default', $product->getAttribute('nonexistent', 'default'));

        $attributes = $product->getAttributes();
        $this->assertCount(2, $attributes);
        $this->assertSame('red', $attributes['color']);
        $this->assertSame('large', $attributes['size']);
    }

    public function testCanRemoveAttributes(): void
    {
        // Arrange
        $product = $this->createProduct();
        $product->setAttribute('color', 'red');
        $product->setAttribute('size', 'large');

        // Act
        $product->removeAttribute('color');

        // Assert
        $attributes = $product->getAttributes();
        $this->assertCount(1, $attributes);
        $this->assertArrayNotHasKey('color', $attributes);
        $this->assertArrayHasKey('size', $attributes);
    }

    public function testCanPublishProduct(): void
    {
        // Arrange
        $product = $this->createProduct();

        // Act
        $product->publish();

        // Assert
        $this->assertTrue($product->isPublished());
        $this->assertSame(ProductStatus::Published, $product->getStatus());
        $this->assertNotNull($product->getPublishedAt());
        $this->assertNotNull($product->getUpdatedAt());
        $this->assertCount(2, $product->getUncommittedEvents()); // Created + Published

        // Check domain event
        $events = $product->getUncommittedEvents();
        $this->assertInstanceOf(ProductPublished::class, $events[1]);
    }

    public function testCannotPublishAlreadyPublishedProduct(): void
    {
        // Arrange
        $product = $this->createProduct();
        $product->publish();

        // Assert
        $this->expectException(ProductAlreadyPublishedException::class);

        // Act
        $product->publish();
    }

    public function testCanUnpublishProduct(): void
    {
        // Arrange
        $product = $this->createProduct();
        $product->publish();

        // Act
        $product->unpublish();

        // Assert
        $this->assertTrue($product->isDraft());
        $this->assertFalse($product->isPublished());
        $this->assertNull($product->getPublishedAt());
    }

    public function testCanArchiveProduct(): void
    {
        // Arrange
        $product = $this->createProduct();

        // Act
        $product->archive();

        // Assert
        $this->assertTrue($product->isArchived());
        $this->assertSame(ProductStatus::Archived, $product->getStatus());
        $this->assertNotNull($product->getUpdatedAt());
    }

    public function testCanConvertToArray(): void
    {
        // Arrange
        $product = $this->createProduct();

        // Act
        $array = $product->toArray();

        // Assert
        $this->assertIsArray($array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('description', $array);
        $this->assertArrayHasKey('price', $array);
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('created_at', $array);

        $this->assertSame($product->getId()->toString(), $array['id']);
        $this->assertSame($product->getName(), $array['name']);
        $this->assertSame($product->getStatus()->value, $array['status']);
    }

    public function testCanReconstituteProduct(): void
    {
        // Arrange
        $id = ProductId::generate();
        $name = 'Test Product';
        $description = 'Description';
        $price = Money::fromAmount(1999, 'USD');
        $status = ProductStatus::Published;
        $createdAt = new DateTimeImmutable();
        $updatedAt = new DateTimeImmutable();

        // Act
        $product = Product::reconstitute(
            $id,
            $name,
            $description,
            $price,
            $status,
            null,
            [],
            [],
            $createdAt,
            $updatedAt,
        );

        // Assert
        $this->assertSame($id, $product->getId());
        $this->assertSame($name, $product->getName());
        $this->assertSame($description, $product->getDescription());
        $this->assertSame($price, $product->getPrice());
        $this->assertSame($status, $product->getStatus());
        $this->assertSame($createdAt, $product->getCreatedAt());
        $this->assertSame($updatedAt, $product->getUpdatedAt());
        $this->assertFalse($product->hasUncommittedEvents()); // Reconstituted objects have no events
    }

    private function createProduct(?CategoryId $categoryId = null): Product
    {
        return Product::create(
            ProductId::generate(),
            'Test Product',
            'A test product description',
            Money::fromAmount(1999, 'USD'),
            $categoryId,
        );
    }
}

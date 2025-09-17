<?php

declare(strict_types=1);

namespace Modules\ProductCatalog\Application\Commands\CreateProduct;

readonly class CreateProductCommand
{
    public function __construct(
        public string $name,
        public string $description,
        public int $priceAmount,
        public string $currency,
        public ?string $categoryId = null,
        public array $images = [],
        public array $attributes = [],
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'price_amount' => $this->priceAmount,
            'currency' => $this->currency,
            'category_id' => $this->categoryId,
            'images' => $this->images,
            'attributes' => $this->attributes,
        ];
    }

    public function validate(): array
    {
        $errors = [];

        if (empty(trim($this->name))) {
            $errors['name'] = 'Product name is required';
        }

        if (strlen($this->name) > 255) {
            $errors['name'] = 'Product name cannot exceed 255 characters';
        }

        if (empty(trim($this->description))) {
            $errors['description'] = 'Product description is required';
        }

        if ($this->priceAmount < 0) {
            $errors['price_amount'] = 'Price amount cannot be negative';
        }

        if (empty($this->currency) || strlen($this->currency) !== 3) {
            $errors['currency'] = 'Currency must be a valid 3-letter ISO code';
        }

        if ($this->categoryId && !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $this->categoryId)) {
            $errors['category_id'] = 'Category ID must be a valid UUID';
        }

        if (!is_array($this->images)) {
            $errors['images'] = 'Images must be an array';
        }

        if (!is_array($this->attributes)) {
            $errors['attributes'] = 'Attributes must be an array';
        }

        return $errors;
    }

    public function isValid(): bool
    {
        return empty($this->validate());
    }
}

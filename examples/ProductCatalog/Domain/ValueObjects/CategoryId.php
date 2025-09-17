<?php

declare(strict_types=1);

namespace Modules\ProductCatalog\Domain\ValueObjects;

use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use TaiCrm\LaravelModularDdd\Foundation\ValueObject;

readonly class CategoryId extends ValueObject
{
    private UuidInterface $uuid;

    public function __construct(string $id)
    {
        $this->uuid = $this->validateAndCreateUuid($id);
    }

    public function __toString(): string
    {
        return $this->uuid->toString();
    }

    public static function generate(): self
    {
        return new self(Uuid::uuid4()->toString());
    }

    public static function fromString(string $id): self
    {
        return new self($id);
    }

    public function getValue(): string
    {
        return $this->uuid->toString();
    }

    public function toString(): string
    {
        return $this->uuid->toString();
    }

    public function equals(object $other): bool
    {
        return $other instanceof self && $this->uuid->equals($other->uuid);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->uuid->toString(),
        ];
    }

    private function validateAndCreateUuid(string $id): UuidInterface
    {
        if (empty($id)) {
            throw new InvalidArgumentException('Category ID cannot be empty');
        }

        if (!Uuid::isValid($id)) {
            throw new InvalidArgumentException('Category ID must be a valid UUID');
        }

        return Uuid::fromString($id);
    }
}

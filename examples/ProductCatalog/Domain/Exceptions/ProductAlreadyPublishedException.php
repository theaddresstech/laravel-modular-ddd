<?php

declare(strict_types=1);

namespace Modules\ProductCatalog\Domain\Exceptions;

use DomainException;
use Modules\ProductCatalog\Domain\ValueObjects\ProductId;
use Throwable;

class ProductAlreadyPublishedException extends DomainException
{
    public function __construct(ProductId $productId, int $code = 0, ?Throwable $previous = null)
    {
        $message = "Product with ID '{$productId->getValue()}' is already published";
        parent::__construct($message, $code, $previous);
    }
}

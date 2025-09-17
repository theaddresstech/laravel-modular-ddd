<?php

declare(strict_types=1);

namespace Modules\ProductCatalog\Domain\Exceptions;

use DomainException;
use Modules\ProductCatalog\Domain\ValueObjects\CategoryId;
use Throwable;

class CategoryNotFoundException extends DomainException
{
    public function __construct(CategoryId $categoryId, int $code = 0, ?Throwable $previous = null)
    {
        $message = "Category with ID '{$categoryId->getValue()}' was not found";
        parent::__construct($message, $code, $previous);
    }
}

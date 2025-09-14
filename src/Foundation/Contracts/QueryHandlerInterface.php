<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Foundation\Contracts;

interface QueryHandlerInterface
{
    /**
     * Handle the query and return the result.
     */
    public function handle(QueryInterface $query): mixed;
}
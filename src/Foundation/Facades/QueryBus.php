<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Foundation\Facades;

use Illuminate\Support\Facades\Facade;

class QueryBus extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \TaiCrm\LaravelModularDdd\Foundation\QueryBus::class;
    }
}

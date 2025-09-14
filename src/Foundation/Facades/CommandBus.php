<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Foundation\Facades;

use Illuminate\Support\Facades\Facade;

class CommandBus extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \TaiCrm\LaravelModularDdd\Foundation\CommandBus::class;
    }
}
<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Foundation\Contracts;

interface CommandHandlerInterface
{
    /**
     * Handle the command and return the result.
     */
    public function handle(CommandInterface $command): mixed;
}
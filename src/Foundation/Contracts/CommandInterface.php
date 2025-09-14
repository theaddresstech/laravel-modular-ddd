<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Foundation\Contracts;

interface CommandInterface
{
    /**
     * Get the command identifier for logging and tracing.
     */
    public function getCommandId(): string;

    /**
     * Get the command type/name.
     */
    public function getCommandType(): string;

    /**
     * Get command payload for logging and auditing.
     */
    public function getPayload(): array;

    /**
     * Validate the command before execution.
     */
    public function validate(): array;
}
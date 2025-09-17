<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Foundation;

use Illuminate\Container\Container;
use InvalidArgumentException;
use TaiCrm\LaravelModularDdd\Foundation\Contracts\CommandHandlerInterface;
use TaiCrm\LaravelModularDdd\Foundation\Contracts\CommandInterface;

class CommandBus
{
    private array $handlers = [];

    public function __construct(
        private Container $container,
    ) {}

    public function register(string $commandClass, string $handlerClass): void
    {
        if (!is_subclass_of($commandClass, CommandInterface::class)) {
            throw new InvalidArgumentException('Command class must implement CommandInterface');
        }

        if (!is_subclass_of($handlerClass, CommandHandlerInterface::class)) {
            throw new InvalidArgumentException('Handler class must implement CommandHandlerInterface');
        }

        $this->handlers[$commandClass] = $handlerClass;
    }

    public function dispatch(CommandInterface $command): mixed
    {
        if (!$command->isValid()) {
            throw new InvalidArgumentException(
                'Command validation failed: ' . json_encode($command->validate()),
            );
        }

        $commandClass = $command::class;

        if (!isset($this->handlers[$commandClass])) {
            throw new InvalidArgumentException("No handler registered for command: {$commandClass}");
        }

        $handlerClass = $this->handlers[$commandClass];
        $handler = $this->container->make($handlerClass);

        return $handler->handle($command);
    }

    public function getRegisteredHandlers(): array
    {
        return $this->handlers;
    }
}

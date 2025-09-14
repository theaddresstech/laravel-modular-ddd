<?php

if (!function_exists('dispatch_command')) {
    /**
     * Dispatch a command through the command bus.
     */
    function dispatch_command(\TaiCrm\LaravelModularDdd\Foundation\Contracts\CommandInterface $command): mixed
    {
        return app(\TaiCrm\LaravelModularDdd\Foundation\CommandBus::class)->dispatch($command);
    }
}

if (!function_exists('ask_query')) {
    /**
     * Ask a query through the query bus.
     */
    function ask_query(\TaiCrm\LaravelModularDdd\Foundation\Contracts\QueryInterface $query): mixed
    {
        return app(\TaiCrm\LaravelModularDdd\Foundation\QueryBus::class)->ask($query);
    }
}

if (!function_exists('register_command_handler')) {
    /**
     * Register a command handler.
     */
    function register_command_handler(string $commandClass, string $handlerClass): void
    {
        app(\TaiCrm\LaravelModularDdd\Foundation\CommandBus::class)->register($commandClass, $handlerClass);
    }
}

if (!function_exists('register_query_handler')) {
    /**
     * Register a query handler.
     */
    function register_query_handler(string $queryClass, string $handlerClass): void
    {
        app(\TaiCrm\LaravelModularDdd\Foundation\QueryBus::class)->register($queryClass, $handlerClass);
    }
}
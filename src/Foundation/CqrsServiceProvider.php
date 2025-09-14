<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Foundation;

use Illuminate\Support\ServiceProvider;

class CqrsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CommandBus::class);
        $this->app->singleton(QueryBus::class);
    }

    public function boot(): void
    {
        $this->loadHelpers();
        $this->registerModuleHandlers();
    }

    private function loadHelpers(): void
    {
        require_once __DIR__ . '/helpers.php';
    }

    private function registerModuleHandlers(): void
    {
        $commandBus = $this->app->make(CommandBus::class);
        $queryBus = $this->app->make(QueryBus::class);

        $modules = $this->getActiveModules();

        foreach ($modules as $module) {
            $this->registerCommandHandlers($commandBus, $module);
            $this->registerQueryHandlers($queryBus, $module);
        }
    }

    private function getActiveModules(): array
    {
        $moduleManager = $this->app->make(\TaiCrm\LaravelModularDdd\ModuleManager::class);
        return $moduleManager->getActiveModules();
    }

    private function registerCommandHandlers(CommandBus $commandBus, array $module): void
    {
        $commandsPath = $module['path'] . '/Application/Commands';
        $handlersPath = $module['path'] . '/Application/Handlers/Commands';

        if (!is_dir($commandsPath) || !is_dir($handlersPath)) {
            return;
        }

        $commands = glob($commandsPath . '/*.php');
        $handlers = glob($handlersPath . '/*Handler.php');

        foreach ($commands as $commandFile) {
            $commandClass = $this->getClassFromFile($commandFile, $module['namespace'] . '\\Application\\Commands');
            $handlerFile = $handlersPath . '/' . basename($commandFile, '.php') . 'Handler.php';

            if (file_exists($handlerFile)) {
                $handlerClass = $this->getClassFromFile($handlerFile, $module['namespace'] . '\\Application\\Handlers\\Commands');

                if ($commandClass && $handlerClass) {
                    $commandBus->register($commandClass, $handlerClass);
                }
            }
        }
    }

    private function registerQueryHandlers(QueryBus $queryBus, array $module): void
    {
        $queriesPath = $module['path'] . '/Application/Queries';
        $handlersPath = $module['path'] . '/Application/Handlers/Queries';

        if (!is_dir($queriesPath) || !is_dir($handlersPath)) {
            return;
        }

        $queries = glob($queriesPath . '/*.php');

        foreach ($queries as $queryFile) {
            $queryClass = $this->getClassFromFile($queryFile, $module['namespace'] . '\\Application\\Queries');
            $handlerFile = $handlersPath . '/' . basename($queryFile, '.php') . 'Handler.php';

            if (file_exists($handlerFile)) {
                $handlerClass = $this->getClassFromFile($handlerFile, $module['namespace'] . '\\Application\\Handlers\\Queries');

                if ($queryClass && $handlerClass) {
                    $queryBus->register($queryClass, $handlerClass);
                }
            }
        }
    }

    private function getClassFromFile(string $filePath, string $namespace): ?string
    {
        $className = basename($filePath, '.php');
        $fullClass = $namespace . '\\' . $className;

        if (class_exists($fullClass)) {
            return $fullClass;
        }

        return null;
    }
}
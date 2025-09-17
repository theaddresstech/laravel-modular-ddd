<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Foundation;

use Exception;
use Illuminate\Support\Facades\Validator;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use TaiCrm\LaravelModularDdd\Foundation\Contracts\CommandInterface;

abstract class Command implements CommandInterface
{
    private string $commandId;
    private array $validationErrors = [];

    public function __construct()
    {
        $this->commandId = Uuid::uuid4()->toString();
        $this->validateCommand();
    }

    public function getCommandId(): string
    {
        return $this->commandId;
    }

    public function getCommandType(): string
    {
        $reflection = new ReflectionClass($this);

        return $reflection->getShortName();
    }

    public function getPayload(): array
    {
        return [
            'command_id' => $this->commandId,
            'command_type' => $this->getCommandType(),
            'data' => $this->toArray(),
        ];
    }

    public function validate(): array
    {
        return $this->validationErrors;
    }

    public function isValid(): bool
    {
        return empty($this->validationErrors);
    }

    public function getValidationRules(): array
    {
        return [];
    }

    public function getValidationMessages(): array
    {
        return [];
    }

    abstract protected function toArray(): array;

    private function validateCommand(): void
    {
        $rules = $this->getValidationRules();

        if (empty($rules)) {
            return;
        }

        try {
            $validator = Validator::make(
                $this->toArray(),
                $rules,
                $this->getValidationMessages(),
            );

            if ($validator->fails()) {
                $this->validationErrors = $validator->errors()->toArray();
            }
        } catch (Exception $e) {
            $this->validationErrors = ['validation' => ['Command validation failed: ' . $e->getMessage()]];
        }
    }
}

<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\ValueObjects;

enum ModuleState: string
{
    case NotInstalled = 'not_installed';
    case Installed = 'installed';
    case Enabled = 'enabled';
    case Disabled = 'disabled';
    case Failed = 'failed';
    case Updating = 'updating';

    public function equals(ModuleState $other): bool
    {
        return $this->value === $other->value;
    }

    public function isActive(): bool
    {
        return $this === self::Enabled;
    }

    public function canEnable(): bool
    {
        return in_array($this, [self::Installed, self::Disabled], true);
    }

    public function canDisable(): bool
    {
        return $this === self::Enabled;
    }

    public function canRemove(): bool
    {
        return in_array($this, [self::Installed, self::Disabled, self::Failed], true);
    }

    public function canUpdate(): bool
    {
        return in_array($this, [self::Installed, self::Enabled, self::Disabled], true);
    }

    public function getDisplayName(): string
    {
        return match ($this) {
            self::NotInstalled => 'Not Installed',
            self::Installed => 'Installed',
            self::Enabled => 'Enabled',
            self::Disabled => 'Disabled',
            self::Failed => 'Failed',
            self::Updating => 'Updating',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::NotInstalled => 'gray',
            self::Installed => 'yellow',
            self::Enabled => 'green',
            self::Disabled => 'orange',
            self::Failed => 'red',
            self::Updating => 'blue',
        };
    }
}
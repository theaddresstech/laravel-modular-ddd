<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Foundation;

abstract class Entity
{
    public function __toString(): string
    {
        return static::class . ':' . ($this->getId() ?? 'null');
    }

    abstract public function getId();

    public function equals(self $other): bool
    {
        if (static::class !== $other::class) {
            return false;
        }

        $thisId = $this->getId();
        $otherId = $other->getId();

        if ($thisId === null && $otherId === null) {
            return $this === $other;
        }

        return $thisId !== null && $otherId !== null && $this->compareIds($thisId, $otherId);
    }

    private function compareIds($id1, $id2): bool
    {
        if ($id1 instanceof ValueObject && $id2 instanceof ValueObject) {
            return $id1->equals($id2);
        }

        return $id1 === $id2;
    }
}

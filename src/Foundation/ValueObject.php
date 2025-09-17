<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Foundation;

use BackedEnum;
use JsonSerializable;
use ReflectionClass;
use UnitEnum;

abstract readonly class ValueObject implements JsonSerializable
{
    public function __toString(): string
    {
        return json_encode($this->toArray());
    }

    abstract public function equals(object $other): bool;

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toArray(): array
    {
        $reflection = new ReflectionClass($this);
        $properties = $reflection->getProperties();

        $data = [];
        foreach ($properties as $property) {
            $property->setAccessible(true);
            $value = $property->getValue($this);

            if ($value instanceof self) {
                $data[$property->getName()] = $value->toArray();
            } elseif ($value instanceof BackedEnum) {
                $data[$property->getName()] = $value->value;
            } elseif ($value instanceof UnitEnum) {
                $data[$property->getName()] = $value->name;
            } elseif (is_array($value)) {
                $data[$property->getName()] = array_map(static function ($item) {
                    if ($item instanceof ValueObject) {
                        return $item->toArray();
                    }

                    return $item;
                }, $value);
            } else {
                $data[$property->getName()] = $value;
            }
        }

        return $data;
    }

    protected function compareArrays(array $array1, array $array2): bool
    {
        if (count($array1) !== count($array2)) {
            return false;
        }

        foreach ($array1 as $key => $value) {
            if (!array_key_exists($key, $array2)) {
                return false;
            }

            if ($value instanceof self) {
                if (!($array2[$key] instanceof self) || !$value->equals($array2[$key])) {
                    return false;
                }
            } elseif ($value !== $array2[$key]) {
                return false;
            }
        }

        return true;
    }
}

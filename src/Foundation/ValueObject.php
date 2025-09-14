<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Foundation;

use JsonSerializable;

abstract readonly class ValueObject implements JsonSerializable
{
    abstract public function equals(object $other): bool;

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toArray(): array
    {
        $reflection = new \ReflectionClass($this);
        $properties = $reflection->getProperties();

        $data = [];
        foreach ($properties as $property) {
            $property->setAccessible(true);
            $value = $property->getValue($this);

            if ($value instanceof ValueObject) {
                $data[$property->getName()] = $value->toArray();
            } elseif ($value instanceof \BackedEnum) {
                $data[$property->getName()] = $value->value;
            } elseif ($value instanceof \UnitEnum) {
                $data[$property->getName()] = $value->name;
            } elseif (is_array($value)) {
                $data[$property->getName()] = array_map(function ($item) {
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

            if ($value instanceof ValueObject) {
                if (!($array2[$key] instanceof ValueObject) || !$value->equals($array2[$key])) {
                    return false;
                }
            } elseif ($value !== $array2[$key]) {
                return false;
            }
        }

        return true;
    }

    public function __toString(): string
    {
        return json_encode($this->toArray());
    }
}
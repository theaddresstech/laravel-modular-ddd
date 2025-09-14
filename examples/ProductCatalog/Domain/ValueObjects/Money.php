<?php

declare(strict_types=1);

namespace Modules\ProductCatalog\Domain\ValueObjects;

use TaiCrm\LaravelModularDdd\Foundation\ValueObject;

readonly class Money extends ValueObject
{
    public function __construct(
        private int $amount,        // Amount in cents/smallest currency unit
        private string $currency
    ) {
        $this->validate($amount, $currency);
    }

    public static function fromAmount(int $amount, string $currency): self
    {
        return new self($amount, $currency);
    }

    public static function fromFloat(float $amount, string $currency): self
    {
        return new self((int) round($amount * 100), $currency);
    }

    public static function zero(string $currency): self
    {
        return new self(0, $currency);
    }

    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(Money $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount - $other->amount, $this->currency);
    }

    public function multiply(float $multiplier): self
    {
        return new self((int) round($this->amount * $multiplier), $this->currency);
    }

    public function divide(float $divisor): self
    {
        if ($divisor == 0) {
            throw new \InvalidArgumentException('Cannot divide by zero');
        }

        return new self((int) round($this->amount / $divisor), $this->currency);
    }

    public function percentage(float $percentage): self
    {
        return $this->multiply($percentage / 100);
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function isPositive(): bool
    {
        return $this->amount > 0;
    }

    public function isNegative(): bool
    {
        return $this->amount < 0;
    }

    public function isGreaterThan(Money $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount > $other->amount;
    }

    public function isLessThan(Money $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount < $other->amount;
    }

    public function equals(object $other): bool
    {
        return $other instanceof self &&
               $this->amount === $other->amount &&
               $this->currency === $other->currency;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getFloatAmount(): float
    {
        return $this->amount / 100;
    }

    public function getFormattedAmount(int $decimals = 2): string
    {
        return number_format($this->getFloatAmount(), $decimals);
    }

    public function format(): string
    {
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'CNY' => '¥',
        ];

        $symbol = $symbols[$this->currency] ?? $this->currency . ' ';
        $formatted = $this->getFormattedAmount();

        return match ($this->currency) {
            'USD', 'GBP' => $symbol . $formatted,
            'EUR' => $formatted . ' ' . $symbol,
            default => $symbol . ' ' . $formatted,
        };
    }

    private function validate(int $amount, string $currency): void
    {
        if (empty($currency)) {
            throw new \InvalidArgumentException('Currency cannot be empty');
        }

        if (strlen($currency) !== 3) {
            throw new \InvalidArgumentException('Currency must be a 3-letter ISO code');
        }

        $currency = strtoupper($currency);
        $validCurrencies = [
            'USD', 'EUR', 'GBP', 'JPY', 'CNY', 'CAD', 'AUD', 'CHF', 'SEK', 'NOK'
        ];

        if (!in_array($currency, $validCurrencies)) {
            throw new \InvalidArgumentException("Unsupported currency: {$currency}");
        }
    }

    private function assertSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException(
                "Cannot perform operation with different currencies: {$this->currency} vs {$other->currency}"
            );
        }
    }

    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'formatted' => $this->format(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
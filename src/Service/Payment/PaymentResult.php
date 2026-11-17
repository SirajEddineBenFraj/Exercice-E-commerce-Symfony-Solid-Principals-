<?php

declare(strict_types=1);

namespace App\Service\Payment;

/**
 * Immutable answer of a payment gateway (value object).
 */
final readonly class PaymentResult
{
    private function __construct(
        public bool $isSuccessful,
        public ?string $errorKey = null,
    ) {
    }

    public static function success(): self
    {
        return new self(true);
    }

    /** @param string $errorKey translation key explaining the refusal */
    public static function declined(string $errorKey = 'checkout.error.payment_declined'): self
    {
        return new self(false, $errorKey);
    }
}

<?php

declare(strict_types=1);

namespace App\Service\Payment;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Simulated payment (spec: "le paiement peut être simulé"): every payment succeeds.
 *
 * #[AsAlias] tells Symfony: "when someone asks for PaymentGatewayInterface, give them this class".
 * To switch to a real provider, write StripePaymentGateway implements PaymentGatewayInterface
 * and move this attribute onto it. Nothing else in the app changes (Open/Closed + Dependency Inversion).
 */
#[AsAlias(PaymentGatewayInterface::class)]
final class FakePaymentGateway implements PaymentGatewayInterface
{
    public function pay(int $amountInCents, string $reference): PaymentResult
    {
        return PaymentResult::success();
    }
}

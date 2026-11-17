<?php

declare(strict_types=1);

namespace App\Service\Payment;

/**
 * INTERFACE = a contract: "whatever you are, you can take a payment".
 * It has no code. Classes that `implements` it promise to provide pay() with this exact signature.
 *
 * SOLID (Dependency Inversion): CheckoutService depends on THIS abstraction, never on a concrete
 * gateway. SOLID (Open/Closed): adding Stripe = a new class implementing this, CheckoutService untouched.
 * SOLID (Interface Segregation): only pay(), no refund()/subscribe() that a gateway would have to fake.
 * SOLID (Liskov): every implementation must honor this contract (see the rules below).
 */
interface PaymentGatewayInterface
{
    /**
     * Contract every implementation must respect:
     *  - always return a PaymentResult;
     *  - a declined payment is a normal result (PaymentResult::declined), NOT an exception.
     *
     * @param int    $amountInCents amount to charge, > 0
     * @param string $reference     our order reference, shown on the customer's bank statement
     */
    public function pay(int $amountInCents, string $reference): PaymentResult;
}

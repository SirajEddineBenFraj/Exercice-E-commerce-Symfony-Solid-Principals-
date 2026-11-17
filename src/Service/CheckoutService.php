<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Cart;
use App\Exception\ShopException;
use App\Service\Payment\PaymentGatewayInterface;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * LAYER: Service — "buy the cart". Turns an unpaid cart into a paid order.
 *
 * SOLID (Single Responsibility): checkout only. Adding to the cart lives in CartService.
 * SOLID (Dependency Inversion): it asks for a PaymentGatewayInterface (an abstraction).
 *   Which real class it gets is decided outside (#[AsAlias] on FakePaymentGateway).
 * SOLID (Open/Closed): a new payment provider = a new class. This file never changes for it.
 * SOLID (Liskov): this code works with ANY gateway that respects the interface contract
 *   (proven in tests/Service/CheckoutServiceTest.php with a gateway that always declines).
 */
final class CheckoutService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PaymentGatewayInterface $paymentGateway,
    ) {
    }

    /**
     * Everything happens in one database transaction: either all of it is saved
     * (stock decreased + order paid), or none of it (any exception = rollback).
     *
     * @throws ShopException empty cart, not enough stock, or payment declined
     */
    public function checkout(Cart $cart): void
    {
        if ($cart->isPaid() || $cart->isEmpty()) {
            throw new ShopException('checkout.error.empty');
        }

        $this->entityManager->wrapInTransaction(function () use ($cart): void {
            foreach ($cart->getItems() as $item) {
                $product = $item->getProduct();
                // Re-read the stock and lock the row: two buyers cannot both get the last card.
                $this->entityManager->refresh($product, LockMode::PESSIMISTIC_WRITE);

                if ($item->getQuantity() > $product->getStock()) {
                    throw new ShopException('checkout.error.not_enough_stock', [
                        '%name%' => (string) $product->getName(),
                        '%stock%' => (int) $product->getStock(),
                    ]);
                }
                $product->setStock($product->getStock() - $item->getQuantity());
            }

            // Pay last: if the payment is declined, the exception rolls back the stock changes above.
            $result = $this->paymentGateway->pay($cart->getTotal(), 'ORDER-'.$cart->getId());
            if (!$result->isSuccessful) {
                throw new ShopException($result->errorKey ?? 'checkout.error.payment_declined');
            }

            $cart->markAsPaid(new \DateTimeImmutable());
        });
    }
}

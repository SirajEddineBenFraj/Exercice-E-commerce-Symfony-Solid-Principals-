<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Product;
use App\Entity\User;
use App\Exception\ShopException;
use App\Service\CheckoutService;
use App\Service\Payment\PaymentGatewayInterface;
use App\Service\Payment\PaymentResult;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit test: no database, no HTTP. Possible only because CheckoutService receives its
 * dependencies (Dependency Inversion) instead of creating them.
 *
 * The declining gateway below is the Liskov principle in action: a different implementation of
 * PaymentGatewayInterface is swapped in, and CheckoutService still behaves correctly without knowing.
 */
final class CheckoutServiceTest extends TestCase
{
    public function testSuccessfulCheckoutDecreasesStockAndMarksCartPaid(): void
    {
        $product = $this->product(stock: 5);
        $cart = $this->cartWith($product, quantity: 2);

        $this->service(new AcceptingGateway())->checkout($cart);

        self::assertTrue($cart->isPaid());
        self::assertNotNull($cart->getPurchasedAt());
        self::assertSame(3, $product->getStock());
    }

    public function testDeclinedPaymentThrowsAndLeavesCartUnpaid(): void
    {
        $cart = $this->cartWith($this->product(stock: 5), quantity: 1);

        try {
            $this->service(new DecliningGateway())->checkout($cart);
            self::fail('A declined payment must throw.');
        } catch (ShopException $e) {
            self::assertSame('checkout.error.payment_declined', $e->getMessage());
        }
        self::assertFalse($cart->isPaid());
    }

    public function testNotEnoughStockThrowsBeforeAnyPayment(): void
    {
        $cart = $this->cartWith($this->product(stock: 1), quantity: 3);
        $gateway = new AcceptingGateway();

        try {
            $this->service($gateway)->checkout($cart);
            self::fail('Buying more than the stock must throw.');
        } catch (ShopException $e) {
            self::assertSame('checkout.error.not_enough_stock', $e->getMessage());
        }
        self::assertFalse($gateway->called, 'Nobody must be charged when the stock is missing.');
        self::assertFalse($cart->isPaid());
    }

    public function testEmptyCartCannotBeBought(): void
    {
        $this->expectException(ShopException::class);
        $this->service(new AcceptingGateway())->checkout(new Cart(new User()));
    }

    private function service(PaymentGatewayInterface $gateway): CheckoutService
    {
        // Fake EntityManager: runs the transaction callback directly, does nothing else.
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('wrapInTransaction')->willReturnCallback(static fn (callable $func) => $func($entityManager));

        return new CheckoutService($entityManager, $gateway);
    }

    private function product(int $stock): Product
    {
        return (new Product())->setName('Pikachu')->setDescription('x')->setPrice(450)->setStock($stock);
    }

    private function cartWith(Product $product, int $quantity): Cart
    {
        $cart = new Cart(new User());
        new CartItem($cart, $product, $quantity);

        return $cart;
    }
}

/** Test implementation #1 of the contract: always accepts, remembers it was called. */
final class AcceptingGateway implements PaymentGatewayInterface
{
    public bool $called = false;

    public function pay(int $amountInCents, string $reference): PaymentResult
    {
        $this->called = true;

        return PaymentResult::success();
    }
}

/** Test implementation #2 of the contract: always declines, by returning a result (not throwing). */
final class DecliningGateway implements PaymentGatewayInterface
{
    public function pay(int $amountInCents, string $reference): PaymentResult
    {
        return PaymentResult::declined();
    }
}

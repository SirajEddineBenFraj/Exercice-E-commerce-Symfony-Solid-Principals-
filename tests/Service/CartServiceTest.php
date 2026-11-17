<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Product;
use App\Entity\User;
use App\Exception\ShopException;
use App\Repository\CartRepository;
use App\Service\CartService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit test of the cart rules, without database.
 */
final class CartServiceTest extends TestCase
{
    public function testAddingTheSameProductTwiceRaisesTheQuantity(): void
    {
        $user = new User();
        $cart = new Cart($user);
        $product = $this->product(stock: 10);

        $service = $this->service($cart);
        $service->addProduct($user, $product, 2);
        $service->addProduct($user, $product, 3);

        self::assertCount(1, $cart->getItems());
        self::assertSame(5, $cart->getItems()->first()->getQuantity());
    }

    public function testCannotAddMoreThanTheStock(): void
    {
        $user = new User();
        $cart = new Cart($user);
        $product = $this->product(stock: 3);
        new CartItem($cart, $product, 2);

        $this->expectException(ShopException::class);
        $this->expectExceptionMessage('cart.error.not_enough_stock');
        $this->service($cart)->addProduct($user, $product, 2);
    }

    public function testQuantityMustBePositive(): void
    {
        $user = new User();

        $this->expectException(ShopException::class);
        $this->service(new Cart($user))->addProduct($user, $this->product(stock: 3), 0);
    }

    private function service(Cart $currentCart): CartService
    {
        $carts = $this->createStub(CartRepository::class);
        $carts->method('findCurrentCartFor')->willReturn($currentCart);

        return new CartService($this->createStub(EntityManagerInterface::class), $carts);
    }

    private function product(int $stock): Product
    {
        return (new Product())->setName('Pikachu')->setDescription('x')->setPrice(450)->setStock($stock);
    }
}

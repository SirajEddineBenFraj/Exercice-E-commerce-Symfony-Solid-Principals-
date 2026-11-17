<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Product;
use App\Entity\User;
use App\Exception\ShopException;
use App\Repository\CartRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * LAYER: Service — the rules of the cart: which cart is "current", how adding/removing works,
 * how much a user may add. Controllers call it; it never touches Request, Response or Twig.
 *
 * Why no CartServiceInterface? There is one way to manage a cart and no need to swap it in tests
 * (a real Cart object is easy to build). An interface here would be a file nobody needs (YAGNI).
 */
final class CartService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CartRepository $carts,
    ) {
    }

    /**
     * The user's unpaid cart. If none exists yet, an empty one is returned but NOT saved:
     * it is only written to the database when the first product goes in.
     */
    public function getCurrentCart(User $user): Cart
    {
        return $this->carts->findCurrentCartFor($user) ?? new Cart($user);
    }

    /**
     * Adds a product. If it is already in the cart, the quantity goes up instead of a duplicate line.
     *
     * @throws ShopException when the quantity is invalid or more than the stock
     */
    public function addProduct(User $user, Product $product, int $quantity): void
    {
        if ($quantity < 1) {
            throw new ShopException('cart.error.invalid_quantity');
        }

        $cart = $this->getCurrentCart($user);
        $item = $cart->findItemFor($product);
        $newQuantity = ($item?->getQuantity() ?? 0) + $quantity;

        if ($newQuantity > $product->getStock()) {
            throw new ShopException('cart.error.not_enough_stock', [
                '%name%' => (string) $product->getName(),
                '%stock%' => (int) $product->getStock(),
            ]);
        }

        if (null !== $item) {
            $item->setQuantity($newQuantity);
        } else {
            new CartItem($cart, $product, $quantity); // the constructor attaches the line to the cart
        }

        $this->entityManager->persist($cart); // cascade persist also saves the new line
        $this->entityManager->flush();
    }

    /**
     * @throws ShopException when the line belongs to someone else or to an already paid order
     */
    public function removeItem(User $user, CartItem $item): void
    {
        $cart = $item->getCart();
        if ($cart->getUser()->getId() !== $user->getId() || $cart->isPaid()) {
            throw new ShopException('cart.error.not_yours');
        }

        $cart->removeItem($item); // orphanRemoval deletes the row on flush
        $this->entityManager->flush();
    }
}

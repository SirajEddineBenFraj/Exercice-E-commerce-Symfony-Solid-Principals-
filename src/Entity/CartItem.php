<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CartItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One line of a cart: a product, a quantity, the date it was added (spec: "ContenuPanier").
 */
#[ORM\Entity(repositoryClass: CartItemRepository::class)]
class CartItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Cart $cart;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Product $product;

    #[ORM\Column]
    #[Assert\Positive]
    private int $quantity;

    /** Set automatically, never in a form (spec). */
    #[ORM\Column]
    private \DateTimeImmutable $addedAt;

    public function __construct(Cart $cart, Product $product, int $quantity)
    {
        $this->cart = $cart;
        $this->product = $product;
        $this->quantity = $quantity;
        $this->addedAt = new \DateTimeImmutable();
        $cart->addItem($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getAddedAt(): \DateTimeImmutable
    {
        return $this->addedAt;
    }

    /**
     * Line amount in cents.
     *
     * shortcut: uses the product's current price, so an old order changes if an admin edits the price;
     * upgrade by copying the price into a CartItem column at checkout if order history must be frozen.
     */
    public function getLineTotal(): int
    {
        return $this->product->getPrice() * $this->quantity;
    }
}

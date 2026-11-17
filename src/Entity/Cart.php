<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CartRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * A cart while unpaid, an order once paid (spec: one "Panier" entity with a state).
 */
#[ORM\Entity(repositoryClass: CartRepository::class)]
class Cart
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    /** false = cart being filled, true = paid order. */
    #[ORM\Column(options: ['default' => false])]
    private bool $isPaid = false;

    /** Set by the server at checkout, never in a form (spec). */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $purchasedAt = null;

    /** @var Collection<int, CartItem> */
    #[ORM\OneToMany(targetEntity: CartItem::class, mappedBy: 'cart', cascade: ['persist'], orphanRemoval: true)]
    private Collection $items;

    public function __construct(User $user)
    {
        $this->user = $user;
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function isPaid(): bool
    {
        return $this->isPaid;
    }

    public function getPurchasedAt(): ?\DateTimeImmutable
    {
        return $this->purchasedAt;
    }

    /** Called only by CheckoutService once payment succeeded. */
    public function markAsPaid(\DateTimeImmutable $at): void
    {
        $this->isPaid = true;
        $this->purchasedAt = $at;
    }

    /** @return Collection<int, CartItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(CartItem $item): void
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
        }
    }

    public function removeItem(CartItem $item): void
    {
        $this->items->removeElement($item);
    }

    /** The line already holding this product, so adding twice raises the quantity instead of duplicating. */
    public function findItemFor(Product $product): ?CartItem
    {
        foreach ($this->items as $item) {
            if ($item->getProduct() === $product) {
                return $item;
            }
        }

        return null;
    }

    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }

    /** Total in cents. Small calculation on own data = fine inside the entity. */
    public function getTotal(): int
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += $item->getLineTotal();
        }

        return $total;
    }
}

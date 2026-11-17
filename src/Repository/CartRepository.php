<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Cart;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * LAYER: Repository — every cart/order query of the app lives here.
 * Services and controllers read like sentences: $carts->findPaidCartsFor($user).
 *
 * @extends ServiceEntityRepository<Cart>
 */
class CartRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cart::class);
    }

    /** The cart the user is filling right now (there is at most one unpaid cart per user). */
    public function findCurrentCartFor(User $user): ?Cart
    {
        return $this->findOneBy(['user' => $user, 'isPaid' => false]);
    }

    /** @return list<Cart> order history, newest first. */
    public function findPaidCartsFor(User $user): array
    {
        return $this->findBy(['user' => $user, 'isPaid' => true], ['purchasedAt' => 'DESC']);
    }

    /**
     * @return list<Cart> unpaid, non-empty carts with their user and contents loaded in one query
     *                    (super admin page — avoids one query per cart).
     */
    public function findUnpaidCarts(): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('u', 'i', 'p')
            ->join('c.user', 'u')
            ->join('c.items', 'i')
            ->join('i.product', 'p')
            ->where('c.isPaid = false')
            ->orderBy('c.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

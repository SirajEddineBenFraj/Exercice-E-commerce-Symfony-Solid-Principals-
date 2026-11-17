<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CartItem;
use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CartItem>
 */
class CartItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CartItem::class);
    }

    /** True if the product sits in any cart or past order (then it must not be deleted). */
    public function existsForProduct(Product $product): bool
    {
        return null !== $this->findOneBy(['product' => $product]);
    }
}

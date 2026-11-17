<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * LAYER: Repository = the only place that knows how products are fetched from the database.
 *
 * SOLID (Liskov): this class extends ServiceEntityRepository, so it can be used anywhere a Doctrine
 * repository is expected (find(), findBy()...) and still behaves like one: we only ADD methods.
 *
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /** @return list<Product> the catalog shown on the home page, alphabetical. */
    public function findCatalog(): array
    {
        return $this->findBy([], ['name' => 'ASC']);
    }
}

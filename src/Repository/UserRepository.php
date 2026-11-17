<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * LAYER: Repository — user queries, named after what they mean, not how they are built.
 *
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /** @return list<User> users who signed up today, newest first (super admin page). */
    public function findRegisteredToday(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.createdAt >= :today')
            ->andWhere('u.createdAt < :tomorrow')
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->setParameter('tomorrow', new \DateTimeImmutable('tomorrow'))
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

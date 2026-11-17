<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * LAYER: Service — account rules: registration (password hashing) and profile saving.
 */
final class UserService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function register(User $user, string $plainPassword): void
    {
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    public function saveProfile(User $user): void
    {
        $this->entityManager->flush();
    }

    /**
     * Throws away invalid form changes on the logged-in user. Without it, a mistyped email would be
     * stored in the session and the user would be logged out on the next page.
     */
    public function discardProfileChanges(User $user): void
    {
        $this->entityManager->refresh($user);
    }
}

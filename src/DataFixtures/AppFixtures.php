<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Demo data: `php bin/console doctrine:fixtures:load`.
 * Accounts (password "password"): user@pokeshop.test, admin@pokeshop.test, superadmin@pokeshop.test.
 */
final class AppFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        // name => [description, price in cents, stock]
        $cards = [
            'Pikachu' => ['Pokémon Souris. Il stocke l\'électricité dans ses joues.', 450, 25],
            'Dracaufeu' => ['Pokémon Flamme. Carte holographique très recherchée.', 18900, 2],
            'Bulbizarre' => ['Pokémon Graine. Une graine pousse sur son dos depuis sa naissance.', 300, 40],
            'Carapuce' => ['Pokémon Minitortue. Il se réfugie dans sa carapace.', 300, 35],
            'Salamèche' => ['Pokémon Lézard. La flamme de sa queue montre son humeur.', 350, 30],
            'Mewtwo' => ['Pokémon Génétique. Carte rare, édition limitée.', 7900, 4],
            'Évoli' => ['Pokémon Évolutif. Son ADN instable lui permet d\'évoluer de 8 façons.', 600, 18],
            'Ronflex' => ['Pokémon Pionceur. Il ne se réveille que pour manger.', 900, 0],
        ];

        $products = [];
        foreach ($cards as $name => [$description, $price, $stock]) {
            $product = (new Product())->setName($name)->setDescription($description)->setPrice($price)->setStock($stock);
            $manager->persist($product);
            $products[$name] = $product;
        }

        $user = $this->createUser('user@pokeshop.test', 'Sacha', 'Ketchum', []);
        $admin = $this->createUser('admin@pokeshop.test', 'Pierre', 'Rocher', ['ROLE_ADMIN']);
        $superAdmin = $this->createUser('superadmin@pokeshop.test', 'Professeur', 'Chen', ['ROLE_SUPER_ADMIN']);
        foreach ([$user, $admin, $superAdmin] as $account) {
            $manager->persist($account);
        }

        // A past order for "user", so the account history is not empty.
        $order = new Cart($user);
        new CartItem($order, $products['Pikachu'], 2);
        new CartItem($order, $products['Évoli'], 1);
        $order->markAsPaid(new \DateTimeImmutable('-3 days'));
        $manager->persist($order);

        // An unpaid cart for "admin", visible on the super admin dashboard.
        $openCart = new Cart($admin);
        new CartItem($openCart, $products['Mewtwo'], 1);
        $manager->persist($openCart);

        $manager->flush();
    }

    /** @param list<string> $roles */
    private function createUser(string $email, string $firstName, string $lastName, array $roles): User
    {
        $user = (new User())->setEmail($email)->setFirstName($firstName)->setLastName($lastName)->setRoles($roles);

        return $user->setPassword($this->passwordHasher->hashPassword($user, 'password'));
    }
}

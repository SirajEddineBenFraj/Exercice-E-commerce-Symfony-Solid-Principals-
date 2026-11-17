<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * End-to-end tests through HTTP, the way a browser uses the shop (test database "pokeshop_test").
 */
final class ShopFlowTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // Browsers send Origin on form posts; Symfony's stateless CSRF check relies on it.
        $this->client->setServerParameter('HTTP_ORIGIN', 'http://localhost');
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        foreach ([CartItem::class, Cart::class, Product::class, User::class] as $entity) {
            $this->em->createQuery('DELETE FROM '.$entity)->execute();
        }
    }

    public function testCatalogIsPublicAndCartRequiresLogin(): void
    {
        $this->product('Pikachu', 5);

        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Pikachu');

        $this->client->request('GET', '/cart');
        self::assertResponseRedirects('/login');
    }

    public function testEnglishVersion(): void
    {
        $this->client->request('GET', '/en/');
        self::assertSelectorTextContains('h1', 'Our cards');
    }

    public function testAddToCartThenBuy(): void
    {
        $product = $this->product('Pikachu', 5);
        $this->client->loginUser($this->user('sacha@test.fr'));

        $this->client->request('GET', '/product/'.$product->getId());
        $this->client->submitForm('Ajouter au panier', ['add_to_cart[quantity]' => 2]);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Pikachu');

        $this->client->request('GET', '/cart');
        self::assertSelectorTextContains('tfoot', '9,00');
        $this->client->submitForm('Acheter');

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Commande');

        $this->em->clear();
        self::assertSame(3, $this->em->find(Product::class, $product->getId())->getStock());
    }

    public function testCannotAddMoreThanStock(): void
    {
        $product = $this->product('Dracaufeu', 1);
        $this->client->loginUser($this->user('sacha@test.fr'));

        $this->client->request('GET', '/product/'.$product->getId());
        $this->client->submitForm('Ajouter au panier', ['add_to_cart[quantity]' => 3]);
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
    }

    public function testOrderIsVisibleToOwnerAndSuperAdminOnly(): void
    {
        $owner = $this->user('owner@test.fr');
        $order = $this->paidOrder($owner, $this->product('Mewtwo', 3));

        $this->client->loginUser($this->user('other@test.fr'));
        $this->client->request('GET', '/account/order/'.$order->getId());
        self::assertResponseStatusCodeSame(403);

        $this->client->loginUser($this->user('boss@test.fr', ['ROLE_SUPER_ADMIN']));
        $this->client->request('GET', '/account/order/'.$order->getId());
        self::assertResponseIsSuccessful();
    }

    public function testOnlyAdminsManageProducts(): void
    {
        $this->client->loginUser($this->user('sacha@test.fr'));
        $this->client->request('GET', '/admin/product/new');
        self::assertResponseStatusCodeSame(403);

        $this->client->loginUser($this->user('admin@test.fr', ['ROLE_ADMIN']));
        $this->client->request('GET', '/admin/product/new');
        $this->client->submitForm('Enregistrer', [
            'product[name]' => 'Évoli',
            'product[description]' => 'Pokémon Évolutif',
            'product[price]' => '6,50',
            'product[stock]' => 10,
        ]);
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Évoli');
        self::assertSame(650, $this->em->getRepository(Product::class)->findOneBy(['name' => 'Évoli'])->getPrice());
    }

    public function testSuperAdminDashboard(): void
    {
        $this->user('new@test.fr');
        $this->client->loginUser($this->user('admin@test.fr', ['ROLE_ADMIN']));
        $this->client->request('GET', '/admin/super');
        self::assertResponseStatusCodeSame(403);

        $this->client->loginUser($this->user('boss@test.fr', ['ROLE_SUPER_ADMIN']));
        $this->client->request('GET', '/admin/super');
        self::assertSelectorTextContains('main', 'new@test.fr');
    }

    public function testUnknownPageIs404(): void
    {
        $this->client->request('GET', '/does-not-exist');
        self::assertResponseStatusCodeSame(404);
    }

    private function product(string $name, int $stock): Product
    {
        $product = (new Product())->setName($name)->setDescription('Carte')->setPrice(450)->setStock($stock);
        $this->em->persist($product);
        $this->em->flush();

        return $product;
    }

    /** @param list<string> $roles */
    private function user(string $email, array $roles = []): User
    {
        $user = (new User())->setEmail($email)->setFirstName('Test')->setLastName('User')->setRoles($roles)->setPassword('x');
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function paidOrder(User $user, Product $product): Cart
    {
        $cart = new Cart($user);
        new CartItem($cart, $product, 1);
        $cart->markAsPaid(new \DateTimeImmutable());
        $this->em->persist($cart);
        $this->em->flush();

        return $cart;
    }
}

# Learning guide: SOLID and Controller / Repository / Service in PokéShop

This project is the EEMI M1 e-commerce exercise (a Pokémon card shop). It is written so that every
idea below can be found **in real code**. Each section answers four questions:

1. What does the principle say (in plain words)?
2. Where is it in this project (click the links)?
3. What would the "bad" version look like?
4. What breaks without it?

In the code, look for comments starting with `SOLID (...)` and `LAYER:`. They mark the exact spot.

> Rule followed while building: an abstraction is added only when the app **needs** it.
> SOLID is not "more files". A good design has as few pieces as possible, each with one clear job.

---

## Table of contents

0. [Interfaces 101 (read this first)](#0-interfaces-101-read-this-first)
1. [S — Single Responsibility](#1-s--single-responsibility-principle)
2. [O — Open/Closed](#2-o--openclosed-principle)
3. [L — Liskov Substitution](#3-l--liskov-substitution-principle)
4. [I — Interface Segregation](#4-i--interface-segregation-principle)
5. [D — Dependency Inversion](#5-d--dependency-inversion-principle)
6. [Controller / Repository / Service: why everything is where it is](#6-controller--repository--service)
7. [Where we did NOT add abstractions, and why](#7-where-we-did-not-add-abstractions-and-why)
8. [Exercises to check you understood](#8-exercises)

---

## 0. Interfaces 101 (read this first)

O, L, I and D all rest on one tool: the **interface**. If interfaces are fuzzy, the rest stays fuzzy.

### What it is

An interface is a **contract**: a list of method signatures with **no code inside**.
It says *what* something can do, never *how*.

```php
// src/Service/Payment/PaymentGatewayInterface.php
interface PaymentGatewayInterface
{
    public function pay(int $amountInCents, string $reference): PaymentResult;   // no body { }
}
```

A class that writes `implements PaymentGatewayInterface` **promises** to have that exact method.
PHP checks the promise: if the method is missing or has a different signature, PHP refuses to run
("Class FakePaymentGateway contains 1 abstract method...").

```php
// src/Service/Payment/FakePaymentGateway.php
final class FakePaymentGateway implements PaymentGatewayInterface
{
    public function pay(int $amountInCents, string $reference): PaymentResult
    {
        return PaymentResult::success();      // the "how"
    }
}
```

### The analogy: a wall socket

Your phone charger needs "something with this plug shape that gives 230 V".
It does not care whether the electricity comes from a nuclear plant, solar panels or a generator.

| Real world | This project |
|---|---|
| The socket shape (the standard) | `PaymentGatewayInterface` |
| Nuclear plant, solar panels... | `FakePaymentGateway`, tomorrow `StripePaymentGateway` |
| The charger, which only knows the socket | `CheckoutService` |

### Follow it in the real code, step by step

1. [PaymentGatewayInterface.php](src/Service/Payment/PaymentGatewayInterface.php) defines the contract `pay()`.
2. [FakePaymentGateway.php](src/Service/Payment/FakePaymentGateway.php) implements it (every payment succeeds; the spec allows a simulated payment).
   It carries `#[AsAlias(PaymentGatewayInterface::class)]`, which tells Symfony:
   *"when someone asks for the interface, give them this class"*.
3. [CheckoutService.php](src/Service/CheckoutService.php) asks for the **interface** in its constructor:
   ```php
   public function __construct(
       private readonly EntityManagerInterface $entityManager,
       private readonly PaymentGatewayInterface $paymentGateway,   // <- the socket, not a plant
   ) {}
   ```
4. When a request needs `CheckoutService`, Symfony's **container** builds it:
   - "CheckoutService needs a `PaymentGatewayInterface`"
   - "who is the alias for that interface? `FakePaymentGateway`"
   - `new FakePaymentGateway()`, then `new CheckoutService($entityManager, $thatGateway)`.
   You never write those `new` lines yourself. That is **autowiring**.
5. In [CheckoutServiceTest.php](tests/Service/CheckoutServiceTest.php) we build `CheckoutService` **by hand** with two
   other implementations: `AcceptingGateway` and `DecliningGateway`. CheckoutService does not notice the difference.
   That is the whole point.

### Interface vs abstract class vs normal class

| | Contains code? | Contains properties? | How many can a class use? | Use it when... |
|---|---|---|---|---|
| **interface** | no | no (constants only) | **several** (`implements A, B`) | you want to describe a capability that several unrelated classes may have |
| **abstract class** | yes (partly) | yes | **one** (`extends`) | several classes share real code and are "a kind of" the same thing |
| **normal class** | yes | yes | one (`extends`) | there is one concrete way of doing the job |

In this project: `PaymentGatewayInterface` (interface), `AbstractController` and `Voter` (abstract classes from Symfony,
which we extend to reuse their code), and everything else is a normal class.

### Interfaces you already use without noticing

- [User.php](src/Entity/User.php) `implements UserInterface, PasswordAuthenticatedUserInterface`: that is how Symfony's
  login system can work with **our** User class without knowing it.
- Every form (`ProductType`...) extends `AbstractType`, which implements Symfony's `FormTypeInterface`.
- `EntityManagerInterface`, `UserPasswordHasherInterface`: we always type-hint the Symfony **interface**, not the concrete class.

### When NOT to create an interface

`CartService`, `ProductService`, `UserService` have **no interface**, on purpose.
There is only one way to manage a cart, and tests can use the real class. An interface "just in case"
is a second file to maintain for nothing (YAGNI: *You Aren't Gonna Need It*).
Create an interface when **at least one** is true:
- there are (or will clearly be) several implementations (payment providers, storage backends, mailers...);
- you need to replace the real thing in tests because it is slow, costly or external (a real bank!).

---

## 1. S — Single Responsibility Principle

> **A class should have only one reason to change.**
> "Reason to change" = one person or one topic asking for changes.

### Where it is in PokéShop

| Class | Its single job | Who would ask to change it |
|---|---|---|
| [ProductController](src/Controller/ProductController.php) | turn HTTP requests into calls and pages | the web/UX side |
| [CartRepository](src/Repository/CartRepository.php) | fetch carts from the database | the database side |
| [CartService](src/Service/CartService.php) | cart rules (merge quantities, stock limit) | the business side |
| [CheckoutService](src/Service/CheckoutService.php) | turn a cart into a paid order | the business side (payment, stock) |
| [FileUploader](src/Service/FileUploader.php) | move files on disk | nobody but "file storage" |
| [OrderVoter](src/Security/Voter/OrderVoter.php) | answer "may this user see this order?" | the security side |
| [cart/_table.html.twig](templates/cart/_table.html.twig) | display cart lines (reused 3 times) | the design side |

### The "bad" version it avoids: a fat controller

```php
// What it looks like WITHOUT SRP: everything in the controller
#[Route('/cart/checkout')]
public function checkout(EntityManagerInterface $em): Response
{
    $cart = $em->createQuery('SELECT c FROM App\Entity\Cart c WHERE c.user = :u AND c.isPaid = 0')
               ->setParameter('u', $this->getUser())->getOneOrNullResult();      // SQL in a controller
    foreach ($cart->getItems() as $item) {
        if ($item->getQuantity() > $item->getProduct()->getStock()) {              // business rule in a controller
            $this->addFlash('danger', 'Pas assez de stock');
            return $this->redirectToRoute('app_cart');
        }
        $item->getProduct()->setStock($item->getProduct()->getStock() - $item->getQuantity());
    }
    // ... payment code here too ...
    $em->flush();
    return $this->redirectToRoute('app_account');
}
```

### What breaks without it

- You want a JSON API for a mobile app later: you must **copy** the whole checkout logic into the API controller.
  Two copies drift apart, and one day a bug gets fixed in only one of them.
- You cannot test "not enough stock" without simulating an HTTP request, a session, a logged-in user...
  Look at [CheckoutServiceTest](tests/Service/CheckoutServiceTest.php): it tests the rule in a few lines, with no database at all.

---

## 2. O — Open/Closed Principle

> **Open for extension, closed for modification.**
> You add new behavior by **adding code** (a new class, a config line), not by editing code that already works.

### Where it is in PokéShop

1. **Payment providers.** [CheckoutService](src/Service/CheckoutService.php) only knows `PaymentGatewayInterface`.
   Adding Stripe means writing `StripePaymentGateway implements PaymentGatewayInterface` and moving the `#[AsAlias]` attribute.
   `CheckoutService` is **not edited**, so it cannot get broken.
2. **Role hierarchy** in [security.yaml](config/packages/security.yaml):
   ```yaml
   role_hierarchy:
       ROLE_ADMIN: ROLE_USER
       ROLE_SUPER_ADMIN: ROLE_ADMIN
   ```
   The spec says "SUPER_ADMIN has all ADMIN rights". We wrote **zero** `if ($role === 'ADMIN' || $role === 'SUPER_ADMIN')`.
   Every `#[IsGranted('ROLE_ADMIN')]` automatically accepts super admins.
3. **Voters.** A new permission rule (e.g. "support staff may see all orders") = a new voter class. The controller line
   `#[IsGranted(OrderVoter::VIEW, subject: 'order')]` in [AccountController](src/Controller/AccountController.php) stays as is.
4. **Forms.** [RegistrationFormType](src/Form/RegistrationFormType.php) uses `getParent(): ProfileType::class`.
   It **extends** the profile form with a password field without touching [ProfileType](src/Form/ProfileType.php).

### The "bad" version

```php
public function checkout(Cart $cart, string $provider): void
{
    if ($provider === 'fake') { /* ... */ }
    elseif ($provider === 'stripe') { /* ... */ }
    elseif ($provider === 'paypal') { /* ... */ }    // every new provider = edit this method again
}
```

### What breaks without it

Each new provider means re-opening and re-testing working code. A typo in the PayPal branch can break Stripe payments.

---

## 3. L — Liskov Substitution Principle

> **If code works with a type, it must keep working with any subtype.**
> An implementation must respect the **whole contract**, not just the method names:
> same kind of inputs accepted, same kind of results, no surprise exceptions.

### Where it is in PokéShop

1. **Payment gateways.** The contract is written in the docblock of
   [PaymentGatewayInterface](src/Service/Payment/PaymentGatewayInterface.php):
   *always return a `PaymentResult`; a declined card is a normal result, NOT an exception.*
   - `FakePaymentGateway` respects it.
   - In [CheckoutServiceTest](tests/Service/CheckoutServiceTest.php), `DecliningGateway` respects it too: it **returns** `PaymentResult::declined()`.
   - `CheckoutService` works with both, unchanged. The test `testDeclinedPaymentThrowsAndLeavesCartUnpaid` proves it.
2. **Repositories.** [ProductRepository](src/Repository/ProductRepository.php) extends `ServiceEntityRepository`.
   It only **adds** methods (`findCatalog()`), never changes what `find()` or `findBy()` do. Any code expecting a Doctrine
   repository can use it safely.
3. **Voters.** [OrderVoter](src/Security/Voter/OrderVoter.php) extends Symfony's `Voter` and returns a bool as expected.
   Symfony asks it the same question as all other voters.

### The "bad" version: an implementation that breaks the contract

```php
final class GrumpyGateway implements PaymentGatewayInterface
{
    public function pay(int $amountInCents, string $reference): PaymentResult
    {
        throw new \Exception('Card declined');   // the contract says: RETURN declined, don't throw
    }
}
```

It compiles fine (same signature). But `CheckoutService` never expects that exception, so the user gets a
**500 error page** instead of a nice "payment declined" message. Same method names, but it cannot be used in place of the original. That breaks Liskov.

### What breaks without it

Interfaces become useless: if you must check *which* implementation you have (`if ($gateway instanceof GrumpyGateway)`)
before calling it, you have lost all the benefits of O and D.

---

## 4. I — Interface Segregation Principle

> **No class should be forced to depend on (or implement) methods it does not use.**
> Prefer several small interfaces over one big "do everything" interface.

### Where it is in PokéShop

1. **Symfony's security interfaces**, implemented by [User](src/Entity/User.php):
   - `UserInterface` = "can be logged in" (`getUserIdentifier()`, `getRoles()`)
   - `PasswordAuthenticatedUserInterface` = "logs in with a password" (`getPassword()`)

   Symfony split them on purpose: a user who logs in with Google or a magic link implements only the first one,
   and does not need a fake `getPassword()` that returns null.
2. **Our own [PaymentGatewayInterface](src/Service/Payment/PaymentGatewayInterface.php)** has one method: `pay()`.
   The shop never refunds or subscribes, so the interface does not ask for `refund()` or `createSubscription()`.

### The "bad" version: a fat interface

```php
interface PaymentGatewayInterface
{
    public function pay(int $amount, string $ref): PaymentResult;
    public function refund(string $paymentId): void;
    public function createSubscription(User $user): void;
    public function exportAccountingCsv(): string;
}

final class FakePaymentGateway implements PaymentGatewayInterface
{
    public function pay(...) { return PaymentResult::success(); }
    public function refund(...) { throw new \LogicException('not supported'); }          // forced to lie
    public function createSubscription(...) { throw new \LogicException('not supported'); }
    public function exportAccountingCsv() { return ''; }
}
```

### What breaks without it

Every implementation is full of fake methods that throw. That is also a Liskov violation waiting to happen.
And every change to `refund()` forces changes in classes that never refund anything.
If one day we need refunds, we will add a separate `RefundableGatewayInterface` that only refund-capable gateways implement.

---

## 5. D — Dependency Inversion Principle

> **High-level code (business rules) must not depend on low-level details (a specific bank, a specific disk).
> Both depend on an abstraction.**
> In practice: a class **receives** what it needs (constructor injection) instead of creating it with `new`.

### Where it is in PokéShop

1. [CheckoutService](src/Service/CheckoutService.php) (high level: "how to buy") depends on
   `PaymentGatewayInterface` (abstraction), **not** on `FakePaymentGateway` (detail).
   The direction is "inverted": the detail (`FakePaymentGateway`) depends on the abstraction (it implements it), so the
   business rule depends on nothing concrete.
   ```
   WITHOUT DIP:   CheckoutService ───────────▶ FakePaymentGateway
   WITH DIP:      CheckoutService ──▶ PaymentGatewayInterface ◀── FakePaymentGateway
   ```
2. **Constructor injection everywhere.** Look at any service or controller: dependencies arrive as constructor or action arguments
   (`CartService $cartService`, `ProductRepository $products`...). There is no `new CartService()` anywhere in `src/`.
   Symfony's container builds and wires everything ([config/services.yaml](config/services.yaml): `autowire: true`).
3. We always type-hint Symfony's **interfaces**: `EntityManagerInterface`, `UserPasswordHasherInterface`,
   `AccessDecisionManagerInterface`.

### The "bad" version

```php
final class CheckoutService
{
    public function checkout(Cart $cart): void
    {
        $gateway = new StripePaymentGateway('sk_live_123');   // hard-wired detail + secret in the code
        $em = EntityManagerFactory::create();                   // creates its own database connection
        // ...
    }
}
```

### What breaks without it

- Tests would charge a **real card** every time they run. Look at how
  [CheckoutServiceTest](tests/Service/CheckoutServiceTest.php) passes fake gateways in and never touches a bank or a database.
- Switching providers means editing business code.

---

## 6. Controller / Repository / Service

### The three layers

```
 Browser
   │  HTTP request (POST /cart/checkout)
   ▼
┌──────────────────────┐   "What does the visitor want? Allowed? Which page next?"
│  CONTROLLER          │   - reads the Request, checks CSRF + access (#[IsGranted])
│  src/Controller      │   - calls ONE service method or ONE repository method
└─────────┬────────────┘   - adds a flash message, renders Twig or redirects
          │ calls
          ▼
┌──────────────────────┐   "What are the rules?"
│  SERVICE             │   - stock checks, quantity merging, payment, transaction
│  src/Service         │   - throws ShopException when a rule is broken
└─────────┬────────────┘   - knows nothing about HTTP (no Request, no Response, no Twig)
          │ uses
          ▼
┌──────────────────────┐   "How do I get the data?"
│  REPOSITORY          │   - every database query, named by intent:
│  src/Repository      │     findCurrentCartFor($user), findPaidCartsFor($user),
└─────────┬────────────┘     findUnpaidCarts(), findRegisteredToday()
          ▼
       Database (Entities = the data + validation rules on it)
```

### Walk-through 1: "Add to cart" (POST /cart/add/3)

1. [CartController::add](src/Controller/CartController.php): Symfony converts `{id}` into a `Product` (404 if missing),
   checks `#[IsGranted('ROLE_USER')]`, validates the quantity form, then calls
   `$this->cartService->addProduct($user, $product, $quantity)`.
2. [CartService::addProduct](src/Service/CartService.php): asks the repository for the current cart
   (`findCurrentCartFor`), merges with an existing line or creates a `CartItem`, refuses if over stock
   (`throw new ShopException('cart.error.not_enough_stock')`), then saves.
3. Back in the controller: success means a green flash; a `ShopException` means a red flash. Then redirect to the product page.

### Walk-through 2: "Buy" (POST /cart/checkout)

1. [CartController::checkout](src/Controller/CartController.php) checks the CSRF token, gets the cart, calls `CheckoutService::checkout`.
2. [CheckoutService::checkout](src/Service/CheckoutService.php), **in one database transaction**: locks and re-reads each
   product's stock, decreases it, calls the payment gateway **last**, marks the cart paid with the current date.
   Any exception = everything rolled back (no stock lost on a declined payment).
3. Controller: redirect to the order page. Access to that page is decided by [OrderVoter](src/Security/Voter/OrderVoter.php).

### "Where does this code go?" (cheat sheet)

| The code... | Goes in | Example here |
|---|---|---|
| reads `$request`, redirects, renders, flashes | **Controller** | `CartController::add` |
| writes a database query (DQL, QueryBuilder, findBy with conditions) | **Repository** | `UserRepository::findRegisteredToday` |
| enforces a business rule or a multi-step process | **Service** | `CheckoutService::checkout` |
| is a small calculation on an entity's own data | **Entity** | `Cart::getTotal()`, `Product::isInStock()` |
| says which fields a form has | **Form type** | `ProductType` |
| decides "may user X do Y on object Z?" | **Voter** | `OrderVoter` |
| validates one field value | **#[Assert] on the entity** | `#[Assert\Positive]` on `Product::$price` |
| displays things | **Twig template** | `templates/cart/_table.html.twig` |
| is text shown to the user | **translations/** | `messages.fr.yaml`, `messages.en.yaml` |

### Why this split pays off

- **Read a controller, understand the feature** in 10 seconds: each action is a few lines.
- **Reuse:** `CartService` could serve a JSON API tomorrow without changes.
- **Testing:** services are tested without HTTP ([tests/Service](tests/Service)); the whole flow is tested through HTTP
  ([tests/Controller/ShopFlowTest.php](tests/Controller/ShopFlowTest.php)).
- **Teamwork:** one person changes a query, another a template, another a rule, in different files.

### Note: a controller may call a repository directly

[SuperAdminController](src/Controller/SuperAdminController.php) and `ProductController::index` read data straight from
repositories. That is fine: there is **no rule** to apply, only data to show. A `SuperAdminService` that just forwards
`findUnpaidCarts()` would be a useless extra layer. Use a service as soon as there is a **decision** or a **change** (write).

---

## 7. Where we did NOT add abstractions, and why

Being SOLID also means knowing when to stop:

| Not done | Why |
|---|---|
| `CartServiceInterface`, `ProductServiceInterface` | one implementation, nothing to swap |
| One exception class per error | a single `ShopException` carrying a translation key covers every business error |
| A service for the super admin page | read-only, no rules |
| A separate `RegistrationController` | sign-up is 15 lines; it sits next to login in `SecurityController` |
| Price snapshot on order lines | not required by the spec; marked with a `shortcut:` comment in [CartItem.php](src/Entity/CartItem.php) |

---

## 8. Exercises

1. **Add a PayPal gateway (O + D + L).** Create `src/Service/Payment/PaypalPaymentGateway.php` implementing
   `PaymentGatewayInterface`, and move `#[AsAlias(PaymentGatewayInterface::class)]` from `FakePaymentGateway` to it.
   Expected diff: 1 new file, 2 changed lines. `CheckoutService` must not change. If you had to edit it, something is wrong.
2. **Break Liskov on purpose.** Make `FakePaymentGateway::pay()` throw an exception instead of returning
   `PaymentResult::declined()`. Click "Buy". What does the user see? Why?
3. **Add a rule (O).** "Admins may also see all orders": which single file do you change? (Hint: the voter, or the role hierarchy.)
4. **Find the layer.** "Show only cards under 5 €" — which layer gets a new method? (Repository.)
   "A customer may not buy more than 4 copies of the same card" — which layer? (Service: `CartService::addProduct`.)

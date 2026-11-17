# PokéShop — Symfony 8 e-commerce (EEMI M1)

A Pokémon card shop: catalog, cart, simulated payment, order history, admin and super admin areas,
FR/EN. Built to show the **SOLID principles** and the **Controller / Repository / Service** architecture.
Read [SOLID.md](SOLID.md) for the full explanation, with links to the code.
French audit report of every SOLID decision (how, where, why): [RAPPORT_SOLID.md](RAPPORT_SOLID.md).

## Group members

- _First name LAST NAME_
- _First name LAST NAME_

## Stack

Symfony 8.1, PHP ≥ 8.4, Doctrine ORM, MySQL / MariaDB, Twig, Bootstrap 5 (AssetMapper, no Node needed).

## Install (procedure from the brief)

```bash
git clone URL
cd project_folder
composer install
# copy .env to .env.local and set your database access, for example (XAMPP):
# DATABASE_URL="mysql://root:@127.0.0.1:3306/pokeshop?serverVersion=10.4.32-MariaDB&charset=utf8mb4"
php bin/console d:d:c
php bin/console d:m:m
php bin/console doctrine:fixtures:load     # demo cards + accounts below
symfony serve                              # or any web server pointing to public/
```

## Demo accounts (password: `password`)

| Email | Role |
|---|---|
| user@pokeshop.test | ROLE_USER (has one past order) |
| admin@pokeshop.test | ROLE_ADMIN (has one unpaid cart) |
| superadmin@pokeshop.test | ROLE_SUPER_ADMIN |

## Tests

```bash
php bin/console --env=test d:d:c
php bin/console --env=test d:m:m -n
php bin/phpunit
```

## Features → spec

| Spec | Where |
|---|---|
| Home, product list, admin "add" button | `ProductController::index`, `templates/product/index.html.twig` |
| Product page, add to cart, admin edit/delete | `ProductController::show/edit/delete` |
| Cart: lines, line amount, total, remove, buy (simulated) | `CartController`, `CartService`, `CheckoutService` |
| My account: profile, order history, order detail | `AccountController`, `OrderVoter` |
| Super admin: unpaid carts, users registered today | `SuperAdminController`, `CartRepository::findUnpaidCarts`, `UserRepository::findRegisteredToday` |
| Role-aware menus | `templates/base.html.twig` |
| Custom error pages | `templates/bundles/TwigBundle/Exception/error.html.twig` (visible with `APP_DEBUG=0`; in dev: `/_error/404`) |
| Form validation | `#[Assert]` on entities + form constraints |
| Flash messages | every action, rendered in `base.html.twig` |
| 2 languages | `translations/` (FR default, EN under `/en`) |
| No EasyAdmin | ✔ |

## Choices the brief left open

- Prices are stored in **cents** (integers, no rounding errors).
- Adding a card already in the cart raises its quantity; you cannot add more than the stock.
- Buying re-checks and **decreases the stock** in a transaction; a declined payment rolls everything back.
- A card that appears in any cart/order cannot be deleted (it would erase order history): set its stock to 0 instead.
- `User.createdAt` was added (needed for "users registered today"). Dates are never in forms.

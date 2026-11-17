# Rapport d'audit : les principes SOLID dans PokéShop

PokéShop est la boutique de cartes Pokémon réalisée pour l'exercice e-commerce EEMI M1 (Symfony 8.1, Doctrine, MariaDB).
Ce rapport reprend chaque décision de conception prise au nom des principes SOLID et de l'architecture
contrôleur / repository / service. Pour chacune, il indique où elle se trouve dans le code, comment elle est codée
et pourquoi on l'a préférée à une autre solution.

Une règle a guidé tout le projet : on n'ajoute une abstraction que si l'application en a réellement besoin.
SOLID ne veut pas dire "plus de fichiers". Un principe appliqué sans raison rend le code plus long à lire sans le rendre
plus solide, c'est pourquoi la partie 8 liste aussi les abstractions qu'on a refusé de créer.

Dans le code, les commentaires qui commencent par `SOLID (...)` ou `LAYER:` marquent les endroits cités ici.

## Sommaire

1. [Architecture contrôleur / repository / service](#1-architecture-contrôleur--repository--service)
2. [Prérequis : ce qu'est une interface](#2-prérequis--ce-quest-une-interface)
3. [S : responsabilité unique](#3-s--responsabilité-unique)
4. [O : ouvert / fermé](#4-o--ouvert--fermé)
5. [L : substitution de Liskov](#5-l--substitution-de-liskov)
6. [I : ségrégation des interfaces](#6-i--ségrégation-des-interfaces)
7. [D : inversion des dépendances](#7-d--inversion-des-dépendances)
8. [Les abstractions qu'on n'a pas créées](#8-les-abstractions-quon-na-pas-créées)
9. [Décisions métier liées à la conception](#9-décisions-métier-liées-à-la-conception)
10. [Récapitulatif](#10-récapitulatif)

---

## 1. Architecture contrôleur / repository / service

### Le rôle de chaque couche

| Couche | Dossier | Ce qu'elle fait | Ce qu'elle ne fait jamais |
|---|---|---|---|
| Contrôleur | [src/Controller](src/Controller) | lit la requête HTTP, vérifie les droits et le jeton CSRF, appelle un service ou un repository, ajoute un message flash, affiche une page ou redirige | écrire une requête SQL, appliquer une règle métier |
| Service | [src/Service](src/Service) | applique les règles métier : stock suffisant, fusion des quantités, paiement, transaction | manipuler `Request`, `Response` ou Twig |
| Repository | [src/Repository](src/Repository) | contient toutes les requêtes en base, nommées selon leur sens | décider quoi faire des données |
| Entité | [src/Entity](src/Entity) | porte les données, leurs règles de validation (`#[Assert]`) et de petits calculs sur ses propres champs (`Cart::getTotal()`) | interroger la base |

Les autres briques suivent la même logique. Les types de formulaire ([src/Form](src/Form)) décrivent des champs,
le voter ([src/Security/Voter/OrderVoter.php](src/Security/Voter/OrderVoter.php)) décide d'une permission, les templates
Twig affichent et les fichiers de [translations](translations) contiennent tous les textes visibles.

### Pourquoi ce découpage

Chaque couche change pour une raison différente. Le design d'une page, une requête lente ou une nouvelle règle de stock
touchent chacun un seul dossier. Deux personnes peuvent donc travailler en même temps sans se marcher dessus.

Les règles métier deviennent aussi réutilisables. `CartService` ne sait pas qu'il est appelé depuis une page web :
une API JSON ou une commande console pourrait l'appeler demain sans modification.

Enfin, une règle isolée se teste sans navigateur. [tests/Service](tests/Service) vérifie le stock et le paiement
sans base de données, alors que [tests/Controller/ShopFlowTest.php](tests/Controller/ShopFlowTest.php) vérifie le
parcours complet par HTTP.

### Exemple : "Ajouter au panier" (POST /cart/add/3)

```
Navigateur ── POST /cart/add/3 ──▶ CartController::add
                                     │  #[IsGranted('ROLE_USER')], formulaire de quantité validé
                                     ▼
                                   CartService::addProduct($user, $product, $quantité)
                                     │  cherche le panier ouvert, fusionne avec une ligne existante,
                                     │  refuse si la quantité dépasse le stock (ShopException)
                                     ▼
                                   CartRepository::findCurrentCartFor($user)
                                     ▼
                                   Base de données
```

Le contrôleur [CartController.php](src/Controller/CartController.php) ne contient aucune règle. Il attrape la
`ShopException`, la transforme en message flash rouge et redirige vers la fiche produit.

### Exemple : "Acheter" (POST /cart/checkout)

[CheckoutService::checkout](src/Service/CheckoutService.php) fait tout dans une seule transaction. Pour chaque ligne,
il relit le stock en verrouillant la ligne du produit, vérifie qu'il est suffisant et le diminue. Il appelle ensuite
la passerelle de paiement, puis marque le panier comme payé avec la date du jour. Si une exception survient à n'importe
quelle étape, rien n'est enregistré. Le paiement est appelé en dernier pour qu'un refus annule aussi la baisse de stock.

### L'exception assumée : la lecture seule

[SuperAdminController](src/Controller/SuperAdminController.php) et `ProductController::index` appellent directement un
repository. Il n'y a aucune règle à appliquer, seulement des données à afficher. Un `SuperAdminService` qui se
contenterait de relayer `findUnpaidCarts()` ajouterait un fichier sans rien apporter. Dès qu'il y a une décision à prendre
ou une écriture en base, le code passe par un service.

---

## 2. Prérequis : ce qu'est une interface

Les principes O, L, I et D reposent tous sur la notion d'interface. Une interface est un contrat : une liste de signatures
de méthodes, sans code à l'intérieur. Elle dit ce qu'un objet sait faire, jamais comment il le fait.

```php
// src/Service/Payment/PaymentGatewayInterface.php
interface PaymentGatewayInterface
{
    public function pay(int $amountInCents, string $reference): PaymentResult;
}
```

Une classe qui écrit `implements PaymentGatewayInterface` promet de fournir cette méthode avec exactement cette signature,
et PHP refuse de l'exécuter si la promesse n'est pas tenue.

Dans le projet, la chaîne complète se lit en quatre fichiers :

1. [PaymentGatewayInterface.php](src/Service/Payment/PaymentGatewayInterface.php) définit le contrat `pay()`.
2. [FakePaymentGateway.php](src/Service/Payment/FakePaymentGateway.php) l'implémente. Tous les paiements réussissent,
   ce que le sujet autorise ("le paiement peut être simulé"). L'attribut `#[AsAlias(PaymentGatewayInterface::class)]`
   indique à Symfony quelle classe fournir quand on lui demande l'interface.
3. [CheckoutService.php](src/Service/CheckoutService.php) demande l'interface dans son constructeur, jamais la classe concrète.
4. Le conteneur de services de Symfony construit l'ensemble tout seul (autowiring) : il voit que `CheckoutService` a besoin
   d'un `PaymentGatewayInterface`, trouve l'alias vers `FakePaymentGateway`, crée les deux objets et les relie.

On peut comparer l'interface à une prise électrique. Le chargeur (`CheckoutService`) connaît seulement la forme de la prise.
Il fonctionne de la même façon que le courant vienne d'une centrale ou de panneaux solaires (`FakePaymentGateway`,
demain une passerelle Stripe).

---

## 3. S : responsabilité unique

Une classe ne doit avoir qu'une seule raison de changer, c'est-à-dire un seul sujet ou une seule personne susceptible
de demander des modifications.

### Où et comment

| Classe | Sa responsabilité | Qui demanderait de la modifier |
|---|---|---|
| [ProductController](src/Controller/ProductController.php) | traduire les requêtes HTTP du catalogue en appels et en pages | l'équipe web |
| [CartRepository](src/Repository/CartRepository.php) | récupérer les paniers en base | la personne qui gère la base |
| [CartService](src/Service/CartService.php) | les règles du panier (panier courant, fusion des quantités, limite de stock) | le métier |
| [CheckoutService](src/Service/CheckoutService.php) | transformer un panier en commande payée | le métier (paiement, stock) |
| [FileUploader](src/Service/FileUploader.php) | déplacer des fichiers sur le disque | le stockage de fichiers uniquement |
| [OrderVoter](src/Security/Voter/OrderVoter.php) | répondre à "cet utilisateur peut-il voir cette commande ?" | la sécurité |
| [cart/_table.html.twig](templates/cart/_table.html.twig) | afficher les lignes d'un panier | le design |

Quelques choix précis découlent de ce principe.

`CartService` et `CheckoutService` sont séparés. Ajouter un produit et payer sont deux processus distincts, avec des
dépendances différentes : seul le paiement a besoin d'une passerelle.

`FileUploader` ne connaît pas les produits. Il génère un nom aléatoire, déplace le fichier et sait le supprimer.
La décision de remplacer l'ancienne photo d'un produit appartient à [ProductService](src/Service/ProductService.php).
Le même uploader pourrait donc gérer des avatars sans changement.

Le tableau des lignes de panier est un template partiel unique, inclus par la page panier, la page de commande et le
tableau de bord super admin. Une modification de colonne se fait à un seul endroit.

Les erreurs métier passent par [ShopException](src/Exception/ShopException.php), qui transporte une clé de traduction et
ses paramètres. Le service décide de l'erreur, le contrôleur la transforme en message flash et Twig la traduit dans la
langue du visiteur. Aucune de ces trois briques ne fait le travail d'une autre.

### Pourquoi

Sans ce principe, le paiement serait écrit directement dans le contrôleur : requête SQL, boucle sur les lignes,
vérification du stock et appel au paiement dans une seule méthode. Pour ajouter une API mobile, il faudrait copier cette
logique, et les deux copies finiraient par diverger. Il serait aussi impossible de tester la règle "stock insuffisant"
sans simuler une requête HTTP, une session et un utilisateur connecté. Avec le découpage actuel,
[CheckoutServiceTest](tests/Service/CheckoutServiceTest.php) la teste en quelques lignes, sans base de données.

---

## 4. O : ouvert / fermé

Le code doit être ouvert à l'extension et fermé à la modification. On ajoute un comportement en ajoutant du code
(une classe, une ligne de configuration), pas en retouchant du code qui fonctionne déjà.

### Où et comment

Le premier cas concerne les passerelles de paiement. [CheckoutService](src/Service/CheckoutService.php) ne connaît que
`PaymentGatewayInterface`. Pour passer à Stripe, on écrit une classe `StripePaymentGateway` qui implémente l'interface et
on déplace l'attribut `#[AsAlias]` sur elle. `CheckoutService` n'est pas modifié, il ne peut donc pas être cassé.

Le deuxième cas est la hiérarchie des rôles, dans [config/packages/security.yaml](config/packages/security.yaml) :

```yaml
role_hierarchy:
    ROLE_ADMIN: ROLE_USER
    ROLE_SUPER_ADMIN: ROLE_ADMIN
```

Le sujet impose que le super admin ait tous les droits de l'admin. Aucune condition du type
`if ($role === 'ROLE_ADMIN' || $role === 'ROLE_SUPER_ADMIN')` n'a été écrite : chaque `#[IsGranted('ROLE_ADMIN')]`
accepte automatiquement le super admin. Un nouveau rôle s'ajoute dans ce fichier, sans toucher aux contrôleurs.

Le troisième cas est le voter. La page de commande déclare seulement
`#[IsGranted(OrderVoter::VIEW, subject: 'order')]` dans [AccountController](src/Controller/AccountController.php).
Une nouvelle règle (par exemple "le service client voit toutes les commandes") s'ajouterait dans un voter, sans modifier
le contrôleur.

Le dernier cas concerne les formulaires. [RegistrationFormType](src/Form/RegistrationFormType.php) déclare
`getParent(): ProfileType::class` et ajoute seulement le champ mot de passe. [ProfileType](src/Form/ProfileType.php)
reste intact, et une modification d'un champ du profil s'applique aux deux formulaires.

### Pourquoi

L'alternative serait une méthode `checkout()` remplie de `if ($provider === 'stripe') ... elseif ($provider === 'paypal')`.
Chaque nouveau prestataire obligerait à rouvrir et retester une méthode qui marche, et une faute de frappe dans la branche
PayPal pourrait casser les paiements Stripe.

---

## 5. L : substitution de Liskov

Si un code fonctionne avec un type, il doit continuer à fonctionner avec n'importe quel sous-type ou implémentation de
ce type. Respecter les noms de méthodes ne suffit pas : il faut respecter tout le contrat (ce qui est accepté en entrée,
ce qui est renvoyé, les exceptions possibles).

### Où et comment

Le contrat des passerelles de paiement est écrit noir sur blanc dans la documentation de
[PaymentGatewayInterface](src/Service/Payment/PaymentGatewayInterface.php) : la méthode renvoie toujours un `PaymentResult`,
et un paiement refusé est un résultat normal (`PaymentResult::declined()`), pas une exception.

Ce contrat est vérifié par les tests. [CheckoutServiceTest](tests/Service/CheckoutServiceTest.php) définit deux autres
implémentations, `AcceptingGateway` et `DecliningGateway`, et les passe à `CheckoutService` à la place de la vraie passerelle.
Le test `testDeclinedPaymentThrowsAndLeavesCartUnpaid` montre que le service gère le refus correctement sans savoir quelle
implémentation il a reçue.

Le principe s'applique aussi à l'héritage. Les repositories, comme [ProductRepository](src/Repository/ProductRepository.php),
étendent `ServiceEntityRepository` et ne font qu'ajouter des méthodes (`findCatalog()`). `find()` et `findBy()` gardent leur
comportement d'origine, donc tout code qui attend un repository Doctrine peut les utiliser. De même,
[OrderVoter](src/Security/Voter/OrderVoter.php) étend la classe `Voter` de Symfony et renvoie un booléen comme prévu :
Symfony l'interroge exactement comme ses propres voters.

### Pourquoi

Une passerelle qui lèverait une exception sur un refus aurait la bonne signature et passerait la compilation.
Pourtant, `CheckoutService` ne s'attend pas à cette exception : l'utilisateur verrait une page d'erreur 500 au lieu du
message "Le paiement a été refusé". Si on devait vérifier quelle implémentation on a reçue avant de l'utiliser
(`instanceof`), l'interface ne servirait plus à rien et les bénéfices de O et de D disparaîtraient. Écrire le contrat dans
la documentation de l'interface, puis le tester avec une implémentation différente, rend ce risque visible.

---

## 6. I : ségrégation des interfaces

Aucune classe ne doit être obligée d'implémenter, ou de dépendre de, méthodes qu'elle n'utilise pas. Mieux vaut plusieurs
petites interfaces qu'une grosse interface qui fait tout.

### Où et comment

L'entité [User](src/Entity/User.php) implémente deux interfaces de Symfony : `UserInterface` ("peut être connecté" :
identifiant et rôles) et `PasswordAuthenticatedUserInterface` ("se connecte avec un mot de passe"). Symfony les a séparées
volontairement. Un utilisateur qui se connecterait par Google ou par lien magique n'implémenterait que la première, sans
méthode `getPassword()` factice.

Notre propre [PaymentGatewayInterface](src/Service/Payment/PaymentGatewayInterface.php) ne contient qu'une méthode, `pay()`.
La boutique ne rembourse pas et ne gère pas d'abonnements, donc l'interface ne demande ni `refund()` ni `createSubscription()`.

### Pourquoi

Avec une interface qui exigerait aussi `refund()`, `createSubscription()` et un export comptable, `FakePaymentGateway` devrait
fournir trois méthodes qui lèvent "non supporté". Ce serait aussi une violation de Liskov en puissance : un appelant pourrait
croire qu'un remboursement fonctionne. Chaque changement de `refund()` forcerait en plus à modifier des classes qui ne
remboursent jamais. Le jour où les remboursements seront nécessaires, une interface séparée `RefundableGatewayInterface`
sera implémentée uniquement par les passerelles qui savent rembourser.

---

## 7. D : inversion des dépendances

Le code de haut niveau (les règles métier) ne doit pas dépendre des détails de bas niveau (une banque précise, un disque
précis). Les deux dépendent d'une abstraction. Concrètement, une classe reçoit ce dont elle a besoin au lieu de le créer
elle-même avec `new`.

### Où et comment

`CheckoutService` (le haut niveau : comment on achète) dépend de `PaymentGatewayInterface` (l'abstraction), pas de
`FakePaymentGateway` (le détail). Le sens de la dépendance est inversé : c'est le détail qui dépend de l'abstraction,
puisqu'il l'implémente.

```
Sans inversion :  CheckoutService ─────────────▶ FakePaymentGateway
Avec inversion :  CheckoutService ──▶ PaymentGatewayInterface ◀── FakePaymentGateway
```

Toutes les dépendances arrivent par le constructeur ou par les arguments des actions : `CartService $cartService`,
`ProductRepository $products`, et ainsi de suite. On ne trouve aucun `new CartService()` dans `src/`. Le conteneur de Symfony
construit et relie les objets ([config/services.yaml](config/services.yaml), `autowire: true`).

On demande toujours les interfaces de Symfony plutôt que leurs classes concrètes : `EntityManagerInterface`,
`UserPasswordHasherInterface`, `AccessDecisionManagerInterface`.

Le choix de passer par `#[AsAlias]` plutôt que par une ligne dans `services.yaml` suit les bonnes pratiques Symfony :
l'information "quelle implémentation est active" se trouve sur la classe concernée.

### Pourquoi

Si `CheckoutService` créait lui-même `new StripePaymentGateway('sk_live_...')`, chaque exécution des tests débiterait une vraie
carte, et la clé secrète serait écrite dans le code. Grâce à l'injection, les tests unitaires de
[CheckoutServiceTest](tests/Service/CheckoutServiceTest.php) et [CartServiceTest](tests/Service/CartServiceTest.php)
passent de fausses dépendances (une passerelle de test, un `EntityManager` simulé) et ne touchent ni banque ni base de données.
Changer de prestataire ne demande pas non plus de modifier le code métier.

---

## 8. Les abstractions qu'on n'a pas créées

Appliquer SOLID, c'est aussi savoir s'arrêter. Chaque refus ci-dessous suit le principe YAGNI ("You Aren't Gonna Need It" :
on n'écrit pas ce dont on n'a pas besoin aujourd'hui).

| Non créé | Raison |
|---|---|
| `CartServiceInterface`, `ProductServiceInterface`, `UserServiceInterface` | une seule implémentation, rien à remplacer, et les tests utilisent la vraie classe sans difficulté |
| une classe d'exception par erreur métier | une seule `ShopException` qui porte une clé de traduction couvre tous les cas |
| un service pour le tableau de bord super admin | page en lecture seule, aucune règle à appliquer |
| un `RegistrationController` séparé | l'inscription tient en une quinzaine de lignes, elle est rangée à côté de la connexion dans [SecurityController](src/Controller/SecurityController.php) |

On crée une interface quand au moins une de ces conditions est remplie : il existe (ou il existera clairement) plusieurs
implémentations, ou bien il faut remplacer la vraie implémentation dans les tests parce qu'elle est lente, coûteuse ou
externe. La passerelle de paiement remplit les deux. Les services du panier ou des produits n'en remplissent aucune.

---

## 9. Décisions métier liées à la conception

Ces choix ne sont pas des principes SOLID au sens strict, mais ils découlent du même découpage et sont implémentés
dans la couche indiquée.

Les prix sont stockés en centimes (entier) dans [Product](src/Entity/Product.php). Les nombres à virgule produisent des
erreurs d'arrondi sur les montants. Le formulaire ([ProductType](src/Form/ProductType.php)) affiche des euros grâce à
`MoneyType` avec `divisor: 100`, et Twig formate le prix selon la langue.

Ajouter une carte déjà présente dans le panier augmente sa quantité au lieu de créer une deuxième ligne, et la quantité
ne peut pas dépasser le stock. Ces deux règles sont dans `CartService::addProduct` et testées dans
[CartServiceTest](tests/Service/CartServiceTest.php).

Le paiement se fait dans une transaction avec verrouillage des lignes produit (`LockMode::PESSIMISTIC_WRITE`), dans
`CheckoutService`. Deux acheteurs ne peuvent pas prendre la dernière carte en même temps, et un paiement refusé annule la
baisse du stock.

Un produit présent dans un panier ou une commande ne peut pas être supprimé : `ProductService::delete` le refuse avec un
message qui invite à passer le stock à 0. Sinon, la suppression effacerait l'historique des commandes.

Les dates (`createdAt` de l'utilisateur, `addedAt` d'une ligne, `purchasedAt` d'une commande) sont fixées par le code,
dans les constructeurs ou au paiement, et n'apparaissent dans aucun formulaire, comme l'exige le sujet. Le champ
`createdAt` a été ajouté à l'utilisateur parce que la liste "inscrits aujourd'hui" du super admin en a besoin.

L'accès aux pages est contrôlé deux fois : par URL dans `access_control` ([security.yaml](config/packages/security.yaml))
et par `#[IsGranted]` sur chaque contrôleur. Si l'une des deux protections est mal configurée, l'autre bloque encore l'accès.

---

## 10. Récapitulatif

| Principe | Fichiers principaux | Bénéfice concret dans PokéShop |
|---|---|---|
| S | [CartService](src/Service/CartService.php), [CheckoutService](src/Service/CheckoutService.php), [FileUploader](src/Service/FileUploader.php), [OrderVoter](src/Security/Voter/OrderVoter.php), [cart/_table.html.twig](templates/cart/_table.html.twig) | des contrôleurs de quelques lignes, des règles testées sans HTTP, un tableau de panier modifié à un seul endroit |
| O | [PaymentGatewayInterface](src/Service/Payment/PaymentGatewayInterface.php), [security.yaml](config/packages/security.yaml), [OrderVoter](src/Security/Voter/OrderVoter.php), [RegistrationFormType](src/Form/RegistrationFormType.php) | nouveau prestataire de paiement, nouveau rôle ou nouvelle règle d'accès sans modifier le code existant |
| L | [PaymentGatewayInterface](src/Service/Payment/PaymentGatewayInterface.php), [CheckoutServiceTest](tests/Service/CheckoutServiceTest.php), repositories, [OrderVoter](src/Security/Voter/OrderVoter.php) | n'importe quelle passerelle qui respecte le contrat fonctionne, ce que le test avec `DecliningGateway` vérifie |
| I | [User](src/Entity/User.php), [PaymentGatewayInterface](src/Service/Payment/PaymentGatewayInterface.php) | aucune méthode factice à écrire |
| D | [CheckoutService](src/Service/CheckoutService.php), [FakePaymentGateway](src/Service/Payment/FakePaymentGateway.php), tous les constructeurs | tests sans base ni banque, prestataire interchangeable |

Les 15 tests du projet (7 tests unitaires des services, 8 tests fonctionnels du parcours complet) se lancent avec
`php bin/phpunit`.

<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\CartItem;
use App\Entity\Product;
use App\Entity\User;
use App\Exception\ShopException;
use App\Form\AddToCartType;
use App\Service\CartService;
use App\Service\CheckoutService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function Symfony\Component\Translation\t;

/**
 * LAYER: Controller — the cart pages. All rules ("enough stock?", "is it yours?", "pay")
 * are in CartService / CheckoutService; this class only translates HTTP into service calls.
 */
#[IsGranted('ROLE_USER')]
#[Route('/cart')]
final class CartController extends AbstractController
{
    // SOLID (Dependency Inversion): the services are injected, never created with `new`.
    public function __construct(
        private readonly CartService $cartService,
        private readonly CheckoutService $checkoutService,
    ) {
    }

    #[Route('', name: 'app_cart', methods: ['GET'])]
    public function index(#[CurrentUser] User $user): Response
    {
        return $this->render('cart/index.html.twig', ['cart' => $this->cartService->getCurrentCart($user)]);
    }

    #[Route('/add/{id}', name: 'app_cart_add', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function add(Product $product, Request $request, #[CurrentUser] User $user): Response
    {
        $form = $this->createForm(AddToCartType::class, null, ['max_quantity' => $product->getStock()]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('danger', t('cart.error.invalid_quantity'));
        } else {
            try {
                $this->cartService->addProduct($user, $product, (int) $form->get('quantity')->getData());
                $this->addFlash('success', t('cart.flash.added', ['%name%' => (string) $product->getName()]));
            } catch (ShopException $e) {
                $this->addFlash('danger', $e->toTranslatable());
            }
        }

        return $this->redirectToRoute('app_product_show', ['id' => $product->getId()]);
    }

    #[Route('/remove/{id}', name: 'app_cart_remove', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function remove(CartItem $item, Request $request, #[CurrentUser] User $user): Response
    {
        if (!$this->isCsrfTokenValid('remove-item-'.$item->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', t('flash.invalid_csrf'));

            return $this->redirectToRoute('app_cart');
        }

        try {
            $this->cartService->removeItem($user, $item);
            $this->addFlash('success', t('cart.flash.removed'));
        } catch (ShopException $e) {
            $this->addFlash('danger', $e->toTranslatable());
        }

        return $this->redirectToRoute('app_cart');
    }

    #[Route('/checkout', name: 'app_cart_checkout', methods: ['POST'])]
    public function checkout(Request $request, #[CurrentUser] User $user): Response
    {
        if (!$this->isCsrfTokenValid('checkout', $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', t('flash.invalid_csrf'));

            return $this->redirectToRoute('app_cart');
        }

        $cart = $this->cartService->getCurrentCart($user);
        try {
            $this->checkoutService->checkout($cart);
        } catch (ShopException $e) {
            $this->addFlash('danger', $e->toTranslatable());

            return $this->redirectToRoute('app_cart');
        }

        $this->addFlash('success', t('checkout.flash.success'));

        return $this->redirectToRoute('app_account_order', ['id' => $cart->getId()]);
    }
}

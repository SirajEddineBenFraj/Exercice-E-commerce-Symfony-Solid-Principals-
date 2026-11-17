<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Cart;
use App\Entity\User;
use App\Form\ProfileType;
use App\Repository\CartRepository;
use App\Security\Voter\OrderVoter;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function Symfony\Component\Translation\t;

/**
 * LAYER: Controller — "My account": profile form, order history, order detail.
 */
#[IsGranted('ROLE_USER')]
#[Route('/account')]
final class AccountController extends AbstractController
{
    #[Route('', name: 'app_account', methods: ['GET', 'POST'])]
    public function index(Request $request, #[CurrentUser] User $user, UserService $userService, CartRepository $carts): Response
    {
        $form = $this->createForm(ProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $userService->saveProfile($user);
            $this->addFlash('success', t('account.flash.saved'));

            return $this->redirectToRoute('app_account');
        }

        $response = $this->render('account/index.html.twig', [
            'form' => $form,
            'orders' => $carts->findPaidCartsFor($user),
        ]);

        if ($form->isSubmitted()) {
            // Invalid form: the page above already shows the typed values and errors, now undo them in memory.
            $userService->discardProfileChanges($user);
        }

        return $response;
    }

    /**
     * Order detail. OrderVoter decides who may see it (owner or super admin): the rule is not written here.
     */
    #[Route('/order/{id}', name: 'app_account_order', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(OrderVoter::VIEW, subject: 'order')]
    public function order(Cart $order): Response
    {
        if (!$order->isPaid()) {
            throw $this->createNotFoundException(); // an unpaid cart is not an order yet
        }

        return $this->render('account/order.html.twig', ['order' => $order]);
    }
}

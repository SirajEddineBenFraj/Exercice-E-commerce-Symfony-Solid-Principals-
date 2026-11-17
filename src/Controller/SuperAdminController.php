<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\CartRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * LAYER: Controller — super admin dashboard. Read-only page, so it talks to repositories directly:
 * there is no business rule to put in a service (a pass-through service would just add a file).
 */
#[IsGranted('ROLE_SUPER_ADMIN')]
final class SuperAdminController extends AbstractController
{
    #[Route('/admin/super', name: 'app_super_admin', methods: ['GET'])]
    public function index(CartRepository $carts, UserRepository $users): Response
    {
        return $this->render('super_admin/index.html.twig', [
            'unpaidCarts' => $carts->findUnpaidCarts(),
            'usersToday' => $users->findRegisteredToday(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Product;
use App\Exception\ShopException;
use App\Form\AddToCartType;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use App\Service\ProductService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function Symfony\Component\Translation\t;

/**
 * LAYER: Controller — HTTP only. Each action: read the request, check access, call a
 * repository (to read) or a service (to change things), then render or redirect.
 * You will not find a SQL query or a business rule in this file.
 */
final class ProductController extends AbstractController
{
    /** Home page: the card catalog. */
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(ProductRepository $products): Response
    {
        return $this->render('product/index.html.twig', ['products' => $products->findCatalog()]);
    }

    /** Product page. Symfony turns {id} into a Product (404 if it does not exist). */
    #[Route('/product/{id}', name: 'app_product_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Product $product): Response
    {
        $form = $this->createForm(AddToCartType::class, null, [
            'action' => $this->generateUrl('app_cart_add', ['id' => $product->getId()]),
            'max_quantity' => $product->getStock(),
        ]);

        return $this->render('product/show.html.twig', ['product' => $product, 'form' => $form]);
    }

    #[Route('/admin/product/new', name: 'app_product_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request, ProductService $productService): Response
    {
        return $this->handleProductForm(new Product(), $request, $productService, 'product.new.title');
    }

    #[Route('/admin/product/{id}/edit', name: 'app_product_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Product $product, Request $request, ProductService $productService): Response
    {
        return $this->handleProductForm($product, $request, $productService, 'product.edit.title');
    }

    #[Route('/admin/product/{id}/delete', name: 'app_product_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Product $product, Request $request, ProductService $productService): Response
    {
        if (!$this->isCsrfTokenValid('delete-product-'.$product->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', t('flash.invalid_csrf'));

            return $this->redirectToRoute('app_product_show', ['id' => $product->getId()]);
        }

        try {
            $productService->delete($product);
        } catch (ShopException $e) {
            $this->addFlash('danger', $e->toTranslatable());

            return $this->redirectToRoute('app_product_show', ['id' => $product->getId()]);
        }

        $this->addFlash('success', t('product.flash.deleted'));

        return $this->redirectToRoute('app_home');
    }

    /** Same form for create and edit: only the title changes. */
    private function handleProductForm(Product $product, Request $request, ProductService $productService, string $title): Response
    {
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $productService->save($product, $form->get('photoFile')->getData());
            $this->addFlash('success', t('product.flash.saved'));

            return $this->redirectToRoute('app_product_show', ['id' => $product->getId()]);
        }

        // Passing the form object makes Symfony answer 422 when it was submitted with errors.
        return $this->render('product/form.html.twig', ['form' => $form, 'title' => $title, 'product' => $product]);
    }
}

<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Product;
use App\Exception\ShopException;
use App\Repository\CartItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * LAYER: Service — rules for creating, editing and deleting products (admin side).
 */
final class ProductService
{
    // SOLID (Dependency Inversion): everything arrives through the constructor (autowiring);
    // this class never does `new FileUploader()` itself, so tests can pass fakes.
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FileUploader $fileUploader,
        private readonly CartItemRepository $cartItems,
    ) {
    }

    /** Creates or updates a product; a new photo replaces (and deletes) the old one. */
    public function save(Product $product, ?UploadedFile $photo = null): void
    {
        if (null !== $photo) {
            $oldPhoto = $product->getPhoto();
            $product->setPhoto($this->fileUploader->upload($photo));
            $this->fileUploader->remove($oldPhoto);
        }

        $this->entityManager->persist($product);
        $this->entityManager->flush();
    }

    /**
     * @throws ShopException when the product is in a cart or an order: deleting it would erase order history
     */
    public function delete(Product $product): void
    {
        if ($this->cartItems->existsForProduct($product)) {
            throw new ShopException('product.error.in_use', ['%name%' => (string) $product->getName()]);
        }

        $photo = $product->getPhoto();
        $this->entityManager->remove($product);
        $this->entityManager->flush();
        $this->fileUploader->remove($photo);
    }
}

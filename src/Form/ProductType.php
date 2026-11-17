<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Product;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

/**
 * Admin form to create / edit a card. Field rules come from the #[Assert] attributes on Product;
 * only the photo (not stored as-is on the entity) gets its own constraint here.
 */
final class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'product.field.name'])
            ->add('description', TextareaType::class, ['label' => 'product.field.description', 'attr' => ['rows' => 5]])
            // The user types 12,50 and we store 1250 cents.
            ->add('price', MoneyType::class, ['label' => 'product.field.price', 'currency' => 'EUR', 'divisor' => 100, 'input' => 'integer'])
            ->add('stock', IntegerType::class, ['label' => 'product.field.stock', 'attr' => ['min' => 0]])
            ->add('photoFile', FileType::class, [
                'label' => 'product.field.photo',
                'mapped' => false, // the uploaded file goes to ProductService, the entity only keeps the file name
                'required' => false,
                'constraints' => [
                    new Image(maxSize: '2M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp']),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Product::class]);
    }
}

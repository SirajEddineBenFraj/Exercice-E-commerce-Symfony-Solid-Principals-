<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

/**
 * Quantity picker on the product page. Not linked to an entity: CartService decides what happens.
 * The stock limit is checked again in CartService (never trust the browser's max attribute).
 */
final class AddToCartType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('quantity', IntegerType::class, [
            'label' => 'cart.field.quantity',
            'data' => 1,
            'attr' => ['min' => 1, 'max' => $options['max_quantity']],
            'constraints' => [new NotBlank(), new Positive()],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['max_quantity' => null]);
        $resolver->setAllowedTypes('max_quantity', ['null', 'int']);
    }
}

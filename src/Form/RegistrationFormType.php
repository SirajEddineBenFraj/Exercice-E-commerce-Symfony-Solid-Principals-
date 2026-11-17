<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Sign-up form = the profile fields + a password.
 *
 * SOLID (Open/Closed): getParent() reuses ProfileType's fields and ADDS the password,
 * without modifying ProfileType. Change a profile field once, both forms follow.
 */
final class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('plainPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'mapped' => false, // never put the plain password on the entity; UserService hashes it
            'first_options' => ['label' => 'user.field.password', 'attr' => ['autocomplete' => 'new-password']],
            'second_options' => ['label' => 'user.field.password_repeat', 'attr' => ['autocomplete' => 'new-password']],
            'invalid_message' => 'user.password.mismatch',
            'constraints' => [new NotBlank(), new Length(min: 8, max: 4096)],
        ]);
    }

    public function getParent(): string
    {
        return ProfileType::class;
    }
}

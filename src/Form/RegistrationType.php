<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Contracts\Translation\TranslatorInterface;

class RegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'label' => 'auth.register_page.username',
                'attr' => [
                    'placeholder' => 'auth.register_page.username',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank(message: 'username.not_blank'),
                    new Length(
                        min: 3, 
                        max: 180,
                        minMessage: 'username.min_length',
                        maxMessage: 'username.max_length'
                    ),
                    new Regex(
                        pattern: '/^[a-zA-Z0-9_-]+$/',
                        message: 'username.invalid_characters'
                    )
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'auth.register_page.email',
                'attr' => [
                    'placeholder' => 'auth.register_page.email',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank(message: 'email.not_blank'),
                    new Email(
                        message: 'email.invalid',
                        mode: Email::VALIDATION_MODE_STRICT
                    ),
                    new Length(
                        max: 180,
                        maxMessage: 'email.max_length'
                    )
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => [
                    'label' => 'auth.register_page.password',
                    'attr' => [
                        'placeholder' => 'auth.register_page.password',
                        'class' => 'form-control'
                    ],
                    'constraints' => [
                        new NotBlank(message: 'password.not_blank'),
                        new Length(
                            min: 8, 
                            max: 128,
                            minMessage: 'password.min_length',
                            maxMessage: 'password.max_length'
                        ),
                        new Regex(
                            pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/',
                            message: 'password.weak'
                        ),
                        new NotCompromisedPassword(
                            message: 'password.compromised'
                        )
                    ],
                ],
                'second_options' => [
                    'label' => 'auth.register_page.password',
                    'attr' => [
                        'placeholder' => 'auth.register_page.password',
                        'class' => 'form-control'
                    ]
                ],
                'invalid_message' => 'password.mismatch',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}

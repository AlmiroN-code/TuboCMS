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

class RegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'label' => 'Имя пользователя',
                'attr' => [
                    'placeholder' => 'Введите имя пользователя',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Введите имя пользователя']),
                    new Length([
                        'min' => 3, 
                        'max' => 180,
                        'minMessage' => 'Имя пользователя должно содержать минимум {{ limit }} символа',
                        'maxMessage' => 'Имя пользователя не может быть длиннее {{ limit }} символов'
                    ]),
                    new Regex([
                        'pattern' => '/^[a-zA-Z0-9_-]+$/',
                        'message' => 'Имя пользователя может содержать только буквы, цифры, дефисы и подчеркивания'
                    ])
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => [
                    'placeholder' => 'Введите email адрес',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Введите email']),
                    new Email([
                        'message' => 'Введите корректный email адрес',
                        'mode' => 'strict'
                    ]),
                    new Length([
                        'max' => 180,
                        'maxMessage' => 'Email не может быть длиннее {{ limit }} символов'
                    ])
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => [
                    'label' => 'Пароль',
                    'attr' => [
                        'placeholder' => 'Введите пароль',
                        'class' => 'form-control'
                    ],
                    'constraints' => [
                        new NotBlank(['message' => 'Введите пароль']),
                        new Length([
                            'min' => 8, 
                            'max' => 128,
                            'minMessage' => 'Пароль должен содержать минимум {{ limit }} символов',
                            'maxMessage' => 'Пароль не может быть длиннее {{ limit }} символов'
                        ]),
                        new Regex([
                            'pattern' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/',
                            'message' => 'Пароль должен содержать минимум одну строчную букву, одну заглавную букву и одну цифру'
                        ]),
                        new NotCompromisedPassword([
                            'message' => 'Этот пароль был скомпрометирован в утечках данных. Выберите другой пароль.'
                        ])
                    ],
                ],
                'second_options' => [
                    'label' => 'Повторите пароль',
                    'attr' => [
                        'placeholder' => 'Повторите пароль',
                        'class' => 'form-control'
                    ]
                ],
                'invalid_message' => 'Пароли должны совпадать',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}

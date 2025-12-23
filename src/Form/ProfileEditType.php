<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class ProfileEditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('avatarFile', FileType::class, [
                'label' => 'Аватар',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File(
                        maxSize: '5M',
                        mimeTypes: [
                            'image/jpeg',
                            'image/png',
                            'image/gif',
                            'image/webp',
                        ],
                        mimeTypesMessage: 'Пожалуйста, загрузите изображение (JPEG, PNG, GIF, WebP)',
                    )
                ],
            ])
            ->add('coverImageFile', FileType::class, [
                'label' => 'Обложка профиля',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File(
                        maxSize: '10M',
                        mimeTypes: [
                            'image/jpeg',
                            'image/png',
                            'image/gif',
                            'image/webp',
                        ],
                        mimeTypesMessage: 'Пожалуйста, загрузите изображение (JPEG, PNG, GIF, WebP)',
                    )
                ],
            ])
            ->add('country', ChoiceType::class, [
                'label' => 'Страна',
                'required' => false,
                'placeholder' => 'Выберите страну',
                'choices' => $this->getCountries(),
            ])
            ->add('city', TextType::class, [
                'label' => 'Город',
                'required' => false,
                'attr' => ['placeholder' => 'Введите город'],
            ])
            ->add('gender', ChoiceType::class, [
                'label' => 'Пол',
                'required' => false,
                'placeholder' => 'Выберите пол',
                'choices' => [
                    'Мужской' => 'male',
                    'Женский' => 'female',
                ],
            ])
            ->add('maritalStatus', ChoiceType::class, [
                'label' => 'Семейное положение',
                'required' => false,
                'placeholder' => 'Выберите статус',
                'choices' => [
                    'Не женат/Не замужем' => 'single',
                    'В отношениях' => 'in_relationship',
                    'Помолвлен(а)' => 'engaged',
                    'Женат/Замужем' => 'married',
                    'В разводе' => 'divorced',
                    'Вдовец/Вдова' => 'widowed',
                ],
            ])
            ->add('birthDate', DateType::class, [
                'label' => 'Дата рождения',
                'required' => false,
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['max' => (new \DateTime())->format('Y-m-d')],
            ])
            ->add('orientation', ChoiceType::class, [
                'label' => 'Ориентация',
                'required' => false,
                'placeholder' => 'Выберите ориентацию',
                'choices' => [
                    'Гетеросексуал' => 'heterosexual',
                    'Гомосексуал' => 'homosexual',
                    'Бисексуал' => 'bisexual',
                    'Другое' => 'other',
                ],
            ])
            ->add('bio', TextareaType::class, [
                'label' => 'Обо мне',
                'required' => false,
                'attr' => [
                    'rows' => 5,
                    'placeholder' => 'Расскажите о себе...',
                    'maxlength' => 1000,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }

    private function getCountries(): array
    {
        return [
            'Россия' => 'RU',
            'Украина' => 'UA',
            'Беларусь' => 'BY',
            'Казахстан' => 'KZ',
            'США' => 'US',
            'Великобритания' => 'GB',
            'Германия' => 'DE',
            'Франция' => 'FR',
            'Италия' => 'IT',
            'Испания' => 'ES',
            'Канада' => 'CA',
            'Австралия' => 'AU',
            'Япония' => 'JP',
            'Китай' => 'CN',
            'Индия' => 'IN',
            'Бразилия' => 'BR',
            'Мексика' => 'MX',
            'Аргентина' => 'AR',
            'Польша' => 'PL',
            'Турция' => 'TR',
        ];
    }
}

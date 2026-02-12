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
                'label' => 'profile.avatar',
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
                        mimeTypesMessage: 'profile.avatar.invalid_type',
                    )
                ],
            ])
            ->add('coverImageFile', FileType::class, [
                'label' => 'profile.cover',
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
                        mimeTypesMessage: 'profile.cover.invalid_type',
                    )
                ],
            ])
            ->add('country', ChoiceType::class, [
                'label' => 'members.country',
                'required' => true,
                'placeholder' => 'common.select',
                'choices' => $this->getCountries(),
            ])
            ->add('city', TextType::class, [
                'label' => 'members.city',
                'required' => false,
                'attr' => ['placeholder' => 'members.city'],
            ])
            ->add('gender', ChoiceType::class, [
                'label' => 'members.gender',
                'required' => false,
                'placeholder' => 'common.select',
                'choices' => [
                    'members.gender_male' => 'male',
                    'members.gender_female' => 'female',
                ],
            ])
            ->add('maritalStatus', ChoiceType::class, [
                'label' => 'members.marital_status',
                'required' => false,
                'placeholder' => 'common.select',
                'choices' => [
                    'members.marital_single' => 'single',
                    'members.marital_in_relationship' => 'in_relationship',
                    'members.marital_engaged' => 'engaged',
                    'members.marital_married' => 'married',
                    'members.marital_divorced' => 'divorced',
                    'members.marital_widowed' => 'widowed',
                ],
            ])
            ->add('birthDate', DateType::class, [
                'label' => 'profile.birth_date',
                'required' => false,
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['max' => (new \DateTime())->format('Y-m-d')],
            ])
            ->add('orientation', ChoiceType::class, [
                'label' => 'members.orientation',
                'required' => false,
                'placeholder' => 'common.select',
                'choices' => [
                    'members.orientation_heterosexual' => 'heterosexual',
                    'members.orientation_homosexual' => 'homosexual',
                    'members.orientation_bisexual' => 'bisexual',
                    'members.orientation_other' => 'other',
                ],
            ])
            ->add('bio', TextareaType::class, [
                'label' => 'members.about',
                'required' => false,
                'attr' => [
                    'rows' => 5,
                    'placeholder' => 'profile.bio_placeholder',
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

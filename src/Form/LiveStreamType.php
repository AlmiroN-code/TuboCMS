<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Channel;
use App\Entity\LiveStream;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class LiveStreamType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Название стрима',
                'attr' => [
                    'class' => 'px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors w-full',
                    'placeholder' => 'Введите название стрима',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Описание',
                'required' => false,
                'attr' => [
                    'class' => 'px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors w-full',
                    'rows' => 4,
                    'placeholder' => 'Опишите ваш стрим',
                ],
            ])
            ->add('channel', EntityType::class, [
                'label' => 'Канал',
                'class' => Channel::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'Выберите канал (опционально)',
                'attr' => [
                    'class' => 'px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors w-full bg-white',
                ],
            ])
            ->add('scheduledAt', DateTimeType::class, [
                'label' => 'Запланировать на',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors w-full',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LiveStream::class,
        ]);
    }
}

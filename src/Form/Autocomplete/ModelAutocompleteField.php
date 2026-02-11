<?php

namespace App\Form\Autocomplete;

use App\Entity\ModelProfile;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;
use Symfony\UX\Autocomplete\Form\BaseEntityAutocompleteType;

#[AsEntityAutocompleteField]
class ModelAutocompleteField extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => ModelProfile::class,
            'label' => 'video.performers',
            'choice_label' => 'displayName',
            'multiple' => true,
            'placeholder' => 'Начните вводить имя модели...',
            'filter_query' => function ($qb, string $query) {
                $qb->andWhere('(entity.displayName LIKE :query OR entity.slug LIKE :query)')
                   ->andWhere('entity.isActive = :active')
                   ->setParameter('query', '%' . $query . '%')
                   ->setParameter('active', true);
            },
        ]);
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}

<?php

namespace App\Form\Autocomplete;

use App\Entity\Category;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;
use Symfony\UX\Autocomplete\Form\BaseEntityAutocompleteType;

#[AsEntityAutocompleteField]
class CategoryAutocompleteField extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => Category::class,
            'searchable_fields' => ['name'],
            'label' => 'video.upload_page.category',
            'choice_label' => 'name',
            'placeholder' => 'Выберите категорию...',
            'filter_query' => function ($qb, string $query) {
                $qb->andWhere('entity.isActive = :active')
                   ->setParameter('active', true);
            },
        ]);
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}

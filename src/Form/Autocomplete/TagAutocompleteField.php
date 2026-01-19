<?php

namespace App\Form\Autocomplete;

use App\Entity\Tag;
use App\Repository\TagRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;
use Symfony\UX\Autocomplete\Form\BaseEntityAutocompleteType;

#[AsEntityAutocompleteField]
class TagAutocompleteField extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => Tag::class,
            'searchable_fields' => ['name'],
            'label' => 'video.upload_page.tags',
            'choice_label' => 'name',
            'multiple' => true,
            'placeholder' => 'Начните вводить название тега...',
            'attr' => [
                'data-controller' => 'symfony--ux-autocomplete--autocomplete',
            ],
        ]);
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}

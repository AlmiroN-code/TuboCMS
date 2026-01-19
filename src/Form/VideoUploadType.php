<?php

namespace App\Form;

use App\Entity\Category;
use App\Entity\ModelProfile;
use App\Entity\Tag;
use App\Entity\Video;
use App\Form\Autocomplete\CategoryAutocompleteField;
use App\Form\Autocomplete\ModelAutocompleteField;
use App\Form\Autocomplete\TagAutocompleteField;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\UX\Dropzone\Form\DropzoneType;

class VideoUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'video.upload_page.video_title',
                'constraints' => [
                    new NotBlank(message: 'video.title.not_blank'),
                    new Length(max: 200, maxMessage: 'video.title.max_length'),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'video.upload_page.description',
                'required' => false,
                'attr' => ['rows' => 5],
            ])
            ->add('category', CategoryAutocompleteField::class, [
                'required' => false,
            ])
            ->add('tags', TagAutocompleteField::class, [
                'required' => false,
            ])
            ->add('performers', ModelAutocompleteField::class, [
                'required' => false,
                'label' => 'video.performers',
            ])
            ->add('videoFile', DropzoneType::class, [
                'label' => 'video.upload_page.video_file',
                'mapped' => false,
                'required' => true,
                'attr' => [
                    'placeholder' => 'Перетащите видео сюда или нажмите для выбора',
                    'data-controller' => 'symfony--ux-dropzone--dropzone',
                ],
                'constraints' => [
                    new File([
                        'maxSize' => '2048M',
                        'mimeTypes' => [
                            'video/mp4',
                            'video/mpeg',
                            'video/quicktime',
                            'video/x-msvideo',
                            'video/x-matroska',
                        ],
                        'mimeTypesMessage' => 'video.file.invalid_type',
                    ])
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Video::class,
        ]);
    }
}

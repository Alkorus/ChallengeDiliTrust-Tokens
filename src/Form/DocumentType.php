<?php

namespace App\Form;

use App\Entity\Document;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class DocumentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // ->add('nom')
            // ->add('lien')
            // ->add('MomentEnregistrement')
            // ->add('auteur')
            // ->add('proprietaires')
            ->add('doc', FileType::class, [
                'label' => 'Ajouter un document',
                'mapped' => false,
                'required' => true,
                // 'constraints' => [
                //     new File([
                //         'maxSize' => '1024k',
                //         'mimeTypes' => [
                //             'application/pdf',
                //             'application/x-pdf',
                //         ],
                //         'mimeTypesMessage' => 'Please upload a valid PDF document',
                //     ])
                // ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Document::class,
        ]);
    }
}

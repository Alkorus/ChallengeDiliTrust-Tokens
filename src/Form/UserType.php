<?php

namespace App\Form;

use App\Entity\Langue;
use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\{TextType, DatetimeType, TextareaType, SubmitType, IntegerType, FormType, ChoiceType, PasswordType, RepeatedType};
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('courriel', TextType::class, [
                'invalid_message' => 'La valeur entrée est invalide.',
                'constraints' => [
                    new NotBlank(),
                    new Regex('/^[a-zA-Z0-9_.-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+$/')
                ],
            ])
            ->add('motdepasse', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'Les deux mots de passe doivent être identiques.',
                'options' => ['attr' => ['class' => 'password-field']],
                'required' => true,
                'first_options'  => ['label' => 'Password'],
                'second_options' => ['label' => 'Repeat Password'],
                'constraints' => [
                    new NotBlank(),
                    new Regex('/(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{9,}$/')
                ],
            ])
            ->add('langue', EntityType::class, ['class' => Langue::class, 'choice_label' => 'Nom'])
            ->add('soumettre', SubmitType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}

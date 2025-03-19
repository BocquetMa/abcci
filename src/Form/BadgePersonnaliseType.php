<?php

namespace App\Form;

use App\Entity\Utilisateur;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;

class BadgePersonnaliseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('utilisateur', EntityType::class, [
                'class' => Utilisateur::class,
                'choice_label' => function (Utilisateur $utilisateur) {
                    return sprintf('%s %s (%s)', 
                        $utilisateur->getPrenom(), 
                        $utilisateur->getNom(), 
                        $utilisateur->getEmail()
                    );
                },
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez sélectionner un utilisateur'])
                ]
            ])
            ->add('nom', TextType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'Le nom du badge est obligatoire']),
                    new Length([
                        'min' => 3, 
                        'max' => 100, 
                        'minMessage' => 'Le nom du badge doit faire au moins 3 caractères',
                        'maxMessage' => 'Le nom du badge ne peut pas dépasser 100 caractères'
                    ])
                ]
            ])
            ->add('description', TextareaType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'Une description est obligatoire']),
                    new Length([
                        'min' => 10, 
                        'max' => 500, 
                        'minMessage' => 'La description doit faire au moins 10 caractères',
                        'maxMessage' => 'La description ne peut pas dépasser 500 caractères'
                    ])
                ]
            ])
            ->add('points', IntegerType::class, [
                'required' => false,
                'empty_data' => 0,
                'constraints' => [
                    new Length([
                        'min' => 0,
                        'max' => 500,
                        'minMessage' => 'Les points doivent être positifs',
                        'maxMessage' => 'Les points ne peuvent pas dépasser 500'
                    ])
                ]
            ])
            ->add('image', FileType::class, [
                'required' => false,
                'constraints' => [
                    new Image([
                        'maxSize' => '5M',
                        'mimeTypes' => ['image/png', 'image/jpeg', 'image/gif'],
                        'mimeTypesMessage' => 'Veuillez télécharger une image valide (PNG, JPEG, GIF)'
                    ])
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_protection' => true,
            'allow_extra_fields' => false
        ]);
    }
}
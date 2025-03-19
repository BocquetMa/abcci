<?php

namespace App\Form;

use App\Entity\Quiz;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class QuizType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre du quiz',
                'attr' => [
                    'placeholder' => 'Exemple: Quiz final du module JavaScript',
                    'class' => 'form-control'
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Description du quiz et instructions pour les apprenants',
                    'rows' => 5,
                    'class' => 'form-control'
                ]
            ])
            ->add('tempsLimite', IntegerType::class, [
                'label' => 'Temps limite (en minutes)',
                'attr' => [
                    'min' => 1,
                    'max' => 180,
                    'class' => 'form-control'
                ],
                'help' => 'Durée maximale allouée pour compléter le quiz (1-180 minutes)'
            ])
            ->add('scoreReussite', IntegerType::class, [
                'label' => 'Score de réussite (%)',
                'attr' => [
                    'min' => 0,
                    'max' => 100,
                    'class' => 'form-control'
                ],
                'help' => 'Pourcentage minimum requis pour réussir le quiz'
            ])
            ->add('actif', CheckboxType::class, [
                'label' => 'Quiz actif',
                'required' => false,
                'help' => 'Cochez pour rendre le quiz disponible aux apprenants',
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'label_attr' => [
                    'class' => 'form-check-label'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Quiz::class,
        ]);
    }
}
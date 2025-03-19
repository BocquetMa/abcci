<?php

namespace App\Form;

use App\Entity\MotCle;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FormationSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('theme', ChoiceType::class, [
                'choices' => [
                    'Tous les thèmes' => '',
                    'Développement Web' => 'dev_web',
                    'Base de données' => 'bdd',
                    'DevOps' => 'devops',
                    'Sécurité' => 'securite',
                    'Bureautique' => 'bureautique',
                ],
                'required' => false,
                'label' => 'Thème'
            ])
            ->add('niveau', ChoiceType::class, [
                'choices' => [
                    'Tous les niveaux' => '',
                    'Débutant' => 'debutant',
                    'Intermédiaire' => 'intermediaire',
                    'Avancé' => 'avance'
                ],
                'required' => false,
                'label' => 'Niveau'
            ])
            ->add('dureeMin', NumberType::class, [
                'required' => false,
                'label' => 'Durée minimum (heures)',
                'attr' => [
                    'min' => 0,
                    'placeholder' => 'Min'
                ]
            ])
            ->add('dureeMax', NumberType::class, [
                'required' => false,
                'label' => 'Durée maximum (heures)',
                'attr' => [
                    'min' => 0,
                    'placeholder' => 'Max'
                ]
            ])
            ->add('prixMin', NumberType::class, [
                'required' => false,
                'mapped' => false,
                'label' => 'Prix minimum (€)',
                'attr' => [
                    'min' => 0,
                    'placeholder' => 'Min'
                ]
            ])
            ->add('prixMax', NumberType::class, [
                'required' => false,
                'mapped' => false,
                'label' => 'Prix maximum (€)',
                'attr' => [
                    'min' => 0,
                    'placeholder' => 'Max'
                ]
            ])
            ->add('motsCles', EntityType::class, [
                'class' => MotCle::class,
                'choice_label' => 'libelle',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'label' => 'Mots-clés'
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'method' => 'GET',
            'csrf_protection' => false,
        ]);
    }

    public function getBlockPrefix()
    {
        return '';
    }
}
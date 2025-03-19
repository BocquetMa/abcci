<?php

namespace App\Form;

use App\Entity\Formateur;
use App\Entity\Formation;
use App\Entity\MotCle;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FormationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
{
    $builder
        ->add('titre')
        ->add('description')
        ->add('theme', ChoiceType::class, [
            'choices' => [
                'Développement Web' => 'dev_web',
                'Base de données' => 'bdd',
                'DevOps' => 'devops',
                'Sécurité' => 'securite',
            ]
        ])
        ->add('niveau', ChoiceType::class, [
            'choices' => [
                'Débutant' => 'debutant',
                'Intermédiaire' => 'intermediaire',
                'Avancé' => 'avance'
            ]
        ])
        ->add('duree')
        ->add('prix')
        ->add('dateDebut')
        ->add('dateFin')
        ->add('formateur', EntityType::class, [
            'class' => Formateur::class,
            'choice_label' => 'nom'
        ])
        ->add('motsCles', EntityType::class, [
            'class' => MotCle::class,
            'choice_label' => 'libelle',
            'multiple' => true,
            'expanded' => true,
            'label' => 'Mots-clés',
            'required' => false,
        ]);
}

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Formation::class,
        ]);
    }
}

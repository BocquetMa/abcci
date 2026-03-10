<?php

namespace App\Form;

use App\Entity\Formateur;
use App\Entity\Formation;
use App\Entity\MotCle;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
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
                    'Data Science / IA' => 'data_science',
                    'Cybersécurité' => 'cybersecurite',
                    'DevOps' => 'devops',
                    'Base de données' => 'bdd',
                    'Management / Agile' => 'management',
                    'Bureautique' => 'bureautique',
                    'Autre' => 'autre',
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
            ->add('prix', NumberType::class, [
                'scale' => 2,
                'html5' => true,
                'attr' => ['step' => '0.01', 'min' => '0'],
            ])
            ->add('dateDebut')
            ->add('dateFin')
            ->add('nombrePlacesTotal')
            ->add('motsCles', EntityType::class, [
                'class' => MotCle::class,
                'choice_label' => 'libelle',
                'multiple' => true,
                'expanded' => true,
                'label' => 'Mots-clés',
                'required' => false,
            ]);

        // Le champ formateur n'est visible que pour les admins
        if ($options['show_formateur']) {
            $builder->add('formateur', EntityType::class, [
                'class' => Formateur::class,
                'choice_label' => fn(Formateur $f) => $f->getPrenom() . ' ' . $f->getNom(),
                'label' => 'Formateur',
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Formation::class,
            'show_formateur' => false,
        ]);
    }
}

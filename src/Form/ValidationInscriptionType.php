<?php
// src/Form/ValidationInscriptionType.php
namespace App\Form;

use App\Entity\Inscription;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class ValidationInscriptionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
        ->add('action', ChoiceType::class, [
            'label' => 'Action',
            'choices' => [
                'Accepter' => 'accepter',
                'Refuser' => 'refuser'
            ],
            'expanded' => true,     // Important : ceci crée des boutons radio
            'multiple' => false,    // Important : ceci permet de sélectionner une seule option
            'mapped' => false,
            'data' => 'accepter'    // Valeur par défaut
        ])
        ->add('dateDebut', DateTimeType::class, [
            'label' => 'Date de début',
            'widget' => 'single_text',
            'required' => false,
            'mapped' => false,
        ])
        ->add('dateFin', DateTimeType::class, [
            'label' => 'Date de fin',
            'widget' => 'single_text',
            'required' => false,
            'mapped' => false,
        ])
            ->add('motif', TextareaType::class, [
                'label' => 'Motif du refus',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Expliquez pourquoi cette demande est refusée',
                    'rows' => 3
                ]
            ]);
        
        // Modifier le formulaire en fonction de l'action choisie
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            $form = $event->getForm();
            
            if (isset($data['action']) && $data['action'] === 'refuser') {
                // Si on refuse, le motif devient obligatoire
                $form->add('motif', TextareaType::class, [
                    'label' => 'Motif du refus',
                    'required' => true,
                    'attr' => [
                        'placeholder' => 'Expliquez pourquoi cette demande est refusée',
                        'rows' => 3
                    ]
                ]);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Inscription::class,
        ]);
    }
}
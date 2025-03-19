<?php
namespace App\Form;

use App\Entity\Utilisateur;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class NotificationCreationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('titre', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => 'Titre de la notification'
            ])
            ->add('contenu', TextareaType::class, [
                'constraints' => [new NotBlank()],
                'label' => 'Contenu de la notification'
            ])
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'Formation' => 'formation',
                    'Inscription' => 'inscription',
                    'Paiement' => 'paiement',
                    'Général' => 'general'
                ],
                'label' => 'Type de notification'
            ])
            ->add('destinataires', ChoiceType::class, [
                'choices' => [
                    'Tous les utilisateurs' => 'tous',
                    'Utilisateurs' => 'utilisateurs',
                    'Formateurs' => 'formateurs',
                    'Administrateurs' => 'admins'
                ],
                'multiple' => false,
                'expanded' => true,
                'label' => 'Destinataires'
            ])
            ->add('utilisateursSpecifiques', EntityType::class, [
                'class' => Utilisateur::class,
                'choice_label' => function (Utilisateur $utilisateur) {
                    return sprintf('%s %s (%s)', 
                        $utilisateur->getPrenom(), 
                        $utilisateur->getNom(), 
                        $utilisateur->getEmail()
                    );
                },
                'multiple' => true,
                'required' => false,
                'label' => 'Sélectionner des utilisateurs spécifiques'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => null
        ]);
    }
}
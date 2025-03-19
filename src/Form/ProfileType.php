<?php

namespace App\Form;

use App\Entity\Utilisateur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('photoFile', FileType::class, [
                'label' => 'Photo de profil',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '1024k',
                        'mimeTypes' => ['image/jpeg', 'image/png'],
                        'mimeTypesMessage' => 'Veuillez uploader une image valide (JPG ou PNG)',
                    ])
                ],
            ])
            ->add('cvFile', FileType::class, [
                'label' => 'CV (PDF)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '2048k',
                        'mimeTypes' => ['application/pdf'],
                        'mimeTypesMessage' => 'Veuillez uploader un fichier PDF',
                    ])
                ],
            ])
            ->add('biographieDetaillee', TextareaType::class, [
                'label' => 'Biographie détaillée',
                'required' => false,
                'attr' => ['rows' => 5],
            ])
            ->add('socialLinks', CollectionType::class, [
                'entry_type' => SocialLinkType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'label' => 'Liens sociaux'
            ])
            ->add('preferences', ChoiceType::class, [
                'label' => 'Préférences',
                'choices' => [
                    'Recevoir des notifications par email' => 'email_notifications',
                    'Profil public' => 'public_profile',
                    'Afficher les badges' => 'show_badges',
                ],
                'multiple' => true,
                'expanded' => true,
                'required' => false,
            ]);

        if ($options['is_formateur']) {
            $builder->add('domainesExpertise', ChoiceType::class, [
                'label' => 'Domaines d\'expertise',
                'choices' => [
                    'Développement Web' => 'dev_web',
                    'Base de données' => 'bdd',
                    'DevOps' => 'devops',
                    'Design' => 'design',
                    'Marketing Digital' => 'marketing',
                ],
                'multiple' => true,
                'expanded' => true,
                'required' => false,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Utilisateur::class,
            'is_formateur' => false,
        ]);
    }

}
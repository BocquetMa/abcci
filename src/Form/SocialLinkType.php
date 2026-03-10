<?php

namespace App\Form;

use App\Entity\SocialLink;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SocialLinkType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('platform', ChoiceType::class, [
                'label' => 'Plateforme',
                'choices' => [
                    'LinkedIn'  => 'linkedin',
                    'Twitter'   => 'twitter',
                    'GitHub'    => 'github',
                    'Facebook'  => 'facebook',
                    'Instagram' => 'instagram',
                    'Site web'  => 'website',
                ],
                'required' => false,
            ])
            ->add('url', UrlType::class, [
                'label' => 'URL',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SocialLink::class,
        ]);
    }
}

<?php

namespace App\Form;

use App\Entity\Question;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class QuestionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('enonce', TextareaType::class, [
                'label' => 'Énoncé de la question',
                'attr' => [
                    'placeholder' => 'Saisissez votre question ici...',
                    'rows' => 3,
                    'class' => 'form-control'
                ]
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type de question',
                'choices' => [
                    'Choix unique' => Question::TYPE_CHOIX_UNIQUE,
                    'Choix multiple' => Question::TYPE_CHOIX_MULTIPLE,
                    'Vrai ou Faux' => Question::TYPE_VRAI_FAUX,
                    'Réponse textuelle' => Question::TYPE_TEXTE,
                ],
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('points', IntegerType::class, [
                'label' => 'Points',
                'attr' => [
                    'min' => 1,
                    'class' => 'form-control'
                ],
                'help' => 'Nombre de points attribués pour cette question'
            ])
            ->add('explication', TextareaType::class, [
                'label' => 'Explication',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Explication de la réponse correcte (visible après avoir répondu)',
                    'rows' => 3,
                    'class' => 'form-control'
                ]
            ])
            ->add('reponses', CollectionType::class, [
                'entry_type' => ReponseType::class,
                'entry_options' => [
                    'label' => false
                ],
                'allow_add' => false,
                'allow_delete' => false,
                'by_reference' => false,
                'label' => false
            ])
        ;
        
        // Ajouter dynamiquement le champ reponseTexte pour les questions de type texte
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $question = $event->getData();
            $form = $event->getForm();
            
            if ($question && $question->getType() === Question::TYPE_TEXTE) {
                $form->add('reponseTexte', TextType::class, [
                    'label' => 'Réponse correcte',
                    'required' => true,
                    'attr' => [
                        'placeholder' => 'Saisissez la réponse attendue',
                        'class' => 'form-control'
                    ],
                    'help' => 'Cette réponse sera utilisée pour vérifier si la réponse de l\'apprenant est correcte'
                ]);
            }
        });
        
        // Mettre à jour le formulaire quand le type change
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            $form = $event->getForm();
            
            if (isset($data['type']) && $data['type'] === Question::TYPE_TEXTE) {
                $form->add('reponseTexte', TextType::class, [
                    'label' => 'Réponse correcte',
                    'required' => true,
                    'attr' => [
                        'placeholder' => 'Saisissez la réponse attendue',
                        'class' => 'form-control'
                    ],
                    'help' => 'Cette réponse sera utilisée pour vérifier si la réponse de l\'apprenant est correcte'
                ]);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Question::class,
        ]);
    }
}
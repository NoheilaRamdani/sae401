<?php

namespace App\Form;

use App\Entity\Assignment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SuggestionFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Assignment $assignment */
        $assignment = $options['assignment'];

        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre du devoir',
                'data' => $assignment->getTitle(),
                'required' => true,
                'attr' => ['placeholder' => 'Ex. : Projet Symfony'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'data' => $assignment->getDescription(),
                'required' => false,
                'attr' => ['placeholder' => 'Détails du devoir...', 'rows' => 5],
            ])
            ->add('due_date', DateTimeType::class, [
                'label' => 'Date limite',
                'data' => $assignment->getDueDate(),
                'widget' => 'single_text',
                'attr' => ['class' => 'datetime-picker'],
                'required' => true,
            ])
            ->add('submission_type', ChoiceType::class, [
                'label' => 'Mode de rendu',
                'choices' => [
                    'Par mail' => 'email',
                    'Moodle' => 'moodle',
                    'VPS' => 'vps',
                ],
                'data' => $assignment->getSubmissionType(),
                'placeholder' => 'Choisir un mode de rendu',
                'required' => true,
            ])
            ->add('submission_url', TextType::class, [
                'label' => 'URL de soumission (optionnel)',
                'data' => $assignment->getSubmissionUrl(),
                'required' => false,
                'attr' => ['placeholder' => 'Ex. : https://vps.example.com'],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'choices' => [
                    'Devoir' => 'devoir',
                    'Examen' => 'examen',
                    'Oral' => 'oral',
                ],
                'data' => $assignment->getType(),
                'placeholder' => 'Choisir un type',
                'required' => true,
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Votre suggestion de modification',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Expliquez pourquoi vous suggérez ces changements...',
                    'rows' => 5,
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Envoyer la suggestion',
                'attr' => ['class' => 'btn btn-primary'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null, // Pas de liaison directe à une entité
            'assignment' => null,
        ]);
        $resolver->setRequired('assignment');
    }
}
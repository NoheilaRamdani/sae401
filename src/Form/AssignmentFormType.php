<?php

namespace App\Form;

use App\Entity\Assignment;
use App\Entity\Subject;
use App\Entity\Group;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AssignmentFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre du devoir',
                'attr' => ['placeholder' => 'Ex. : Projet Symfony'],
                'required' => true,
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['placeholder' => 'Détails du devoir...'],
            ])
            ->add('due_date', DateTimeType::class, [
                'label' => 'Date limite',
                'widget' => 'single_text',
                'attr' => ['class' => 'datetime-picker'],
                'required' => true,
            ])
            ->add('subject', EntityType::class, [
                'class' => Subject::class,
                'choice_label' => function (Subject $subject) {
                    return sprintf('%s - %s', $subject->getCode(), $subject->getName());
                },
                'label' => 'Matière',
                'placeholder' => 'Choisir une matière',
                'required' => true,
            ])
            ->add('groups', EntityType::class, [
                'class' => Group::class,
                'choice_label' => 'name',
                'label' => 'Groupe(s) concerné(s)',
                'multiple' => true,
                'expanded' => true,
                'required' => true,
            ])
            ->add('submission_type', ChoiceType::class, [
                'label' => 'Mode de rendu',
                'choices' => [
                    'Par mail' => 'email',
                    'Moodle' => 'moodle',
                    'VPS' => 'vps',
                    'Autre' => 'Autre',
                ],
                'placeholder' => 'Choisir un mode de rendu',
                'required' => true,
            ])
            ->add('submission_other', TextType::class, [
                'label' => 'Préciser le mode de rendu',
                'required' => false,
                'attr' => ['placeholder' => 'Ex. : Lien Figma, Discord...'],
            ])
            ->add('submission_url', TextType::class, [
                'label' => 'URL de rendu (optionnel)',
                'required' => false,
                'attr' => ['placeholder' => 'Ex. : https://vps.example.com'],
            ])
            ->add('course_location', TextType::class, [
                'label' => 'Où trouver le cours (optionnel)',
                'required' => false,
                'attr' => ['placeholder' => 'Ex. : Moodle, docs.mmi-troyes.fr...'],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'choices' => [
                    'Devoir' => 'devoir',
                    'Examen' => 'examen',
                    'Oral' => 'oral',
                ],
                'placeholder' => 'Choisir un type',
                'required' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Assignment::class,
        ]);
    }
}
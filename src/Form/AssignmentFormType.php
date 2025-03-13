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
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['placeholder' => 'Détails du devoir...'],
            ])
            ->add('dueDate', DateTimeType::class, [
                'label' => 'Date limite',
                'widget' => 'single_text',
                'attr' => ['class' => 'datetime-picker'],
            ])
            ->add('subject', EntityType::class, [
                'class' => Subject::class,
                'choice_label' => 'name',
                'label' => 'Matière',
                'placeholder' => 'Choisir une matière',
                'required' => true,
            ])
            ->add('groups', EntityType::class, [ // Changement ici : 'groups' au lieu de 'group'
                'class' => Group::class,
                'choice_label' => 'name',
                'label' => 'Groupe(s) concerné(s)',
                'multiple' => true,
                'expanded' => true,
                'required' => true,
            ])
            ->add('submissionType', ChoiceType::class, [
                'label' => 'Mode de rendu',
                'choices' => [
                    'Par mail' => 'email',
                    'Moodle' => 'moodle',
                    'VPS' => 'vps',
                ],
                'placeholder' => 'Choisir un mode de rendu',
                'required' => true,
            ])
            ->add('submissionUrl', TextType::class, [
                'label' => 'URL de soumission (optionnel)',
                'required' => false,
                'attr' => ['placeholder' => 'Ex. : https://vps.example.com'],
            ])
            ->add('type', ChoiceType::class, [ // Ajout du champ type
                'label' => 'Type',
                'choices' => [
                    'Devoir' => 'devoir',
                    'Examen' => 'examen',
                    'Oral' => 'oral',
                ],
                'placeholder' => 'Choisir un type',
                'required' => true,
            ])
            ->add('dueDates', TextareaType::class, [
                'label' => 'Dates limites spécifiques (JSON)',
                'required' => false,
                'mapped' => false,
                'attr' => ['placeholder' => '{"TP A": "2025-04-04 23:59:00", "TD AB": "2025-04-05 23:59:00"}'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Assignment::class,
        ]);
    }
}
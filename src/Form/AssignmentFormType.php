<?php

namespace App\Form;

use App\Entity\Assignment;
use App\Entity\Subject;
use App\Entity\Group;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

class AssignmentFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'required' => true,
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
            ])
            ->add('dueDate', DateTimeType::class, [
                'label' => 'Date limite',
                'widget' => 'single_text',
                'required' => true,
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type de rendu',
                'choices' => [
                    'Devoir' => 'devoir',
                    'Examen' => 'examen',
                    'Oral' => 'oral',
                ],
                'required' => true,
            ])
            ->add('subject', EntityType::class, [
                'label' => 'Matière',
                'class' => Subject::class,
                'choice_label' => 'name',
                'required' => true,
            ])
            ->add('group', EntityType::class, [
                'label' => 'Groupe',
                'class' => Group::class,
                'choice_label' => 'name',
                'required' => true,
            ])
            ->add('submissionType', TextType::class, [
                'label' => 'Mode de soumission (ex: Moodle, email)',
                'required' => false,
            ])
            ->add('submissionUrl', TextType::class, [
                'label' => 'URL de soumission',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Assignment::class,
        ]);
    }
}
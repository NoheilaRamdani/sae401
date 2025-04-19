<?php
namespace App\Form;

use App\Entity\Assignment;
use App\Entity\Subject;
use App\Entity\Group;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

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
            ->add('due_date', DateTimeType::class, [
                'label' => 'Date limite',
                'widget' => 'single_text',
                'html5' => true,
                'required' => true,
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'choices' => [
                    'Examen' => 'examen',
                    'Oral' => 'oral',
                    'Devoir' => 'devoir',
                ],
                'required' => true,
            ])
            ->add('submission_url', UrlType::class, [
                'label' => 'URL de rendu',
                'required' => false,
            ])
            ->add('submission_other', TextType::class, [
                'label' => 'Autres instructions de rendu',
                'required' => false,
            ])
            ->add('course_location', TextType::class, [
                'label' => 'Lieu du cours',
                'required' => false,
            ])
            ->add('subject', EntityType::class, [
                'label' => 'Matière',
                'class' => Subject::class,
                'choice_label' => 'name',
                'required' => true,
            ])
            ->add('groups', EntityType::class, [
                'label' => 'Groupes',
                'class' => Group::class,
                'choice_label' => 'name',
                'multiple' => true,
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
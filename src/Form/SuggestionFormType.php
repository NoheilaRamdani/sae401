<?php
namespace App\Form;

use App\Entity\Assignment;
use App\Entity\Subject;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
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
                'label' => 'Titre',
                'data' => $assignment->getTitle() ?? '',
                'required' => true,
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'data' => $assignment->getDescription(),
                'required' => false,
            ])
            ->add('due_date', DateTimeType::class, [
                'label' => 'Date limite',
                'data' => $assignment->getDueDate(),
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
                'data' => $assignment->getType() ?? 'devoir',
                'required' => true,
            ])
            ->add('submission_url', UrlType::class, [
                'label' => 'URL de rendu',
                'data' => $assignment->getSubmissionUrl(),
                'required' => false,
            ])
            ->add('submission_other', TextType::class, [
                'label' => 'Autres instructions de rendu',
                'data' => $assignment->getSubmissionOther(),
                'required' => false,
            ])
            ->add('course_location', TextType::class, [
                'label' => 'Lieu du cours',
                'data' => $assignment->getCourseLocation(),
                'required' => false,
            ])
            ->add('subject', EntityType::class, [
                'label' => 'Matière',
                'class' => Subject::class,
                'choice_label' => 'name',
                'data' => $assignment->getSubject(),
                'required' => true,
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Message (expliquez votre suggestion)',
                'required' => true,
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Soumettre',
                'attr' => ['class' => 'btn btn-primary'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('assignment');
        $resolver->setAllowedTypes('assignment', Assignment::class);
    }
}
<?php
namespace App\Form;

use App\Entity\Assignment;
use App\Entity\Subject;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SuggestionFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $assignment = $options['assignment'];

        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'required' => false,
                'data' => $assignment->getTitle(),
            ])
            ->add('description', TextType::class, [
                'label' => 'Description',
                'required' => false,
                'data' => $assignment->getDescription(),
            ])
            ->add('due_date', DateTimeType::class, [
                'label' => 'Date limite',
                'widget' => 'single_text',
                'required' => false,
                'data' => $assignment->getDueDate(),
            ])
            ->add('submission_type', ChoiceType::class, [
                'label' => 'Type de rendu',
                'choices' => [
                    'Lien' => 'Lien',
                    'Fichier' => 'Fichier',
                    'Texte' => 'Texte',
                    'Autre' => 'Autre',
                ],
                'required' => false,
                'data' => $assignment->getSubmissionType(),
            ])
            ->add('submission_url', UrlType::class, [
                'label' => 'URL de rendu',
                'required' => false,
                'data' => $assignment->getSubmissionUrl(),
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'choices' => [
                    'Devoir' => 'Devoir',
                    'Examen' => 'Examen',
                    'Oral' => 'Oral',
                ],
                'required' => false,
                'data' => $assignment->getType(),
            ])
            ->add('subject', EntityType::class, [
                'class' => Subject::class,
                'choice_label' => 'name',
                'label' => 'Matière',
                'required' => false,
                'data' => $assignment->getSubject(),
                'placeholder' => 'Sélectionner une matière',
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Message (facultatif)',
                'required' => false,
                'attr' => [
                    'rows' => 4,  // Nombre de lignes du textarea
                    'placeholder' => 'Écrivez votre message ici...' // Optionnel : placeholder
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Envoyer la suggestion',
                'attr' => [
                    'class' => 'button',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('assignment');
        $resolver->setAllowedTypes('assignment', Assignment::class);
    }
}
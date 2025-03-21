<?php
namespace App\Form;

use App\Entity\Suggestion;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SuggestionReviewType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('approve', SubmitType::class, [
                'label' => 'Valider les modifications',
                'attr' => ['class' => 'btn btn-success'],
            ])
            ->add('reject', SubmitType::class, [
                'label' => 'Rejeter la suggestion',
                'attr' => ['class' => 'btn btn-danger'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Suggestion::class,
        ]);
    }
}
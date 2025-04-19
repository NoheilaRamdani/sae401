<?php
namespace App\Controller;

use App\Entity\Suggestion;
use App\Entity\Assignment;
use App\Entity\Subject;
use App\Form\SuggestionFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class SuggestionController extends AbstractController
{
    #[Route('/assignment/{id}/suggest', name: 'suggest_assignment', methods: ['GET', 'POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function suggestAssignment(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $assignment = $entityManager->getRepository(Assignment::class)->find($id);
        if (!$assignment) {
            throw $this->createNotFoundException('Devoir non trouvé.');
        }

        $user = $this->getUser();
        $groups = $user->getGroups();
        $hasAccess = false;
        foreach ($assignment->getGroups() as $group) {
            if ($groups->contains($group)) {
                $hasAccess = true;
                break;
            }
        }
        if (!$hasAccess && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à ce devoir.');
        }

        $form = $this->createForm(SuggestionFormType::class, null, ['assignment' => $assignment]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            // Construire les changements proposés et les valeurs originales
            $proposedChanges = [];
            $originalValues = [];

            // Normalisation pour éviter les problèmes avec null ou chaînes vides
            $normalize = function ($value) {
                return $value === null ? '' : $value;
            };

            // Titre
            $submittedTitle = $normalize($data['title']);
            $currentTitle = $normalize($assignment->getTitle());
            if ($submittedTitle !== $currentTitle) {
                $proposedChanges['title'] = $data['title'];
                $originalValues['title'] = $currentTitle;
            }

            // Description
            $submittedDescription = $normalize($data['description']);
            $currentDescription = $normalize($assignment->getDescription());
            if ($submittedDescription !== $currentDescription) {
                $proposedChanges['description'] = $data['description'];
                $originalValues['description'] = $currentDescription;
            }

            // Date limite
            $submittedDueDate = $data['due_date'];
            $currentDueDate = $assignment->getDueDate();
            if ($submittedDueDate != $currentDueDate) {
                $proposedChanges['due_date'] = $submittedDueDate ? $submittedDueDate->format('Y-m-d H:i:s') : null;
                $originalValues['due_date'] = $currentDueDate ? $currentDueDate->format('Y-m-d H:i:s') : null;
            }

            // Type
            $submittedType = $normalize($data['type']);
            $currentType = $normalize($assignment->getType());
            if ($submittedType !== $currentType) {
                $proposedChanges['type'] = $data['type'];
                $originalValues['type'] = $currentType;
            }

            // URL de rendu
            $submittedSubmissionUrl = $normalize($data['submission_url']);
            $currentSubmissionUrl = $normalize($assignment->getSubmissionUrl());
            if ($submittedSubmissionUrl !== $currentSubmissionUrl) {
                $proposedChanges['submission_url'] = $data['submission_url'];
                $originalValues['submission_url'] = $currentSubmissionUrl;
            }

            // Autres instructions de rendu
            $submittedSubmissionOther = $normalize($data['submission_other']);
            $currentSubmissionOther = $normalize($assignment->getSubmissionOther());
            if ($submittedSubmissionOther !== $currentSubmissionOther) {
                $proposedChanges['submission_other'] = $data['submission_other'];
                $originalValues['submission_other'] = $currentSubmissionOther;
            }

            // Lieu du cours
            $submittedCourseLocation = $normalize($data['course_location']);
            $currentCourseLocation = $normalize($assignment->getCourseLocation());
            if ($submittedCourseLocation !== $currentCourseLocation) {
                $proposedChanges['course_location'] = $data['course_location'];
                $originalValues['course_location'] = $currentCourseLocation;
            }

            // Matière
            $submittedSubjectId = $data['subject'] ? $data['subject']->getId() : null;
            $currentSubjectId = $assignment->getSubject() ? $assignment->getSubject()->getId() : null;
            if ($submittedSubjectId !== $currentSubjectId) {
                $proposedChanges['subject_id'] = $submittedSubjectId;
                $originalValues['subject_id'] = $currentSubjectId;
            }

            if (empty($proposedChanges)) {
                $this->addFlash('error', 'Aucune modification détectée. Veuillez modifier au moins un champ pour soumettre une suggestion.');
                return $this->render('suggestion/suggest.html.twig', [
                    'form' => $form->createView(),
                    'assignment' => $assignment,
                ]);
            }

            $suggestion = new Suggestion();
            $suggestion->setAssignment($assignment);
            $suggestion->setSuggestedBy($user);
            $suggestion->setMessage($normalize($data['message']));
            $suggestion->setProposedChanges($proposedChanges);
            $suggestion->setOriginalValues($originalValues);
            $suggestion->setIsProcessed(false);
            $suggestion->setCreatedAt(new \DateTime());

            $entityManager->persist($suggestion);
            $entityManager->flush();

            $this->addFlash('success', 'Votre suggestion a été envoyée !');
            return $this->redirectToRoute('app_home');
        }

        return $this->render('suggestion/suggest.html.twig', [
            'form' => $form->createView(),
            'assignment' => $assignment,
        ]);
    }

    #[Route('/suggestion/{id}/validate', name: 'validate_suggestion', methods: ['POST'])]
    #[IsGranted('ROLE_DELEGATE')]
    public function validateSuggestion(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $suggestion = $entityManager->getRepository(Suggestion::class)->find($id);
        if (!$suggestion) {
            throw $this->createNotFoundException('Suggestion non trouvée.');
        }

        $user = $this->getUser();
        $assignment = $suggestion->getAssignment();
        $groups = $user->getGroups();
        $hasAccess = false;
        foreach ($assignment->getGroups() as $group) {
            if ($groups->contains($group)) {
                $hasAccess = true;
                break;
            }
        }
        if (!$hasAccess && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette suggestion.');
        }

        if ($this->isCsrfTokenValid('validate'.$id, $request->request->get('_token'))) {
            $changes = $suggestion->getProposedChanges();
            foreach ($changes as $field => $value) {
                switch ($field) {
                    case 'title':
                        $assignment->setTitle($value);
                        break;
                    case 'description':
                        $assignment->setDescription($value);
                        break;
                    case 'due_date':
                        $assignment->setDueDate($value ? new \DateTime($value) : null);
                        break;
                    case 'type':
                        $assignment->setType($value);
                        break;
                    case 'submission_url':
                        $assignment->setSubmissionUrl($value);
                        break;
                    case 'submission_other':
                        $assignment->setSubmissionOther($value);
                        break;
                    case 'course_location':
                        $assignment->setCourseLocation($value);
                        break;
                    case 'subject_id':
                        $subject = $entityManager->getRepository(Subject::class)->find($value);
                        if ($subject) {
                            $assignment->setSubject($subject);
                        }
                        break;
                }
            }
            $assignment->setUpdatedAt(new \DateTime());
            $suggestion->setIsProcessed(true);
            $entityManager->flush();
            $this->addFlash('success', 'La suggestion a été validée et le devoir mis à jour !');
        } else {
            $this->addFlash('error', 'Erreur de sécurité lors de la validation.');
        }

        return $this->redirectToRoute('app_suggestions');
    }

    #[Route('/suggestions', name: 'app_suggestions', methods: ['GET'])]
    #[IsGranted('ROLE_DELEGATE')]
    public function suggestions(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $queryBuilder = $entityManager->getRepository(Suggestion::class)
            ->createQueryBuilder('s')
            ->leftJoin('s.assignment', 'a')
            ->leftJoin('a.groups', 'g');

        if (!$this->isGranted('ROLE_ADMIN')) {
            $userGroups = $user->getGroups();
            $queryBuilder->andWhere('g IN (:userGroups)')
                ->setParameter('userGroups', $userGroups);
        }

        $suggestions = $queryBuilder->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        // Préparer les données pour le template
        $suggestionsData = [];
        foreach ($suggestions as $suggestion) {
            $proposedChanges = $suggestion->getProposedChanges();
            $proposedSubject = null;

            // Si une matière est proposée, la charger
            if (isset($proposedChanges['subject_id'])) {
                $proposedSubject = $entityManager->getRepository(Subject::class)->find($proposedChanges['subject_id']);
            }

            $suggestionsData[] = [
                'suggestion' => $suggestion,
                'proposedSubject' => $proposedSubject,
            ];
        }

        return $this->render('suggestion/history.html.twig', [
            'suggestionsData' => $suggestionsData,
        ]);
    }

    #[Route('/suggestion/{id}/review', name: 'review_suggestion', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_DELEGATE')]
    public function reviewSuggestion(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $suggestion = $entityManager->getRepository(Suggestion::class)->find($id);
        if (!$suggestion) {
            throw $this->createNotFoundException('Suggestion non trouvée.');
        }

        $assignment = $suggestion->getAssignment();
        $user = $this->getUser();
        $groups = $user->getGroups();
        $hasAccess = false;
        foreach ($assignment->getGroups() as $group) {
            if ($groups->contains($group)) {
                $hasAccess = true;
                break;
            }
        }
        if (!$hasAccess && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette suggestion.');
        }

        // Charger la matière proposée, si elle existe
        $proposedChanges = $suggestion->getProposedChanges();
        $proposedSubject = null;
        if (isset($proposedChanges['subject_id'])) {
            $proposedSubject = $entityManager->getRepository(Subject::class)->find($proposedChanges['subject_id']);
        }

        $form = $this->createFormBuilder()
            ->add('approve', \Symfony\Component\Form\Extension\Core\Type\SubmitType::class, [
                'label' => 'Valider',
                'attr' => ['class' => 'btn btn-success'],
            ])
            ->add('reject', \Symfony\Component\Form\Extension\Core\Type\SubmitType::class, [
                'label' => 'Rejeter',
                'attr' => ['class' => 'btn btn-danger'],
            ])
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->get('approve')->isClicked()) {
                $changes = $suggestion->getProposedChanges();
                foreach ($changes as $field => $value) {
                    switch ($field) {
                        case 'title':
                            $assignment->setTitle($value);
                            break;
                        case 'description':
                            $assignment->setDescription($value);
                            break;
                        case 'due_date':
                            $assignment->setDueDate($value ? new \DateTime($value) : null);
                            break;
                        case 'type':
                            $assignment->setType($value);
                            break;
                        case 'submission_url':
                            $assignment->setSubmissionUrl($value);
                            break;
                        case 'submission_other':
                            $assignment->setSubmissionOther($value);
                            break;
                        case 'course_location':
                            $assignment->setCourseLocation($value);
                            break;
                        case 'subject_id':
                            $subject = $entityManager->getRepository(Subject::class)->find($value);
                            if ($subject) {
                                $assignment->setSubject($subject);
                            }
                            break;
                    }
                }
                $assignment->setUpdatedAt(new \DateTime());
                $suggestion->setIsProcessed(true);
                $entityManager->flush();
                $this->addFlash('success', 'Suggestion validée et devoir mis à jour.');
                return $this->redirectToRoute('app_suggestions');
            } elseif ($form->get('reject')->isClicked()) {
                $suggestion->setIsProcessed(true);
                $entityManager->flush();
                $this->addFlash('info', 'Suggestion rejetée.');
                return $this->redirectToRoute('app_suggestions');
            }
        }

        return $this->render('suggestion/review.html.twig', [
            'suggestion' => $suggestion,
            'assignment' => $assignment,
            'form' => $form->createView(),
            'proposedSubject' => $proposedSubject,
        ]);
    }

    #[Route('/api/suggestions/{id}/toggle-processed', name: 'api_toggle_suggestion_processed', methods: ['POST'])]
    #[IsGranted('ROLE_DELEGATE')]
    public function toggleSuggestionProcessed(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $suggestion = $entityManager->getRepository(Suggestion::class)->find($id);
        if (!$suggestion) {
            throw $this->createNotFoundException('Suggestion non trouvée.');
        }

        $suggestion->setIsProcessed(!$suggestion->isProcessed());
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'isProcessed' => $suggestion->isProcessed(),
            'id' => $suggestion->getId(),
        ]);
    }

    #[Route('/api/suggestions/pending', name: 'api_suggestions_pending', methods: ['GET'])]
    #[IsGranted('ROLE_DELEGATE')]
    public function getPendingSuggestions(EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        $queryBuilder = $entityManager->getRepository(Suggestion::class)
            ->createQueryBuilder('s')
            ->leftJoin('s.assignment', 'a')
            ->leftJoin('a.groups', 'g')
            ->where('s.isProcessed = :isProcessed')
            ->setParameter('isProcessed', false);

        if (!$this->isGranted('ROLE_ADMIN')) {
            $userGroups = $user->getGroups();
            $queryBuilder->andWhere('g IN (:userGroups)')
                ->setParameter('userGroups', $userGroups);
        }

        $suggestions = $queryBuilder->orderBy('s.createdAt', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        $data = array_map(function ($suggestion) {
            return [
                'id' => $suggestion->getId(),
                'suggestedBy' => $suggestion->getSuggestedBy()->getUserIdentifier(),
                'assignment' => [
                    'title' => $suggestion->getAssignment()->getTitle(),
                    'subjectCode' => $suggestion->getAssignment()->getSubject()->getCode(),
                ],
                'message' => $suggestion->getMessage(),
            ];
        }, $suggestions);

        return $this->json($data);
    }

}
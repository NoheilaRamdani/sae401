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

            // Construire les changements proposés
            $proposedChanges = [];

            // Normaliser les valeurs pour la comparaison
            $submittedTitle = $data['title'] ?? '';
            $currentTitle = $assignment->getTitle() ?? '';
            if ($submittedTitle !== $currentTitle) {
                $proposedChanges['title'] = $submittedTitle;
            }

            $submittedDescription = $data['description'] ?? '';
            $currentDescription = $assignment->getDescription() ?? '';
            if ($submittedDescription !== $currentDescription) {
                $proposedChanges['description'] = $submittedDescription;
            }

            $submittedDueDate = $data['due_date'] ? $data['due_date']->format('Y-m-d H:i:s') : null;
            $currentDueDate = $assignment->getDueDate() ? $assignment->getDueDate()->format('Y-m-d H:i:s') : null;
            if ($submittedDueDate !== $currentDueDate) {
                $proposedChanges['due_date'] = $submittedDueDate;
            }

            $submittedSubmissionType = $data['submission_type'] ?? '';
            $currentSubmissionType = $assignment->getSubmissionType() ?? '';
            if ($submittedSubmissionType !== $currentSubmissionType) {
                $proposedChanges['submission_type'] = $submittedSubmissionType;
            }

            $submittedSubmissionUrl = $data['submission_url'] ?? '';
            $currentSubmissionUrl = $assignment->getSubmissionUrl() ?? '';
            if ($submittedSubmissionUrl !== $currentSubmissionUrl) {
                $proposedChanges['submission_url'] = $submittedSubmissionUrl;
            }

            $submittedType = $data['type'] ?? '';
            $currentType = $assignment->getType() ?? '';
            if ($submittedType !== $currentType) {
                $proposedChanges['type'] = $submittedType;
            }

            // Ajout de la matière
            $submittedSubject = $data['subject'] ?? null;
            $currentSubjectId = $assignment->getSubject() ? $assignment->getSubject()->getId() : null;
            if ($submittedSubject && $submittedSubject->getId() !== $currentSubjectId) {
                $proposedChanges['subject_id'] = $submittedSubject->getId();
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
            $suggestion->setMessage($data['message'] ?? '');
            $suggestion->setProposedChanges($proposedChanges);
            $suggestion->setIsProcessed(false);

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
                        $assignment->setDueDate(new \DateTime($value));
                        break;
                    case 'submission_type':
                        $assignment->setSubmissionType($value);
                        break;
                    case 'submission_url':
                        $assignment->setSubmissionUrl($value);
                        break;
                    case 'type':
                        $assignment->setType($value);
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
                'proposedSubject' => $proposedSubject, // Passer la matière proposée au template
            ];
        }

        dump('Groupes de l\'utilisateur (délégué) :', $userGroups->toArray());
        dump('Suggestions récupérées :', $suggestions);

        return $this->render('suggestion/history.html.twig', [
            'suggestionsData' => $suggestionsData, // Passer les données préparées
        ]);
    }

    #[Route('/suggestions/{id}/review', name: 'review_suggestion', methods: ['GET', 'POST'])]
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
                            $assignment->setDueDate(new \DateTime($value));
                            break;
                        case 'submission_type':
                            $assignment->setSubmissionType($value);
                            break;
                        case 'submission_url':
                            $assignment->setSubmissionUrl($value);
                            break;
                        case 'type':
                            $assignment->setType($value);
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
            'proposedSubject' => $proposedSubject, // Passer la matière proposée
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

        dump('Groupes de l\'utilisateur (délégué) :', $userGroups->toArray());
        dump('Suggestions récupérées :', $suggestions);

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
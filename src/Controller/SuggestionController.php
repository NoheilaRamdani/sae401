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
use Psr\Log\LoggerInterface;

class SuggestionController extends AbstractController
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

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

            // Normalisation pour gérer null et chaînes vides
            $normalize = function ($value) {
                return $value === null ? '' : $value;
            };

            // Titre
            $submittedTitle = $normalize($data['title']);
            $currentTitle = $normalize($assignment->getTitle());
            if ($submittedTitle !== $currentTitle) {
                $proposedChanges['title'] = $submittedTitle;
                $originalValues['title'] = $currentTitle;
            }

            // Description
            $submittedDescription = $normalize($data['description']);
            $currentDescription = $normalize($assignment->getDescription());
            if ($submittedDescription !== $currentDescription) {
                $proposedChanges['description'] = $submittedDescription;
                $originalValues['description'] = $currentDescription;
            }

            // Date limite
            $submittedDueDate = $data['due_date'] ? $data['due_date']->format('Y-m-d H:i:s') : '';
            $currentDueDate = $assignment->getDueDate() ? $assignment->getDueDate()->format('Y-m-d H:i:s') : '';
            if ($submittedDueDate !== $currentDueDate) {
                $proposedChanges['due_date'] = $submittedDueDate;
                $originalValues['due_date'] = $currentDueDate;
            }

            // Type
            $submittedType = $normalize($data['type']);
            $currentType = $normalize($assignment->getType());
            if ($submittedType !== $currentType) {
                $proposedChanges['type'] = $submittedType;
                $originalValues['type'] = $currentType;
            }

            // URL de rendu
            $submittedSubmissionUrl = $normalize($data['submission_url']);
            $currentSubmissionUrl = $normalize($assignment->getSubmissionUrl());
            if ($submittedSubmissionUrl !== $currentSubmissionUrl) {
                $proposedChanges['submission_url'] = $submittedSubmissionUrl;
                $originalValues['submission_url'] = $currentSubmissionUrl;
            }

            // Autres instructions de rendu
            $submittedSubmissionOther = $normalize($data['submission_other']);
            $currentSubmissionOther = $normalize($assignment->getSubmissionOther());
            if ($submittedSubmissionOther !== $currentSubmissionOther) {
                $proposedChanges['submission_other'] = $submittedSubmissionOther;
                $originalValues['submission_other'] = $currentSubmissionOther;
            }

            // Lieu du cours
            $submittedCourseLocation = $normalize($data['course_location']);
            $currentCourseLocation = $normalize($assignment->getCourseLocation());
            if ($submittedCourseLocation !== $currentCourseLocation) {
                $proposedChanges['course_location'] = $submittedCourseLocation;
                $originalValues['course_location'] = $currentCourseLocation;
            }

            // Matière
            $submittedSubjectId = $data['subject'] ? $data['subject']->getId() : null;
            $currentSubjectId = $assignment->getSubject() ? $assignment->getSubject()->getId() : null;
            $originalValues['subject_id'] = $currentSubjectId;
            if ($submittedSubjectId !== $currentSubjectId) {
                $proposedChanges['subject_id'] = $submittedSubjectId;
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
            $suggestion->setStatus('PENDING'); // Changement ici : setIsProcessed(false) remplacé par setStatus('PENDING')
            $suggestion->setCreatedAt(new \DateTime());

            // Journalisation pour déboguer
            $this->logger->debug('Suggestion créée', [
                'assignment_id' => $assignment->getId(),
                'proposedChanges' => $proposedChanges,
                'originalValues' => $originalValues,
                'submittedData' => $data,
            ]);

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
            $suggestion->setStatus('ACCEPTED');
            $entityManager->flush();
            $this->addFlash('success', 'La suggestion a été validée et le devoir mis à jour !');
        } else {
            $this->addFlash('error', 'Erreur de sécurité lors de la validation.');
        }

        return $this->redirectToRoute('app_suggestions');
    }

    #[Route('/suggestions', name: 'app_suggestions', methods: ['GET'])]
    #[IsGranted('ROLE_DELEGATE')]
    public function suggestions(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 10;

        $queryBuilder = $entityManager->getRepository(Suggestion::class)
            ->createQueryBuilder('s')
            ->leftJoin('s.assignment', 'a')
            ->leftJoin('a.groups', 'g');

        if (!$this->isGranted('ROLE_ADMIN')) {
            $userGroups = $user->getGroups();
            $queryBuilder->andWhere('g IN (:userGroups)')
                ->setParameter('userGroups', $userGroups);
        }

        $countQueryBuilder = clone $queryBuilder;
        $totalSuggestions = $countQueryBuilder->select('COUNT(DISTINCT s.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $suggestions = $queryBuilder->orderBy('s.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $totalPages = ceil($totalSuggestions / $limit);

        $suggestionsData = [];
        foreach ($suggestions as $suggestion) {
            $proposedChanges = $suggestion->getProposedChanges();
            $proposedSubject = null;
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
            'current_page' => $page,
            'total_pages' => $totalPages,
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

        $proposedChanges = $suggestion->getProposedChanges();
        $proposedSubject = null;
        if (isset($proposedChanges['subject_id'])) {
            $proposedSubject = $entityManager->getRepository(Subject::class)->find($proposedChanges['subject_id']);
        }

        $originalValues = $suggestion->getOriginalValues();
        $originalSubject = null;
        if (isset($originalValues['subject_id'])) {
            $originalSubject = $entityManager->getRepository(Subject::class)->find($originalValues['subject_id']);
        }

        $this->logger->debug('Données pour review.html.twig', [
            'suggestion_id' => $suggestion->getId(),
            'proposedChanges' => $proposedChanges,
            'originalValues' => $originalValues,
            'proposedSubject' => $proposedSubject ? $proposedSubject->getName() : null,
            'originalSubject' => $originalSubject ? $originalSubject->getName() : null
        ]);

        $form = $this->createFormBuilder()
            ->add('approve', \Symfony\Component\Form\Extension\Core\Type\SubmitType::class, [
                'label' => '<i class="fa-solid fa-check"></i> Valider',
                'label_html' => true,
                'attr' => ['class' => 'btn btn-success row-start'],
            ])
            ->add('reject', \Symfony\Component\Form\Extension\Core\Type\SubmitType::class, [
                'label' => '<i class="fa-solid fa-xmark"></i> Rejeter',
                'label_html' => true,
                'attr' => ['class' => 'btn btn-danger row-start'],
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
                $suggestion->setStatus('ACCEPTED');
                $entityManager->flush();
                $this->addFlash('success', 'Suggestion validée et devoir mis à jour.');
                return $this->redirectToRoute('app_suggestions');
            } elseif ($form->get('reject')->isClicked()) {
                $suggestion->setStatus('REJECTED');
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
            'originalSubject' => $originalSubject,
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

        $newStatus = $suggestion->getStatus() === 'PENDING' ? 'ACCEPTED' : 'PENDING';
        $suggestion->setStatus($newStatus);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'status' => $suggestion->getStatus(),
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
            ->where('s.status = :status')
            ->setParameter('status', 'PENDING');

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
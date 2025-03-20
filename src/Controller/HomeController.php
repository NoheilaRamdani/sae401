<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Assignment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Form\SuggestionFormType;
use App\Form\AssignmentFormType;
use Symfony\Component\HttpFoundation\RedirectResponse;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté.');
        }

        $typeFilter = $request->query->get('type');

        $queryBuilder = $entityManager->getRepository(\App\Entity\Assignment::class)
            ->createQueryBuilder('a')
            ->where('a.due_date >= :now')
            ->setParameter('now', new \DateTime())
            ->orderBy('a.due_date', 'ASC');

        if ($this->isGranted('ROLE_ADMIN')) {
            if ($typeFilter) {
                $queryBuilder->andWhere('a.type = :type')->setParameter('type', $typeFilter);
            }
        } else {
            $groups = $user->getGroups();
            $queryBuilder
                ->join('a.groups', 'g')
                ->andWhere('g IN (:groups)')
                ->setParameter('groups', $groups);
            if ($typeFilter) {
                $queryBuilder->andWhere('a.type = :type')->setParameter('type', $typeFilter);
            }
        }

        $assignments = $queryBuilder->getQuery()->getResult();

        $now = new \DateTime();
        $assignmentsData = [];
        $notifications = [];
        foreach ($assignments as $assignment) {
            $dueDate = $assignment->getDueDate();
            $daysUntilDue = $now->diff($dueDate)->days * ($dueDate >= $now ? 1 : -1);
            $hoursUntilDue = (int) ($now->diff($dueDate)->days * 24 + $now->diff($dueDate)->h);
            $color = $assignment->getSubject()->getColor() ?? '#3788d8';
            if ($dueDate < $now) {
                $color = '#808080';
            } elseif ($daysUntilDue < 1) {
                $color = '#dc3545';
            } elseif ($daysUntilDue < 3) {
                $color = '#fd7e14';
            } elseif ($daysUntilDue >= 5) {
                $color = '#28a745';
            }

            $assignmentsData[] = [
                'assignment' => $assignment,
                'daysUntilDue' => $daysUntilDue,
                'color' => $color,
                'isCompleted' => $assignment->isCompleted(),
                'subjectCode' => $assignment->getSubject()->getCode(),
            ];

            if (!$assignment->isCompleted() && ($hoursUntilDue === 24 || $hoursUntilDue === 48)) {
                $notifications[] = [
                    'title' => $assignment->getTitle(),
                    'hoursUntilDue' => $hoursUntilDue,
                ];
            }
        }

        $suggestions = [];
        if ($this->isGranted('ROLE_DELEGATE')) {
            $suggestions = $entityManager->getRepository(\App\Entity\Suggestion::class)
                ->createQueryBuilder('s')
                ->where('s.isProcessed = :isProcessed')
                ->setParameter('isProcessed', false)
                ->orderBy('s.createdAt', 'DESC')
                ->setMaxResults(5)
                ->getQuery()
                ->getResult();
        }

        return $this->render('home/index.html.twig', [
            'user' => $user,
            'groups' => $user->getGroups(),
            'assignments_data' => $assignmentsData,
            'type_filter' => $typeFilter,
            'notifications' => $notifications,
            'suggestions' => $suggestions,
        ]);
    }

    #[Route('/api/assignments', name: 'api_assignments', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getAssignments(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $typeFilter = $request->query->get('type');

        $queryBuilder = $entityManager->getRepository(\App\Entity\Assignment::class)
            ->createQueryBuilder('a');

        if ($this->isGranted('ROLE_ADMIN')) {
            if ($typeFilter) {
                $queryBuilder->andWhere('a.type = :type')->setParameter('type', $typeFilter);
            }
        } else {
            $groups = $user->getGroups();
            $queryBuilder
                ->join('a.groups', 'g')
                ->andWhere('g IN (:groups)')
                ->setParameter('groups', $groups);
            if ($typeFilter) {
                $queryBuilder->andWhere('a.type = :type')->setParameter('type', $typeFilter);
            }
        }

        $assignments = $queryBuilder->getQuery()->getResult();

        $events = [];
        $now = new \DateTime();
        foreach ($assignments as $assignment) {
            $dueDate = $assignment->getDueDate();
            $daysUntilDue = $now->diff($dueDate)->days * ($dueDate >= $now ? 1 : -1);
            $color = $assignment->getSubject()->getColor() ?? '#3788d8';
            if ($dueDate < $now) {
                $color = '#808080';
            } elseif ($daysUntilDue < 1) {
                $color = '#dc3545';
            } elseif ($daysUntilDue < 3) {
                $color = '#fd7e14';
            } elseif ($daysUntilDue >= 5) {
                $color = '#28a745';
            }

            $events[] = [
                'id' => $assignment->getId(),
                'title' => $assignment->getTitle(),
                'start' => $dueDate->format('c'),
                'submissionUrl' => $assignment->getSubmissionUrl() ?? null,
                'color' => $color,
                'classNames' => $assignment->isCompleted() ? ['completed-event'] : [],
                'extendedProps' => [
                    'createdAt' => $assignment->getCreatedAt()->format('d/m/Y H:i'),
                    'description' => $assignment->getDescription() ?? 'Lorem ipsum...',
                    'submissionUrl' => $assignment->getSubmissionUrl() ?? null,
                    'isCompleted' => $assignment->isCompleted(),
                    'type' => $assignment->getType(),
                ]
            ];
        }

        return $this->json($events);
    }

    #[Route('/assignments/{id}/edit', name: 'app_edit_assignment', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')] // Ou ajoutez ROLE_DELEGATE si nécessaire
    public function editAssignment(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $assignment = $entityManager->getRepository(Assignment::class)->find($id);
        if (!$assignment) {
            throw $this->createNotFoundException('Devoir non trouvé.');
        }

        // Vérification des permissions (similaire à getAssignmentDetails)
        if (!$this->isGranted('ROLE_ADMIN')) {
            /** @var User $user */
            $user = $this->getUser();
            $groups = $user->getGroups();
            $hasAccess = false;
            foreach ($assignment->getGroups() as $group) {
                if ($groups->contains($group)) {
                    $hasAccess = true;
                    break;
                }
            }
            if (!$hasAccess) {
                throw $this->createAccessDeniedException('Vous n\'avez pas accès à ce devoir.');
            }
        }

        // Créez le formulaire avec AssignmentFormType
        $form = $this->createForm(AssignmentFormType::class, $assignment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Devoir modifié avec succès !');
            return $this->redirectToRoute('app_home');
        }

        return $this->render('assignment/edit.html.twig', [
            'form' => $form->createView(),
            'assignment' => $assignment,
        ]);
    }
    #[Route('/api/assignments/{id}/toggle-complete', name: 'api_toggle_complete', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function toggleComplete(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $assignment = $entityManager->getRepository(\App\Entity\Assignment::class)->find($id);
        if (!$assignment) {
            throw $this->createNotFoundException('Devoir non trouvé.');
        }

        if (!$this->isGranted('ROLE_ADMIN')) {
            $user = $this->getUser();
            $groups = $user->getGroups();
            $hasAccess = false;
            foreach ($assignment->getGroups() as $group) {
                if ($groups->contains($group)) {
                    $hasAccess = true;
                    break;
                }
            }
            if (!$hasAccess) {
                throw $this->createAccessDeniedException('Vous n\'avez pas accès à ce devoir.');
            }
        }

        $assignment->setIsCompleted(!$assignment->isCompleted());
        $entityManager->flush();

        return $this->json(['success' => true, 'isCompleted' => $assignment->isCompleted()]);
    }

    #[Route('/cal.ics', name: 'ical_user_feed', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function generateUserICalFeed(EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté.');
        }

        $queryBuilder = $entityManager->getRepository(\App\Entity\Assignment::class)
            ->createQueryBuilder('a')
            ->where('a.due_date >= :now')
            ->setParameter('now', new \DateTime())
            ->orderBy('a.due_date', 'ASC');

        if (!$this->isGranted('ROLE_ADMIN')) {
            $groups = $user->getGroups();
            $queryBuilder
                ->join('a.groups', 'g')
                ->andWhere('g IN (:groups)')
                ->setParameter('groups', $groups);
        }

        $assignments = $queryBuilder->getQuery()->getResult();

        $ical = "BEGIN:VCALENDAR\r\n";
        $ical .= "VERSION:2.0\r\n";
        $ical .= "PRODID:-//TonApp//StudentAssignments//EN\r\n";
        $ical .= "CALSCALE:GREGORIAN\r\n";
        $ical .= "METHOD:PUBLISH\r\n";
        $ical .= "X-WR-CALNAME:Rendus de " . $user->getUserIdentifier() . "\r\n";

        foreach ($assignments as $assignment) {
            $dueDate = $assignment->getDueDate();
            $ical .= "BEGIN:VEVENT\r\n";
            $ical .= "UID:" . $assignment->getId() . "@tonapp.com\r\n";
            $ical .= "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
            $ical .= "DTSTART:" . $dueDate->format('Ymd\THis') . "\r\n";
            $ical .= "DTEND:" . $dueDate->modify('+1 hour')->format('Ymd\THis') . "\r\n";
            $title = $assignment->getTitle();
            if ($assignment->isCompleted()) {
                $title = "~~" . $title . "~~";
            }
            $ical .= "SUMMARY:" . $title . "\r\n";
            $ical .= "DESCRIPTION:" . ($assignment->getDescription() ?? 'Pas de description') . "\r\n";
            $ical .= "LOCATION:" . ($assignment->getSubmissionUrl() ?? 'Non spécifié') . "\r\n";
            $ical .= "END:VEVENT\r\n";
        }

        $ical .= "END:VCALENDAR\r\n";

        return new Response($ical, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="cal.ics"',
        ]);
    }

    #[Route('/api/assignments/{id}/suggest-modification', name: 'api_suggest_modification', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function suggestModification(int $id, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $assignment = $entityManager->getRepository(\App\Entity\Assignment::class)->find($id);
        if (!$assignment) {
            throw $this->createNotFoundException('Devoir non trouvé.');
        }

        /** @var User $user */
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

        $data = json_decode($request->getContent(), true);
        $message = $data['message'] ?? '';
        if (empty($message)) {
            return $this->json(['error' => 'Message requis'], 400);
        }

        $suggestion = new \App\Entity\Suggestion();
        $suggestion->setAssignment($assignment)
            ->setSuggestedBy($user)
            ->setMessage($message);
        $entityManager->persist($suggestion);
        $entityManager->flush();

        return $this->json(['success' => true, 'message' => 'Suggestion envoyée aux délégués']);
    }

    #[Route('/suggestions', name: 'app_suggestions')]
    #[IsGranted('ROLE_DELEGATE')]
    public function suggestions(EntityManagerInterface $entityManager): Response
    {
        $suggestions = $entityManager->getRepository(\App\Entity\Suggestion::class)
            ->createQueryBuilder('s')
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('home/suggestions.html.twig', [
            'suggestions' => $suggestions,
        ]);
    }

    #[Route('/api/suggestions/{id}/toggle-processed', name: 'api_toggle_suggestion_processed', methods: ['POST'])]
    #[IsGranted('ROLE_DELEGATE')]
    public function toggleSuggestionProcessed(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $suggestion = $entityManager->getRepository(\App\Entity\Suggestion::class)->find($id);
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
        $suggestions = $entityManager->getRepository(\App\Entity\Suggestion::class)
            ->createQueryBuilder('s')
            ->where('s.isProcessed = :isProcessed')
            ->setParameter('isProcessed', false)
            ->orderBy('s.createdAt', 'DESC')
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

    #[Route('/assignments/history', name: 'app_assignments_history')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function assignmentsHistory(EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté.');
        }

        $queryBuilder = $entityManager->getRepository(\App\Entity\Assignment::class)
            ->createQueryBuilder('a')
            ->orderBy('a.due_date', 'DESC');

        if (!$this->isGranted('ROLE_ADMIN')) {
            $groups = $user->getGroups();
            $queryBuilder
                ->join('a.groups', 'g')
                ->andWhere('g IN (:groups)')
                ->setParameter('groups', $groups);
        }

        $assignments = $queryBuilder->getQuery()->getResult();

        $assignmentsData = [];
        $now = new \DateTime();
        foreach ($assignments as $assignment) {
            $dueDate = $assignment->getDueDate();
            $daysUntilDue = $now->diff($dueDate)->days * ($dueDate >= $now ? 1 : -1);
            $color = $assignment->getSubject()->getColor() ?? '#3788d8';
            if ($dueDate < $now) {
                $color = '#808080';
            }

            $assignmentsData[] = [
                'assignment' => $assignment,
                'daysUntilDue' => $daysUntilDue,
                'color' => $color,
                'isCompleted' => $assignment->isCompleted(),
                'subjectCode' => $assignment->getSubject()->getCode(),
            ];
        }

        return $this->render('home/assignments_history.html.twig', [
            'user' => $user,
            'assignments_data' => $assignmentsData,
        ]);
    }

    #[Route('/assignments/{id}/suggest-modification-form', name: 'app_suggest_modification_form', methods: ['GET', 'POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function suggestModificationForm(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $assignment = $entityManager->getRepository(\App\Entity\Assignment::class)->find($id);
        if (!$assignment) {
            throw $this->createNotFoundException('Devoir non trouvé.');
        }

        /** @var User $user */
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

        $form = $this->createForm(SuggestionFormType::class, null, [
            'assignment' => $assignment,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $message = "Suggestion de modification pour '{$assignment->getTitle()}':\n";
            if ($data['title'] !== $assignment->getTitle()) {
                $message .= "Nouveau titre : {$data['title']} (ancien : {$assignment->getTitle()})\n";
            }
            if ($data['description'] !== $assignment->getDescription()) {
                $message .= "Nouvelle description : {$data['description']} (ancienne : {$assignment->getDescription()})\n";
            }
            if ($data['due_date'] != $assignment->getDueDate()) {
                $message .= "Nouvelle date limite : {$data['due_date']->format('d/m/Y H:i')} (ancienne : {$assignment->getDueDate()->format('d/m/Y H:i')})\n";
            }
            if ($data['submission_type'] !== $assignment->getSubmissionType()) {
                $message .= "Nouveau mode de rendu : {$data['submission_type']} (ancien : {$assignment->getSubmissionType()})\n";
            }
            if ($data['submission_url'] !== $assignment->getSubmissionUrl()) {
                $message .= "Nouvelle URL de soumission : {$data['submission_url']} (ancienne : {$assignment->getSubmissionUrl()})\n";
            }
            if ($data['type'] !== $assignment->getType()) {
                $message .= "Nouveau type : {$data['type']} (ancien : {$assignment->getType()})\n";
            }
            $message .= "Commentaire : {$data['message']}";

            $suggestion = new \App\Entity\Suggestion();
            $suggestion->setAssignment($assignment)
                ->setSuggestedBy($user)
                ->setMessage($message);

            $entityManager->persist($suggestion);
            $entityManager->flush();

            $this->addFlash('success', 'Suggestion envoyée aux délégués !');
            return $this->redirectToRoute('app_home');
        }

        return $this->render('home/suggest_modification_form.html.twig', [
            'form' => $form->createView(),
            'assignment' => $assignment,
        ]);
    }
}
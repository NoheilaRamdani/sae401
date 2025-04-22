<?php
namespace App\Controller;

use App\Entity\Assignment;
use App\Entity\Suggestion;
use App\Form\AssignmentFormType;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class AssignmentController extends AbstractController
{
    #[Route('/assignment/add', name: 'add_assignment', methods: ['GET', 'POST'])]
    public function addAssignment(Request $request, EntityManagerInterface $entityManager, NotificationService $notificationService): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté.');
        }

        if (!$this->isGranted('ROLE_DELEGATE') && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Accès réservé aux délégués ou admins.');
        }

        $assignment = new Assignment();
        $form = $this->createForm(AssignmentFormType::class, $assignment);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $assignment->setCreatedBy($user);
            $assignment->setCreatedAt(new \DateTime());
            $assignment->setUpdatedAt(new \DateTime());

            $entityManager->persist($assignment);
            $entityManager->flush();

            // Envoyer les notifications par email
            try {
                $notificationService->sendAssignmentNotification($assignment);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Devoir ajouté, mais erreur lors de l\'envoi des notifications : ' . $e->getMessage());
            }

            $this->addFlash('success', 'Le devoir a été ajouté avec succès !');
            return $this->redirectToRoute('app_home');
        }

        return $this->render('assignment/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/assignments/{id}/edit', name: 'app_edit_assignment', methods: ['GET', 'POST'])]
    public function editAssignment(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté.');
        }

        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_DELEGATE')) {
            throw $this->createAccessDeniedException('Accès réservé aux admins ou délégués.');
        }

        $assignment = $entityManager->getRepository(Assignment::class)->find($id);
        if (!$assignment) {
            throw $this->createNotFoundException('Devoir non trouvé.');
        }

        if (!$this->isGranted('ROLE_ADMIN')) {
            $userGroups = $user->getGroups();
            $assignmentGroups = $assignment->getGroups();
            $hasAccess = false;
            foreach ($assignmentGroups as $group) {
                if ($userGroups->contains($group)) {
                    $hasAccess = true;
                    break;
                }
            }
            if (!$hasAccess && $assignment->getCreatedBy() !== $user) {
                throw $this->createAccessDeniedException('Vous n\'avez pas accès à ce devoir.');
            }
        }

        $form = $this->createForm(AssignmentFormType::class, $assignment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $assignment->setUpdatedAt(new \DateTime());
            $entityManager->flush();
            $this->addFlash('success', 'Devoir modifié avec succès !');
            return $this->redirectToRoute('app_home');
        }

        return $this->render('assignment/edit.html.twig', [
            'form' => $form->createView(),
            'assignment' => $assignment,
        ]);
    }

    #[Route('/assignment/{id}/delete', name: 'delete_assignment', methods: ['POST'])]
    public function deleteAssignment(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté.');
        }

        if (!$this->isGranted('ROLE_DELEGATE') && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Accès réservé aux délégués ou admins.');
        }

        $assignment = $entityManager->getRepository(Assignment::class)->find($id);
        if (!$assignment) {
            throw $this->createNotFoundException('Devoir non trouvé.');
        }

        if (!$this->isGranted('ROLE_ADMIN')) {
            $userGroups = $user->getGroups();
            $assignmentGroups = $assignment->getGroups();
            $hasAccess = false;
            foreach ($assignmentGroups as $group) {
                if ($userGroups->contains($group)) {
                    $hasAccess = true;
                    break;
                }
            }
            if (!$hasAccess && $assignment->getCreatedBy() !== $user) {
                throw $this->createAccessDeniedException('Vous n\'avez pas accès à ce devoir.');
            }
        }

        if ($this->isCsrfTokenValid('delete'.$id, $request->request->get('_token'))) {
            $suggestions = $entityManager->getRepository(\App\Entity\Suggestion::class)
                ->findBy(['assignment' => $assignment]);
            foreach ($suggestions as $suggestion) {
                $entityManager->remove($suggestion);
            }

            $entityManager->remove($assignment);
            $entityManager->flush();

            $this->addFlash('success', 'Le devoir et ses suggestions associées ont été supprimés avec succès !');
        } else {
            $this->addFlash('error', 'Erreur de sécurité lors de la suppression.');
        }

        return $this->redirectToRoute('manage_assignments');
    }

    #[Route('/assignments/manage', name: 'manage_assignments', methods: ['GET', 'POST'])]
    public function manageAssignments(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Utilisateur non connecté.');
        }

        if (!$this->isGranted('ROLE_DELEGATE') && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Accès réservé aux délégués ou admins.');
        }

        try {
            // Récupérer le numéro de page depuis la requête (par défaut, page 1)
            $page = max(1, $request->query->getInt('page', 1));
            $limit = 10; // Nombre de tâches par page

            // Construire la requête de base
            $queryBuilder = $entityManager->getRepository(Assignment::class)
                ->createQueryBuilder('a')
                ->leftJoin('a.groups', 'g');

            // Appliquer les filtres
            $subjectId = $request->query->get('subject', $request->request->get('subject'));
            $groupId = $request->query->get('group', $request->request->get('group'));

            if ($subjectId) {
                $queryBuilder->andWhere('a.subject = :subject')
                    ->setParameter('subject', $subjectId);
            }

            if ($groupId) {
                $queryBuilder->andWhere('g.id = :group')
                    ->setParameter('group', $groupId);
            }

            if (!$this->isGranted('ROLE_ADMIN')) {
                $userGroups = $user->getGroups();
                if (!$userGroups) {
                    $assignments = [];
                    $totalAssignments = 0;
                } else {
                    $queryBuilder->andWhere('g IN (:userGroups)')
                        ->setParameter('userGroups', $userGroups);
                }
            }

            // Cloner la requête pour compter le total
            $countQueryBuilder = clone $queryBuilder;
            $totalAssignments = $countQueryBuilder->select('COUNT(DISTINCT a.id)')
                ->getQuery()
                ->getSingleScalarResult();

            // Appliquer la pagination
            $assignments = $queryBuilder->orderBy('a.due_date', 'ASC')
                ->setFirstResult(($page - 1) * $limit)
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();

            // Calculer le nombre total de pages
            $totalPages = ceil($totalAssignments / $limit);

            $subjects = $entityManager->getRepository('App\Entity\Subject')->findAll();
            $groups = $this->isGranted('ROLE_ADMIN')
                ? $entityManager->getRepository('App\Entity\Group')->findAll()
                : $user->getGroups();

            return $this->render('assignment/manage.html.twig', [
                'assignments' => $assignments ?? [],
                'subjects' => $subjects ?? [],
                'groups' => $groups ?? [],
                'current_subject' => $subjectId,
                'current_group' => $groupId,
                'is_delegate_or_admin' => $this->isGranted('ROLE_DELEGATE') || $this->isGranted('ROLE_ADMIN'),
                'entity_manager' => $entityManager,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_assignments' => $totalAssignments,
            ]);
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Erreur lors du chargement des devoirs : ' . $e->getMessage());
        }
    }

    #[Route('/assignments', name: 'app_assignments', methods: ['GET'])]
    public function assignments(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Utilisateur non connecté.');
        }

        try {
            // Récupérer le numéro de page depuis la requête (par défaut, page 1)
            $page = max(1, $request->query->getInt('page', 1));
            $limit = 10; // Nombre de tâches par page

            // Construire la requête de base
            $queryBuilder = $entityManager->getRepository(Assignment::class)
                ->createQueryBuilder('a')
                ->leftJoin('a.groups', 'g');

            // Appliquer les filtres
            $subjectId = $request->query->get('subject', $request->request->get('subject'));
            $groupId = $request->query->get('group', $request->request->get('group'));

            if ($subjectId) {
                $queryBuilder->andWhere('a.subject = :subject')
                    ->setParameter('subject', $subjectId);
            }

            if ($groupId) {
                $queryBuilder->andWhere('g.id = :group')
                    ->setParameter('group', $groupId);
            }

            if (!$this->isGranted('ROLE_ADMIN')) {
                $userGroups = $user->getGroups();
                if (!$userGroups) {
                    $assignments = [];
                    $totalAssignments = 0;
                } else {
                    $queryBuilder->andWhere('g IN (:userGroups)')
                        ->setParameter('userGroups', $userGroups);
                }
            }

            // Cloner la requête pour compter le total
            $countQueryBuilder = clone $queryBuilder;
            $totalAssignments = $countQueryBuilder->select('COUNT(DISTINCT a.id)')
                ->getQuery()
                ->getSingleScalarResult();

            // Appliquer la pagination
            $assignments = $queryBuilder->orderBy('a.due_date', 'ASC')
                ->setFirstResult(($page - 1) * $limit)
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();

            // Calculer le nombre total de pages
            $totalPages = ceil($totalAssignments / $limit);

            $subjects = $entityManager->getRepository('App\Entity\Subject')->findAll();
            $groups = $this->isGranted('ROLE_ADMIN')
                ? $entityManager->getRepository('App\Entity\Group')->findAll()
                : $user->getGroups();

            return $this->render('assignment/assignments_history.html.twig', [
                'assignments' => $assignments ?? [],
                'subjects' => $subjects ?? [],
                'groups' => $groups ?? [],
                'current_subject' => $subjectId,
                'current_group' => $groupId,
                'is_delegate_or_admin' => $this->isGranted('ROLE_DELEGATE') || $this->isGranted('ROLE_ADMIN'),
                'current_page' => $page,
                'total_pages' => $totalPages,
            ]);
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Erreur lors du chargement des devoirs : ' . $e->getMessage());
        }
    }

    #[Route('/api/assignments', name: 'api_assignments', methods: ['GET'])]
    public function getAssignments(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté.');
        }

        $typeFilter = $request->query->get('type');
        $startDate = $request->query->get('start') ? new \DateTime($request->query->get('start')) : null;
        $endDate = $request->query->get('end') ? new \DateTime($request->query->get('end')) : null;

        $queryBuilder = $entityManager->getRepository(Assignment::class)
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

        if ($startDate) {
            $queryBuilder->andWhere('a.due_date >= :start')
                ->setParameter('start', $startDate);
        }
        if ($endDate) {
            $queryBuilder->andWhere('a.due_date <= :end')
                ->setParameter('end', $endDate);
        }

        $assignments = $queryBuilder->getQuery()->getResult();

        $events = [];
        $now = new \DateTime();
        foreach ($assignments as $assignment) {
            $dueDate = $assignment->getDueDate();
            $daysUntilDue = $now->diff($dueDate)->days * ($dueDate >= $now ? 1 : -1);
            $color = $assignment->getSubject() ? $assignment->getSubject()->getColor() : '#3788d8';

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
                    'type' => $assignment->getType() ?? 'devoir',
                ]
            ];
        }

        return $this->json($events);
    }

    #[Route('/api/assignments/{id}', name: 'api_assignment_details', methods: ['GET'])]
    public function getAssignmentDetails(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Vous devez être connecté'], 401);
        }

        $assignment = $entityManager->getRepository(Assignment::class)->find($id);
        if (!$assignment) {
            return $this->json(['error' => 'Devoir non trouvé'], 404);
        }

        if (!$this->isGranted('ROLE_ADMIN')) {
            $groups = $user->getGroups();
            $hasAccess = false;
            for ($i = 0; $i < count($assignment->getGroups()->toArray()); $i++) {
                $group = $assignment->getGroups()->toArray()[$i];
                if ($groups->contains($group)) {
                    $hasAccess = true;
                    break;
                }
            }
            if (!$hasAccess) {
                return $this->json(['error' => 'Accès refusé'], 403);
            }
        }

        return $this->json([
            'id' => $assignment->getId(),
            'title' => $assignment->getTitle(),
            'start' => $assignment->getDueDate()->setTimezone(new \DateTimeZone('Europe/Paris'))->format('c'),
            'createdAt' => $assignment->getCreatedAt()->format('d/m/Y H:i'),
            'description' => $assignment->getDescription() ?? 'Aucune description',
            'submissionOther' => $assignment->getSubmissionOther() ?? null,
            'submissionUrl' => $assignment->getSubmissionUrl() ?? null,
            'submissionType' => $assignment->getSubmissionType() ?? null,
            'courseLocation' => $assignment->getCourseLocation() ?? null,
            'isCompleted' => $assignment->isCompleted(),
            'type' => $assignment->getType() ?? 'devoir',
            'subject' => $assignment->getSubject() ? [
                'code' => $assignment->getSubject()->getCode(),
                'name' => $assignment->getSubject()->getName(),
            ] : null,
        ]);
    }

    #[Route('/api/assignments/{id}/toggle-complete', name: 'api_toggle_complete', methods: ['POST'])]
    public function toggleComplete(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté.');
        }

        $assignment = $entityManager->getRepository(Assignment::class)->find($id);
        if (!$assignment) {
            throw $this->createNotFoundException('Devoir non trouvé.');
        }

        if (!$this->isGranted('ROLE_ADMIN')) {
            $groups = $user->getGroups();
            $hasAccess = false;
            for ($i = 0; $i < count($assignment->getGroups()->toArray()); $i++) {
                $group = $assignment->getGroups()->toArray()[$i];
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
}
<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté.');
        }

        // Si l'utilisateur est un administrateur, récupérer tous les devoirs
        if ($this->isGranted('ROLE_ADMIN')) {
            $assignments = $entityManager->getRepository(\App\Entity\Assignment::class)
                ->createQueryBuilder('a')
                ->where('a.due_date >= :now') // Corrigé de dueDate à due_date
                ->setParameter('now', new \DateTime())
                ->orderBy('a.due_date', 'ASC') // Corrigé de dueDate à due_date
                ->getQuery()
                ->getResult();

            return $this->render('home/index.html.twig', [
                'user' => $user,
                'groups' => $user->getGroups(),
                'assignments' => $assignments,
            ]);
        }

        // Pour les utilisateurs normaux, garder le comportement actuel
        $groups = $user->getGroups();

        $assignments = $entityManager->getRepository(\App\Entity\Assignment::class)
            ->createQueryBuilder('a')
            ->join('a.groups', 'g')
            ->where('g IN (:groups)')
            ->setParameter('groups', $groups)
            ->andWhere('a.due_date >= :now') // Corrigé de dueDate à due_date
            ->setParameter('now', new \DateTime())
            ->orderBy('a.due_date', 'ASC') // Déjà correct
            ->getQuery()
            ->getResult();

        return $this->render('home/index.html.twig', [
            'user' => $user,
            'groups' => $groups,
            'assignments' => $assignments,
        ]);
    }

    #[Route('/api/assignments', name: 'api_assignments', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getAssignments(EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        // Si l'utilisateur est un administrateur, récupérer tous les devoirs
        if ($this->isGranted('ROLE_ADMIN')) {
            $assignments = $entityManager->getRepository(\App\Entity\Assignment::class)
                ->createQueryBuilder('a')
                ->getQuery()
                ->getResult();
        } else {
            // Pour les utilisateurs normaux, garder le comportement actuel
            $groups = $user->getGroups();
            $assignments = $entityManager->getRepository(\App\Entity\Assignment::class)
                ->createQueryBuilder('a')
                ->join('a.groups', 'g')
                ->where('g IN (:groups)')
                ->setParameter('groups', $groups)
                ->getQuery()
                ->getResult();
        }

        $events = [];
        foreach ($assignments as $assignment) {
            $events[] = [
                'id' => $assignment->getId(),
                'title' => $assignment->getTitle(),
                'start' => $assignment->getDueDate()->format('c'), // Fonctionne car getDueDate() existe
                'submissionUrl' => $assignment->getSubmissionUrl() ?? null,
                'color' => $assignment->getSubject()->getColor() ?? '#3788d8',
                'extendedProps' => [
                    // 'createdBy' => $assignment->getCreatedBy() ? $assignment->getCreatedBy()->getFirstName() . ' ' . $assignment->getCreatedBy()->getLastName() : 'Inconnu',
                    'createdAt' => $assignment->getCreatedAt()->format('d/m/Y H:i'),
                    'description' => $assignment->getDescription() ?? 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
                    'submissionUrl' => $assignment->getSubmissionUrl() ?? null
                ]
            ];
        }

        return $this->json($events);
    }

    #[Route('/api/assignments/{id}', name: 'api_assignment_details', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getAssignmentDetails(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $assignment = $entityManager->getRepository(\App\Entity\Assignment::class)->find($id);

        if (!$assignment) {
            throw $this->createNotFoundException('Devoir non trouvé.');
        }

        // Si l'utilisateur n'est pas admin, vérifier qu'il a accès à ce devoir
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

        return $this->json([
            'id' => $assignment->getId(),
            'title' => $assignment->getTitle(),
            'start' => $assignment->getDueDate()->format('c'), 
            // 'createdBy' => $assignment->getCreatedBy() ? $assignment->getCreatedBy()->getFirstName() . ' ' . $assignment->getCreatedBy()->getLastName() : 'Inconnu',
            'createdAt' => $assignment->getCreatedAt()->format('d/m/Y H:i'),
            'description' => $assignment->getDescription() ?? 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
            'submissionUrl' => $assignment->getSubmissionUrl() ?? null
        ]);
    }
}
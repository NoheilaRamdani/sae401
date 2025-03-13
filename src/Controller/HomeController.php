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
    #[IsGranted('ROLE_USER')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté.');
        }

        $groups = $user->getGroups();

        $assignments = $entityManager->getRepository(\App\Entity\Assignment::class)
            ->createQueryBuilder('a')
            ->join('a.groups', 'g') // Changement ici : 'groups' au lieu de 'assignmentGroup'
            ->where('g IN (:groups)')
            ->setParameter('groups', $groups)
            ->andWhere('a.dueDate >= :now')
            ->setParameter('now', new \DateTime())
            ->orderBy('a.dueDate', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('home/index.html.twig', [
            'user' => $user,
            'groups' => $groups,
            'assignments' => $assignments,
        ]);
    }

    #[Route('/api/assignments', name: 'api_assignments', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getAssignments(EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $groups = $user->getGroups();

        $assignments = $entityManager->getRepository(\App\Entity\Assignment::class)
            ->createQueryBuilder('a')
            ->join('a.groups', 'g') // Changement ici : 'groups' au lieu de 'assignmentGroup'
            ->where('g IN (:groups)')
            ->setParameter('groups', $groups)
            ->getQuery()
            ->getResult();

        $events = [];
        foreach ($assignments as $assignment) {
            $events[] = [
                'id' => $assignment->getId(),
                'title' => $assignment->getTitle(),
                'start' => $assignment->getDueDate()->format('c'),
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
    #[IsGranted('ROLE_USER')]
    public function getAssignmentDetails(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $assignment = $entityManager->getRepository(\App\Entity\Assignment::class)->find($id);

        if (!$assignment) {
            throw $this->createNotFoundException('Devoir non trouvé.');
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
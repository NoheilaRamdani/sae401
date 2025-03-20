<?php

namespace App\Controller;

use App\Entity\Assignment;
use App\Form\AssignmentFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

class AssignmentController extends AbstractController
{
    #[Route('/assignment/add', name: 'add_assignment', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_DELEGATE')]
    public function addAssignment(Request $request, EntityManagerInterface $entityManager): Response
    {
        $assignment = new Assignment();
        $form = $this->createForm(AssignmentFormType::class, $assignment);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $assignment->setCreatedBy($this->getUser());
            $assignment->setCreatedAt(new \DateTime());
            $assignment->setUpdatedAt(new \DateTime());

            $entityManager->persist($assignment);
            $entityManager->flush();

            $this->addFlash('success', 'Le devoir a été ajouté avec succès !');
            return $this->redirectToRoute('app_home');
        }

        return $this->render('assignment/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/assignment/{id}/edit', name: 'edit_assignment', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_DELEGATE')]
    public function editAssignment(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $assignment = $entityManager->getRepository(Assignment::class)->find($id);

        if (!$assignment) {
            throw $this->createNotFoundException('Devoir non trouvé.');
        }

        if ($assignment->getCreatedBy() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à modifier ce devoir.');
        }

        $form = $this->createForm(AssignmentFormType::class, $assignment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $assignment->setUpdatedAt(new \DateTime());
            $entityManager->flush();

            $this->addFlash('success', 'Le devoir a été modifié avec succès !');
            return $this->redirectToRoute('manage_assignments');
        }

        return $this->render('assignment/edit.html.twig', [
            'form' => $form->createView(),
            'assignment' => $assignment,
        ]);
    }

    #[Route('/assignment/{id}/delete', name: 'delete_assignment', methods: ['POST'])]
    #[IsGranted('ROLE_DELEGATE')]
    public function deleteAssignment(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $assignment = $entityManager->getRepository(Assignment::class)->find($id);

        if (!$assignment) {
            throw $this->createNotFoundException('Devoir non trouvé.');
        }

        if ($assignment->getCreatedBy() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à supprimer ce devoir.');
        }

        if ($this->isCsrfTokenValid('delete'.$id, $request->request->get('_token'))) {
            $entityManager->remove($assignment);
            $entityManager->flush();

            $this->addFlash('success', 'Le devoir a été supprimé avec succès !');
        } else {
            $this->addFlash('error', 'Erreur de sécurité lors de la suppression.');
        }

        return $this->redirectToRoute('manage_assignments');
    }

    #[Route('/assignments/manage', name: 'manage_assignments', methods: ['GET'])]
    #[IsGranted('ROLE_DELEGATE')]
    public function manageAssignments(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Utilisateur non connecté.');
        }

        try {
            $queryBuilder = $entityManager->getRepository(Assignment::class)
                ->createQueryBuilder('a')
                ->leftJoin('a.groups', 'g');

            $subjectId = $request->query->get('subject');
            $groupId = $request->query->get('group');

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
                } else {
                    $queryBuilder->andWhere('g IN (:userGroups)')
                        ->setParameter('userGroups', $userGroups);
                    $assignments = $queryBuilder->orderBy('a.due_date', 'ASC')
                        ->getQuery()
                        ->getResult();
                }
            } else {
                $assignments = $queryBuilder->orderBy('a.due_date', 'ASC')
                    ->getQuery()
                    ->getResult();
            }

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
            ]);
        } catch (\Exception $e) {
            dump($e->getMessage());
            throw $this->createNotFoundException('Erreur lors du chargement des devoirs : ' . $e->getMessage());
        }
    }

    #[Route('/assignments/history', name: 'assignments_history', methods: ['GET'])]
    public function assignmentsHistory(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Utilisateur non connecté.');
        }

        try {
            $queryBuilder = $entityManager->getRepository(Assignment::class)
                ->createQueryBuilder('a')
                ->leftJoin('a.groups', 'g');

            $subjectId = $request->query->get('subject');
            $groupId = $request->query->get('group');

            if ($subjectId) {
                $queryBuilder->andWhere('a.subject = :subject')
                    ->setParameter('subject', $subjectId);
            }

            // Filtre par groupe uniquement pour délégués ou admins
            if ($this->isGranted('ROLE_DELEGATE') || $this->isGranted('ROLE_ADMIN')) {
                if ($groupId) {
                    $queryBuilder->andWhere('g.id = :group')
                        ->setParameter('group', $groupId);
                }
            }

            // Pour les élèves, limiter aux groupes de l'utilisateur sans filtre manuel
            if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_DELEGATE')) {
                $userGroups = $user->getGroups();
                if (!$userGroups || $userGroups->isEmpty()) {
                    $assignments = [];
                } else {
                    $queryBuilder->andWhere('g IN (:userGroups)')
                        ->setParameter('userGroups', $userGroups);
                    $assignments = $queryBuilder->orderBy('a.due_date', 'DESC')
                        ->getQuery()
                        ->getResult();
                }
            } else {
                // Pour délégués et admins, tous les devoirs ou filtrés par groupe
                $assignments = $queryBuilder->orderBy('a.due_date', 'DESC')
                    ->getQuery()
                    ->getResult();
            }

            $subjects = $entityManager->getRepository('App\Entity\Subject')->findAll();
            $groups = $this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_DELEGATE')
                ? $entityManager->getRepository('App\Entity\Group')->findAll()
                : $user->getGroups();

            return $this->render('home/assignments_history.html.twig', [
                'assignments' => $assignments ?? [],
                'subjects' => $subjects ?? [],
                'groups' => $groups ?? [],
                'current_subject' => $subjectId,
                'current_group' => $groupId,
                'is_delegate_or_admin' => $this->isGranted('ROLE_DELEGATE') || $this->isGranted('ROLE_ADMIN'),
            ]);
        } catch (\Exception $e) {
            dump($e->getMessage());
            throw $this->createNotFoundException('Erreur lors du chargement de l\'historique : ' . $e->getMessage());
        }
    }
}
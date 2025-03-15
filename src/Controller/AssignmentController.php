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

        // Vérifie que l'utilisateur est bien le créateur ou un admin (optionnel, selon tes besoins)
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

        // Vérifie que l'utilisateur est bien le créateur ou un admin (optionnel)
        if ($assignment->getCreatedBy() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à supprimer ce devoir.');
        }

        // Vérification CSRF pour plus de sécurité
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
    public function manageAssignments(EntityManagerInterface $entityManager): Response
    {
        // Récupérer tous les devoirs, triés par date de rendu
        $assignments = $entityManager->getRepository(Assignment::class)
            ->createQueryBuilder('a')
            ->orderBy('a.due_date', 'ASC') // Changé de dueDate à due_date
            ->getQuery()
            ->getResult();

        return $this->render('assignment/manage.html.twig', [
            'assignments' => $assignments,
        ]);
    }
}
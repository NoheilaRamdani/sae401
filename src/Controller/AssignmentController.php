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
            $assignment->setCreatedAt(new \DateTime()); // Compatible avec ton entité
            $assignment->setUpdatedAt(new \DateTime());

            // Gérer le champ JSON dueDates si fourni
            $dueDates = $form->get('dueDates')->getData();
            if ($dueDates) {
                $assignment->setDueDates(json_decode($dueDates, true));
            }

            $entityManager->persist($assignment);
            $entityManager->flush();

            $this->addFlash('success', 'Le devoir a été ajouté avec succès !');
            return $this->redirectToRoute('app_home');
        }

        return $this->render('assignment/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
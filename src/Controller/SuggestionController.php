<?php
namespace App\Controller;

use App\Entity\Suggestion;
use App\Entity\Assignment;
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

            $suggestion = new Suggestion();
            $suggestion->setAssignment($assignment);
            $suggestion->setSuggestedBy($user);
            $suggestion->setMessage($data['message'] ?? ''); // Message facultatif

            $proposedChanges = [];
            if ($data['title'] !== $assignment->getTitle()) {
                $proposedChanges['title'] = $data['title'];
            }
            if ($data['description'] !== $assignment->getDescription()) {
                $proposedChanges['description'] = $data['description'];
            }
            if ($data['due_date'] != $assignment->getDueDate()) {
                $proposedChanges['due_date'] = $data['due_date']->format('Y-m-d H:i:s');
            }
            if ($data['submission_type'] !== $assignment->getSubmissionType()) {
                $proposedChanges['submission_type'] = $data['submission_type'];
            }
            if ($data['submission_url'] !== $assignment->getSubmissionUrl()) {
                $proposedChanges['submission_url'] = $data['submission_url'];
            }
            if ($data['type'] !== $assignment->getType()) {
                $proposedChanges['type'] = $data['type'];
            }
            $suggestion->setProposedChanges($proposedChanges);

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

    #[Route('/suggestions/manage', name: 'manage_suggestions', methods: ['GET'])]
    #[IsGranted('ROLE_DELEGATE')]
    public function manageSuggestions(EntityManagerInterface $entityManager): Response
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
            ->getQuery()
            ->getResult();

        return $this->render('suggestion/manage.html.twig', [
            'suggestions' => $suggestions,
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
                }
            }
            $assignment->setUpdatedAt(new \DateTime());
            $suggestion->setIsProcessed(true);
            $entityManager->flush();
            $this->addFlash('success', 'La suggestion a été validée et le devoir mis à jour !');
        } else {
            $this->addFlash('error', 'Erreur de sécurité lors de la validation.');
        }

        return $this->redirectToRoute('manage_suggestions');
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

        return $this->render('suggestion/history.html.twig', [
            'suggestions' => $suggestions,
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
        ]);
    }

    #[Route('/api/assignments/{id}/suggest-modification', name: 'api_suggest_modification', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function suggestModification(int $id, Request $request, EntityManagerInterface $entityManager): JsonResponse
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

        $data = json_decode($request->getContent(), true);
        $message = $data['message'] ?? '';

        $suggestion = new Suggestion();
        $suggestion->setAssignment($assignment)
            ->setSuggestedBy($user)
            ->setMessage($message);
        $entityManager->persist($suggestion);
        $entityManager->flush();

        return $this->json(['success' => true, 'message' => 'Suggestion envoyée aux délégués']);
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
<?php

namespace App\Controller;

use App\Entity\Delegate;
use App\Entity\Group;
use App\Entity\User;
use App\Entity\UserGroup;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DelegateManagementController extends AbstractController
{
    #[Route('/delegate/manage', name: 'delegate_manage')]
    #[IsGranted('ROLE_ADMIN')]
    public function manageDelegate(EntityManagerInterface $entityManager): Response
    {
        // Récupérer tous les groupes
        $groups = $entityManager->getRepository(Group::class)->findAll();

        $groupsData = [];

        foreach ($groups as $group) {
            // Récupérer les utilisateurs du groupe
            $userGroups = $entityManager->getRepository(UserGroup::class)
                ->createQueryBuilder('ug')
                ->where('ug.group = :group')
                ->setParameter('group', $group)
                ->getQuery()
                ->getResult();

            $users = [];
            foreach ($userGroups as $userGroup) {
                $user = $userGroup->getUser();
                $isDelegateActive = $entityManager->getRepository(Delegate::class)
                    ->createQueryBuilder('d')
                    ->where('d.user = :user')
                    ->andWhere('d.group = :group')
                    ->andWhere('d.isActive = true')
                    ->setParameter('user', $user)
                    ->setParameter('group', $group)
                    ->getQuery()
                    ->getOneOrNullResult();

                $users[] = [
                    'user' => $user,
                    'isDelegate' => ($isDelegateActive !== null)
                ];
            }

            // Récupérer les délégués actifs pour ce groupe
            $activeDelegates = $entityManager->getRepository(Delegate::class)
                ->createQueryBuilder('d')
                ->where('d.group = :group')
                ->andWhere('d.isActive = true')
                ->setParameter('group', $group)
                ->getQuery()
                ->getResult();

            $groupsData[] = [
                'group' => $group,
                'users' => $users,
                'activeDelegates' => $activeDelegates
            ];
        }

        return $this->render('delegate/manage.html.twig', [
            'groupsData' => $groupsData
        ]);
    }

    #[Route('/delegate/toggle/{userId}/{groupId}', name: 'delegate_toggle')]
    #[IsGranted('ROLE_ADMIN')]
    public function toggleDelegateStatus(int $userId, int $groupId, EntityManagerInterface $entityManager): Response
    {
        $user = $entityManager->getRepository(User::class)->find($userId);
        $group = $entityManager->getRepository(Group::class)->find($groupId);

        if (!$user || !$group) {
            $this->addFlash('error', 'Utilisateur ou groupe introuvable.');
            return $this->redirectToRoute('delegate_manage');
        }

        $delegate = $entityManager->getRepository(Delegate::class)
            ->createQueryBuilder('d')
            ->where('d.user = :user')
            ->andWhere('d.group = :group')
            ->andWhere('d.isActive = true')
            ->setParameter('user', $user)
            ->setParameter('group', $group)
            ->getQuery()
            ->getOneOrNullResult();

        if ($delegate) {
            // Désactiver le délégué et retirer le rôle
            $delegate->setIsActive(false);
            $delegate->setEndDate(new \DateTime());
            $user->removeRole('ROLE_DELEGATE');
            $this->addFlash('success', sprintf('%s %s n\'est plus délégué du groupe %s.', $user->getFirstName(), $user->getLastName(), $group->getName()));
        } else {
            // Vérifier le nombre de délégués actifs
            $activeDelegatesCount = $entityManager->getRepository(Delegate::class)
                ->createQueryBuilder('d')
                ->where('d.group = :group')
                ->andWhere('d.isActive = true')
                ->setParameter('group', $group)
                ->select('COUNT(d.id)')
                ->getQuery()
                ->getSingleScalarResult();

            if ($activeDelegatesCount >= 2) {
                $this->addFlash('error', sprintf('Le groupe %s a déjà 2 délégués actifs. Veuillez en désactiver un avant d\'en ajouter un nouveau.', $group->getName()));
                return $this->redirectToRoute('delegate_manage');
            }

            // Créer un nouveau délégué et ajouter le rôle
            $newDelegate = new Delegate();
            $newDelegate->setUser($user);
            $newDelegate->setGroup($group);
            $newDelegate->setStartDate(new \DateTime());
            $newDelegate->setEndDate((new \DateTime())->modify('+6 months'));
            $newDelegate->setIsActive(true);
            $newDelegate->setCreatedAt(new \DateTime());

            $user->addRole('ROLE_DELEGATE');
            $entityManager->persist($newDelegate);
            $this->addFlash('success', sprintf('%s %s est maintenant délégué du groupe %s.', $user->getFirstName(), $user->getLastName(), $group->getName()));
        }

        $entityManager->flush();
        return $this->redirectToRoute('delegate_manage');
    }
}
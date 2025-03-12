<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Group;
use App\Form\RegistrationFormType;
use App\Security\AppAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;


class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        Security $security,
        EntityManagerInterface $entityManager
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Hash le mot de passe
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            // Récupérer le TP choisi
            /** @var \App\Entity\Group $group */
            $group = $form->get('group')->getData();
            if ($group) {
                // Persister l'utilisateur d'abord pour obtenir son ID
                $entityManager->persist($user);
                $entityManager->flush();

                // Créer une requête SQL directe
                $conn = $entityManager->getConnection();
                $conn->executeStatement(
                    'INSERT INTO user_group (user_id, group_id) VALUES (?, ?)',
                    [$user->getId(), $group->getId()]
                );
            }

            // Persister l'utilisateur
            $entityManager->persist($user);
            $entityManager->flush();

            // Authentification automatique après inscription
            return $security->login($user, AppAuthenticator::class, 'main');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }
}
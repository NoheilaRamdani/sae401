<?php

namespace App\Service;

use App\Entity\Assignment;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

class NotificationService
{
    private $mailer;
    private $entityManager;

    public function __construct(MailerInterface $mailer, EntityManagerInterface $entityManager)
    {
        $this->mailer = $mailer;
        $this->entityManager = $entityManager;
    }

    public function sendAssignmentNotification(Assignment $assignment): void
    {
        // Récupérer les groupes associés au devoir
        $groups = $assignment->getGroups();

        // Récupérer tous les utilisateurs des groupes
        $users = [];
        foreach ($groups as $group) {
            foreach ($group->getUsers() as $user) {
                // Envoyer uniquement aux utilisateurs avec ROLE_USER
                if (in_array('ROLE_USER', $user->getRoles()) && !in_array($user->getId(), array_column($users, 'id'))) {
                    $users[] = $user;
                }
            }
        }

        // Créer et envoyer un email pour chaque utilisateur
        foreach ($users as $user) {
            $email = (new TemplatedEmail())
                ->from('no-reply@mmi-agenda.com')
                ->to($user->getEmail())
                ->subject('Nouveau devoir ajouté : ' . $assignment->getTitle())
                ->htmlTemplate('emails/assignment_notification.html.twig')
                ->context([
                    'assignment' => $assignment,
                    'user' => $user,
                ]);

            $this->mailer->send($email);
        }
    }
}
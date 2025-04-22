<?php
namespace App\Service;

use App\Entity\Assignment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class NotificationService
{
    private $entityManager;
    private $mailer;

    public function __construct(EntityManagerInterface $entityManager, MailerInterface $mailer)
    {
        $this->entityManager = $entityManager;
        $this->mailer = $mailer;
    }

    public function sendAssignmentNotification(Assignment $assignment): void
    {
        $groups = $assignment->getGroups();
        $users = [];
        foreach ($groups as $group) {
            // Requête modifiée pour utiliser UserGroup
            $groupUsers = $this->entityManager->createQueryBuilder()
                ->select('u')
                ->from('App\Entity\User', 'u')
                ->innerJoin('App\Entity\UserGroup', 'ug', 'WITH', 'ug.user = u')
                ->where('ug.group = :groupId')
                ->setParameter('groupId', $group->getId())
                ->getQuery()
                ->getResult();
            $users = array_merge($users, $groupUsers);
        }
        $users = array_unique($users, SORT_REGULAR);

        foreach ($users as $user) {
            $description = $assignment->getDescription() ?: 'Aucune description';
            $subjectName = $assignment->getSubject() ? $assignment->getSubject()->getName() : 'Non défini'; // Ajout de la matière
            $email = (new Email())
                ->from('admin@mmiple.fr')
                ->to($user->getEmail())
                ->subject('Nouveau devoir ajouté : ' . $assignment->getTitle())
                ->text(
                    "Bonjour {$user->getFirstName()},\n\n" .
                    "Un nouveau devoir a été ajouté :\n" .
                    "Titre : {$assignment->getTitle()}\n" .
                    "Matière : {$subjectName}\n" . // Ajout de la matière
                    "Description : {$description}\n" .
                    "Date limite : {$assignment->getDueDate()->format('d/m/Y H:i')}\n" .
                    "Type : {$assignment->getType()}\n\n" .
                    "Consultez les détails sur l'application."
                )
                ->html(
                    "<p>Bonjour {$user->getFirstName()},</p>" .
                    "<p>Un nouveau devoir a été ajouté :</p>" .
                    "<ul>" .
                    "<li><strong>Titre :</strong> {$assignment->getTitle()}</li>" .
                    "<li><strong>Matière :</strong> {$subjectName}</li>" . // Ajout de la matière
                    "<li><strong>Description :</strong> {$description}</li>" .
                    "<li><strong>Date limite :</strong> {$assignment->getDueDate()->format('d/m/Y H:i')}</li>" .
                    "<li><strong>Type :</strong> {$assignment->getType()}</li>" .
                    "</ul>" .
                    "<p>Consultez les détails sur l'application.</p>"
                );

            try {
                $this->mailer->send($email);
            } catch (\Exception $e) {
                error_log("Erreur lors de l'envoi de l'email à {$user->getEmail()} : {$e->getMessage()}");
            }
        }
    }
}
<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

class MailerController extends AbstractController
{
    #[Route('/email')]
    public function sendEmail(MailerInterface $mailer): Response
    {
        $sender = 'admin@mmiple.fr';
        $recipient = 'contact@mmiple.fr';
        $sujet = 'Message du test';

        $email = (new Email())
            ->from($sender)
            ->to($recipient)
            ->subject($sujet)
            ->text('Envoi facile des mails avec Symfony')
            ->html('<p>Envoi facile des mails avec Symfony</p>');

        $mailer->send($email);
        return new Response('Envoi Réussi ? Vérifiez avec l\'URL localhost:1080');
    }
}
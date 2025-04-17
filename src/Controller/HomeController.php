<?php
namespace App\Controller;

use App\Entity\User;
use App\Entity\Assignment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté.');
        }

        $typeFilter = $request->query->get('type');

        $queryBuilder = $entityManager->getRepository(Assignment::class)
            ->createQueryBuilder('a')
            ->where('a.due_date >= :now')
            ->setParameter('now', new \DateTime())
            ->orderBy('a.due_date', 'ASC');

        if ($this->isGranted('ROLE_ADMIN')) {
            if ($typeFilter) {
                $queryBuilder->andWhere('a.type = :type')->setParameter('type', $typeFilter);
            }
        } else {
            $groups = $user->getGroups();
            $queryBuilder
                ->join('a.groups', 'g')
                ->andWhere('g IN (:groups)')
                ->setParameter('groups', $groups);
            if ($typeFilter) {
                $queryBuilder->andWhere('a.type = :type')->setParameter('type', $typeFilter);
            }
        }

        $assignments = $queryBuilder->getQuery()->getResult();

        $now = new \DateTime();
        $assignmentsData = [];
        $notifications = [];
        foreach ($assignments as $assignment) {
            $dueDate = $assignment->getDueDate();
            $hoursUntilDue = (int) (($dueDate->getTimestamp() - $now->getTimestamp()) / 3600); // Calcul en heures
            $daysUntilDue = (int) ($hoursUntilDue / 24); // Pour l'affichage en jours
            $color = $assignment->getSubject()->getColor() ?? '#3788d8';

            $assignmentsData[] = [
                'assignment' => $assignment,
                'daysUntilDue' => $daysUntilDue, // Pour l'affichage
                'hoursUntilDue' => $hoursUntilDue, // Pour la logique des couleurs et affichage précis
                'color' => $color,
                'isCompleted' => $assignment->isCompleted(),
                'subjectCode' => $assignment->getSubject()->getCode(),
                'urgencyClass' => $this->getUrgencyClass($hoursUntilDue, $dueDate < $now),
            ];

            if (!$assignment->isCompleted() && ($hoursUntilDue === 24 || $hoursUntilDue === 72)) {
                $notifications[] = [
                    'title' => $assignment->getTitle(),
                    'hoursUntilDue' => $hoursUntilDue,
                ];
            }
        }

        $suggestions = [];
        if ($this->isGranted('ROLE_DELEGATE')) {
            $suggestions = $entityManager->getRepository(\App\Entity\Suggestion::class)
                ->createQueryBuilder('s')
                ->where('s.isProcessed = :isProcessed')
                ->setParameter('isProcessed', false)
                ->orderBy('s.createdAt', 'DESC')
                ->setMaxResults(5)
                ->getQuery()
                ->getResult();
        }

        return $this->render('home/index.html.twig', [
            'user' => $user,
            'groups' => $user->getGroups(),
            'assignments_data' => $assignmentsData,
            'type_filter' => $typeFilter,
            'notifications' => $notifications,
            'suggestions' => $suggestions,
        ]);
    }

    private function getUrgencyClass(int $hoursUntilDue, bool $isExpired): string
    {
        if ($isExpired) {
            return 'expired';
        } elseif ($hoursUntilDue < 24) {
            return 'urgent'; // Moins de 24 heures
        } elseif ($hoursUntilDue <= 72) {
            return 'soon'; // Entre 24 et 72 heures
        }
        return 'ontime'; // Plus de 72 heures
    }

    #[Route('/cal.ics', name: 'ical_user_feed', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function generateUserICalFeed(EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté.');
        }

        $queryBuilder = $entityManager->getRepository(Assignment::class)
            ->createQueryBuilder('a')
            ->where('a.due_date >= :now')
            ->setParameter('now', new \DateTime())
            ->orderBy('a.due_date', 'ASC');

        if (!$this->isGranted('ROLE_ADMIN')) {
            $groups = $user->getGroups();
            $queryBuilder
                ->join('a.groups', 'g')
                ->andWhere('g IN (:groups)')
                ->setParameter('groups', $groups);
        }

        $assignments = $queryBuilder->getQuery()->getResult();

        $ical = "BEGIN:VCALENDAR\r\n";
        $ical .= "VERSION:2.0\r\n";
        $ical .= "PRODID:-//TonApp//StudentAssignments//EN\r\n";
        $ical .= "CALSCALE:GREGORIAN\r\n";
        $ical .= "METHOD:PUBLISH\r\n";
        $ical .= "X-WR-CALNAME:Rendus de " . $user->getUserIdentifier() . "\r\n";

        foreach ($assignments as $assignment) {
            $dueDate = $assignment->getDueDate();
            $ical .= "BEGIN:VEVENT\r\n";
            $ical .= "UID:" . $assignment->getId() . "@tonapp.com\r\n";
            $ical .= "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
            $ical .= "DTSTART:" . $dueDate->format('Ymd\THis') . "\r\n";
            $ical .= "DTEND:" . $dueDate->modify('+1 hour')->format('Ymd\THis') . "\r\n";
            $title = $assignment->getTitle();
            if ($assignment->isCompleted()) {
                $title = "~~" . $title . "~~";
            }
            $ical .= "SUMMARY:" . $title . "\r\n";
            $ical .= "DESCRIPTION:" . ($assignment->getDescription() ?? 'Pas de description') . "\r\n";
            $ical .= "LOCATION:" . ($assignment->getSubmissionUrl() ?? 'Non spécifié') . "\r\n";
            $ical .= "END:VEVENT\r\n";
        }

        $ical .= "END:VCALENDAR\r\n";

        return new Response($ical, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="cal.ics"',
        ]);
    }

    #[Route('/assignments/history', name: 'app_assignments_history')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function assignmentsHistory(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté.');
        }

        $queryBuilder = $entityManager->getRepository(Assignment::class)
            ->createQueryBuilder('a')
            ->leftJoin('a.groups', 'g')
            ->orderBy('a.due_date', 'DESC');

        $subjectId = $request->query->get('subject');
        $groupId = $request->query->get('group');

        if ($subjectId) {
            $queryBuilder->andWhere('a.subject = :subject')
                ->setParameter('subject', $subjectId);
        }

        if (!$this->isGranted('ROLE_ADMIN')) {
            $groups = $user->getGroups();
            if ($groups->count() > 0) {
                $queryBuilder
                    ->andWhere('g IN (:groups)')
                    ->setParameter('groups', $groups);
            }
            if ($groupId) {
                $queryBuilder->andWhere('g.id = :group')
                    ->setParameter('group', $groupId);
            }
        } else {
            if ($groupId) {
                $queryBuilder->andWhere('g.id = :group')
                    ->setParameter('group', $groupId);
            }
        }

        $assignments = $queryBuilder->getQuery()->getResult();

        $subjects = [];
        $groups = [];
        foreach ($assignments as $assignment) {
            $subject = $assignment->getSubject();
            if ($subject && !in_array($subject, $subjects, true)) {
                $subjects[] = $subject;
            }
            foreach ($assignment->getGroups() as $group) {
                if (!in_array($group, $groups, true)) {
                    $groups[] = $group;
                }
            }
        }

        $isDelegateOrAdmin = $this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_DELEGATE');

        return $this->render('assignment/assignments_history.html.twig', [
            'user' => $user,
            'assignments' => $assignments,
            'subjects' => $subjects,
            'groups' => $isDelegateOrAdmin ? $groups : [],
            'is_delegate_or_admin' => $isDelegateOrAdmin,
            'current_subject' => $subjectId,
            'current_group' => $groupId,
        ]);
    }
}
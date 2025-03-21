<?php
namespace App\Twig;

use App\Entity\Suggestion;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AppExtension extends AbstractExtension
{
private EntityManagerInterface $em;

public function __construct(EntityManagerInterface $em)
{
$this->em = $em;
}

public function getFilters(): array
{
return [
new TwigFilter('suggestionsPending', [$this, 'getSuggestionsPending']),
new TwigFilter('suggestionsProcessed', [$this, 'getSuggestionsProcessed']),
];
}

public function getSuggestionsPending(int $assignmentId): array
{
return $this->em->getRepository(Suggestion::class)
->findBy(['assignment' => $assignmentId, 'isProcessed' => false]);
}

public function getSuggestionsProcessed(int $assignmentId): array
{
return $this->em->getRepository(Suggestion::class)
->findBy(['assignment' => $assignmentId, 'isProcessed' => true]);
}
}
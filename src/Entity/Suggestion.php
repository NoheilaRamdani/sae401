<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Suggestion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Assignment::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Assignment $assignment;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $suggestedBy;

    #[ORM\Column(type: 'json', nullable: true)] // Permet NULL dans la base
    private ?array $proposedChanges = []; // Type union : peut être un tableau ou null

    #[ORM\Column(type: 'text')]
    private string $message;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isProcessed = false;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAssignment(): Assignment
    {
        return $this->assignment;
    }

    public function setAssignment(Assignment $assignment): self
    {
        $this->assignment = $assignment;
        return $this;
    }

    public function getSuggestedBy(): User
    {
        return $this->suggestedBy;
    }

    public function setSuggestedBy(User $suggestedBy): self
    {
        $this->suggestedBy = $suggestedBy;
        return $this;
    }

    public function getProposedChanges(): array
    {
        return $this->proposedChanges ?? []; // Retourne un tableau vide si null
    }

    public function setProposedChanges(?array $proposedChanges): self
    {
        $this->proposedChanges = $proposedChanges; // Accepte null ou un tableau
        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function isProcessed(): bool
    {
        return $this->isProcessed;
    }

    public function setIsProcessed(bool $isProcessed): self
    {
        $this->isProcessed = $isProcessed;
        return $this;
    }
}
<?php

namespace App\Entity;

use App\Repository\AssignmentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AssignmentRepository::class)]
class Assignment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dueDate = null;

    #[ORM\ManyToOne(targetEntity: Subject::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Subject $subject = null;

    #[ORM\ManyToOne(targetEntity: Group::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Group $assignmentGroup = null;

//    #[ORM\ManyToOne(targetEntity: User::class)]
//    #[ORM\JoinColumn(nullable: false)]
//    private ?User $createdBy = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $submissionType = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $submissionUrl = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private $type; // Par exemple : 'devoir', 'examen', 'oral'

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getDueDate(): ?\DateTimeInterface
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTimeInterface $dueDate): static
    {
        $this->dueDate = $dueDate;
        return $this;
    }

    public function getSubject(): ?Subject
    {
        return $this->subject;
    }

    public function setSubject(?Subject $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    public function getAssignmentGroup(): ?Group
    {
        return $this->assignmentGroup;
    }

    public function setAssignmentGroup(?Group $assignmentGroup): static
    {
        $this->assignmentGroup = $assignmentGroup;
        return $this;
    }

//    public function getCreatedBy(): ?User
//    {
//        return $this->createdBy;
//    }

//    public function setCreatedBy(?User $createdBy): static
//    {
//        $this->createdBy = $createdBy;
//        return $this;
//    }

    public function getSubmissionType(): ?string
    {
        return $this->submissionType;
    }

    public function setSubmissionType(?string $submissionType): static
    {
        $this->submissionType = $submissionType;
        return $this;
    }

    public function getSubmissionUrl(): ?string
    {
        return $this->submissionUrl;
    }

    public function setSubmissionUrl(?string $submissionUrl): static
    {
        $this->submissionUrl = $submissionUrl;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
    public function getType(): ?string
    {
        return $this->type;
    }
    public function setType(?string $type): static
    {
        $this->type = $type;
        return $this;
    }
}


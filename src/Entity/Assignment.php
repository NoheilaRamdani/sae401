<?php

namespace App\Entity;

use App\Repository\AssignmentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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
    private ?\DateTimeInterface $due_date = null; // Changé de dueDate à due_date

    #[ORM\ManyToOne(targetEntity: Subject::class)]
    #[ORM\JoinColumn(name: 'subject_id', nullable: false)] // Explicitement nommé subject_id
    private ?Subject $subject = null;

    #[ORM\ManyToMany(targetEntity: Group::class, inversedBy: 'assignments')]
    #[ORM\JoinTable(name: 'assignment_group')]
    private Collection $groups;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', nullable: true)] // Explicitement nommé created_by_id
    private ?User $created_by = null; // Changé de createdBy à created_by

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $submission_type = null; // Changé de submissionType à submission_type

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $submission_url = null; // Changé de submissionUrl à submission_url

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $created_at = null; // Changé de createdAt à created_at

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updated_at = null; // Changé de updatedAt à updated_at

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $type = null;

    public function __construct()
    {
        $this->created_at = new \DateTime();
        $this->groups = new ArrayCollection();
    }

    // Getters et Setters adaptés
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
        return $this->due_date;
    }

    public function setDueDate(?\DateTimeInterface $due_date): static
    {
        $this->due_date = $due_date;
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

    public function getGroups(): Collection
    {
        return $this->groups;
    }

    public function addGroup(Group $group): static
    {
        if (!$this->groups->contains($group)) {
            $this->groups->add($group);
        }
        return $this;
    }

    public function removeGroup(Group $group): static
    {
        $this->groups->removeElement($group);
        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->created_by;
    }

    public function setCreatedBy(?User $created_by): static
    {
        $this->created_by = $created_by;
        return $this;
    }

    public function getSubmissionType(): ?string
    {
        return $this->submission_type;
    }

    public function setSubmissionType(?string $submission_type): static
    {
        $this->submission_type = $submission_type;
        return $this;
    }

    public function getSubmissionUrl(): ?string
    {
        return $this->submission_url;
    }

    public function setSubmissionUrl(?string $submission_url): static
    {
        $this->submission_url = $submission_url;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeInterface $created_at): static
    {
        $this->created_at = $created_at;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(?\DateTimeInterface $updated_at): static
    {
        $this->updated_at = $updated_at;
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
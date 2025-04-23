<?php
namespace App\Entity;

use App\Repository\AssignmentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AssignmentRepository::class)]
class Assignment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le titre du devoir est requis.")]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'datetime')]
    #[Assert\NotBlank(message: "La date limite est requise.")]
    private ?\DateTimeInterface $due_date = null;

    #[ORM\ManyToOne(targetEntity: Subject::class)]
    #[ORM\JoinColumn(name: 'subject_id', nullable: false)]
    #[Assert\NotBlank(message: "La matière est requise.")]
    private ?Subject $subject = null;

    #[ORM\ManyToMany(targetEntity: Group::class, inversedBy: 'assignments')]
    #[ORM\JoinTable(name: 'assignment_group')]
    #[Assert\NotBlank(message: "Au moins un groupe doit être sélectionné.")]
    #[Assert\Count(min: 1, minMessage: "Vous devez sélectionner au moins un groupe.")]
    private Collection $groups;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', nullable: true)]
    private ?User $created_by = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $submission_url = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $submission_other = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $course_location = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updated_at = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: "Le type de devoir est requis.")]
    #[Assert\Choice(choices: ['examen', 'oral', 'devoir'], message: "Le type doit être 'examen', 'oral' ou 'devoir'.")]
    private ?string $type = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Choice(choices: ['email', 'moodle', 'vps', 'Autre'], message: "Le mode de rendu doit être 'email', 'moodle', 'vps' ou 'Autre'.")]
    private ?string $submission_type = null;

    #[ORM\Column(type: 'boolean', nullable: false, options: ['default' => false])]
    private bool $is_completed = false;

    #[ORM\OneToMany(targetEntity: Suggestion::class, mappedBy: 'assignment')]
    private Collection $suggestions;

    public function __construct()
    {
        $this->created_at = new \DateTime();
        $this->groups = new ArrayCollection();
        $this->suggestions = new ArrayCollection();
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

    public function getSubmissionUrl(): ?string
    {
        return $this->submission_url;
    }

    public function setSubmissionUrl(?string $submission_url): static
    {
        $this->submission_url = $submission_url;
        return $this;
    }

    public function getSubmissionOther(): ?string
    {
        return $this->submission_other;
    }

    public function setSubmissionOther(?string $submission_other): static
    {
        $this->submission_other = $submission_other;
        return $this;
    }

    public function getCourseLocation(): ?string
    {
        return $this->course_location;
    }

    public function setCourseLocation(?string $course_location): static
    {
        $this->course_location = $course_location;
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

    public function getSubmissionType(): ?string
    {
        return $this->submission_type;
    }

    public function setSubmissionType(?string $submission_type): static
    {
        $this->submission_type = $submission_type;
        return $this;
    }

    public function isCompleted(): bool
    {
        return $this->is_completed;
    }

    public function setIsCompleted(bool $is_completed): static
    {
        $this->is_completed = $is_completed;
        return $this;
    }

    /**
     * @return Collection<int, Suggestion>
     */
    public function getSuggestions(): Collection
    {
        return $this->suggestions;
    }

    public function addSuggestion(Suggestion $suggestion): static
    {
        if (!$this->suggestions->contains($suggestion)) {
            $this->suggestions->add($suggestion);
            $suggestion->setAssignment($this);
        }
        return $this;
    }

    public function removeSuggestion(Suggestion $suggestion): static
    {
        if ($this->suggestions->removeElement($suggestion)) {
            if ($suggestion->getAssignment() === $this) {
                $suggestion->setAssignment(null);
            }
        }
        return $this;
    }
}
<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TravelRequestRepository;
use Doctrine\ORM\Mapping as ORM;
use Sulu\Bundle\ContactBundle\Entity\Contact;

#[ORM\Entity(repositoryClass: TravelRequestRepository::class)]
#[ORM\HasLifecycleCallbacks]
class TravelRequest
{
    public const STATUS_NEW = 'new';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_NEEDS_INFO = 'needs_info';
    public const STATUS_PLAN_IN_PROGRESS = 'plan_in_progress';
    public const STATUS_PROPOSAL_READY = 'proposal_ready';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Contact::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Contact $contact;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $summary = null;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'json')]
    private array $formData = [];

    #[ORM\Column(options: ['default' => false])]
    private bool $contactDataConflict = false;

    #[ORM\Column(length: 30, options: ['default' => self::STATUS_NEW])]
    private string $status = self::STATUS_NEW;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $internalNotes = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTime $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContact(): Contact
    {
        return $this->contact;
    }

    public function setContact(Contact $contact): self
    {
        $this->contact = $contact;

        return $this;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(?string $summary): self
    {
        $this->summary = $summary;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getFormData(): array
    {
        return $this->formData;
    }

    /**
     * @param array<string, mixed> $formData
     */
    public function setFormData(array $formData): self
    {
        $this->formData = $formData;

        return $this;
    }

    public function hasContactDataConflict(): bool
    {
        return $this->contactDataConflict;
    }

    public function setContactDataConflict(bool $contactDataConflict): self
    {
        $this->contactDataConflict = $contactDataConflict;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getInternalNotes(): ?string
    {
        return $this->internalNotes;
    }

    public function setInternalNotes(?string $internalNotes): self
    {
        $this->internalNotes = $internalNotes;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTime();
    }
}

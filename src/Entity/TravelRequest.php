<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
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

    #[ORM\ManyToOne(inversedBy: 'travelRequests')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Contact $contact = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $destination = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $region = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $duration = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $travelType = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $departureDate = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $returnDate = null;

    #[ORM\Column(nullable: true)]
    private ?int $numberOfTravelers = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $budgetIndication = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $interests = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $atmosphere = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $accommodationPreference = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $transportPreference = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $additionalNotes = null;

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

    public function getContact(): ?Contact
    {
        return $this->contact;
    }

    public function setContact(?Contact $contact): self
    {
        $this->contact = $contact;

        return $this;
    }

    public function getDestination(): ?string
    {
        return $this->destination;
    }

    public function setDestination(?string $destination): self
    {
        $this->destination = $destination;

        return $this;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function setRegion(?string $region): self
    {
        $this->region = $region;

        return $this;
    }

    public function getDuration(): ?string
    {
        return $this->duration;
    }

    public function setDuration(?string $duration): self
    {
        $this->duration = $duration;

        return $this;
    }

    public function getTravelType(): ?string
    {
        return $this->travelType;
    }

    public function setTravelType(?string $travelType): self
    {
        $this->travelType = $travelType;

        return $this;
    }

    public function getDepartureDate(): ?string
    {
        return $this->departureDate;
    }

    public function setDepartureDate(?string $departureDate): self
    {
        $this->departureDate = $departureDate;

        return $this;
    }

    public function getReturnDate(): ?string
    {
        return $this->returnDate;
    }

    public function setReturnDate(?string $returnDate): self
    {
        $this->returnDate = $returnDate;

        return $this;
    }

    public function getNumberOfTravelers(): ?int
    {
        return $this->numberOfTravelers;
    }

    public function setNumberOfTravelers(?int $numberOfTravelers): self
    {
        $this->numberOfTravelers = $numberOfTravelers;

        return $this;
    }

    public function getBudgetIndication(): ?string
    {
        return $this->budgetIndication;
    }

    public function setBudgetIndication(?string $budgetIndication): self
    {
        $this->budgetIndication = $budgetIndication;

        return $this;
    }

    public function getInterests(): ?string
    {
        return $this->interests;
    }

    public function setInterests(?string $interests): self
    {
        $this->interests = $interests;

        return $this;
    }

    public function getAtmosphere(): ?string
    {
        return $this->atmosphere;
    }

    public function setAtmosphere(?string $atmosphere): self
    {
        $this->atmosphere = $atmosphere;

        return $this;
    }

    public function getAccommodationPreference(): ?string
    {
        return $this->accommodationPreference;
    }

    public function setAccommodationPreference(?string $accommodationPreference): self
    {
        $this->accommodationPreference = $accommodationPreference;

        return $this;
    }

    public function getTransportPreference(): ?string
    {
        return $this->transportPreference;
    }

    public function setTransportPreference(?string $transportPreference): self
    {
        $this->transportPreference = $transportPreference;

        return $this;
    }

    public function getAdditionalNotes(): ?string
    {
        return $this->additionalNotes;
    }

    public function setAdditionalNotes(?string $additionalNotes): self
    {
        $this->additionalNotes = $additionalNotes;

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

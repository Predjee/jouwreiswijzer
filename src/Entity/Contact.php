<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class Contact
{
    public const STATUS_LEAD = 'lead';
    public const STATUS_IN_GESPREK = 'in_gesprek';
    public const STATUS_ACTIEVE_KLANT = 'actieve_klant';
    public const STATUS_TERUGKERENDE_KLANT = 'terugkerende_klant';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 26, unique: true)]
    private string $ulid;

    #[ORM\Column(length: 100)]
    private string $firstName;

    #[ORM\Column(length: 100)]
    private string $lastName;

    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 30, options: ['default' => self::STATUS_LEAD])]
    private string $status = self::STATUS_LEAD;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTime $updatedAt;

    /** @var Collection<int, TravelRequest> */
    #[ORM\OneToMany(mappedBy: 'contact', targetEntity: TravelRequest::class, cascade: ['persist', 'remove'])]
    private Collection $travelRequests;

    public function __construct()
    {
        $this->ulid = (string) new Ulid();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTime();
        $this->travelRequests = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUlid(): string
    {
        return $this->ulid;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;

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

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;

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

    /** @return Collection<int, TravelRequest> */
    public function getTravelRequests(): Collection
    {
        return $this->travelRequests;
    }

    public function addTravelRequest(TravelRequest $travelRequest): self
    {
        if (!$this->travelRequests->contains($travelRequest)) {
            $this->travelRequests->add($travelRequest);
            $travelRequest->setContact($this);
        }

        return $this;
    }

    public function removeTravelRequest(TravelRequest $travelRequest): self
    {
        if ($this->travelRequests->removeElement($travelRequest) && $travelRequest->getContact() === $this) {
            $travelRequest->setContact(null);
        }

        return $this;
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTime();
    }
}

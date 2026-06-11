<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class TravelDay
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'days')]
    #[ORM\JoinColumn(nullable: false)]
    private ?TravelPlan $travelPlan = null;

    #[ORM\Column]
    private int $dayNumber;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $introduction = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTime $updatedAt;

    /** @var Collection<int, TravelDayPart> */
    #[ORM\OneToMany(mappedBy: 'travelDay', targetEntity: TravelDayPart::class, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $parts;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTime();
        $this->parts = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTravelPlan(): ?TravelPlan
    {
        return $this->travelPlan;
    }

    public function setTravelPlan(?TravelPlan $travelPlan): self
    {
        $this->travelPlan = $travelPlan;

        return $this;
    }

    public function getDayNumber(): int
    {
        return $this->dayNumber;
    }

    public function setDayNumber(int $dayNumber): self
    {
        $this->dayNumber = $dayNumber;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getIntroduction(): ?string
    {
        return $this->introduction;
    }

    public function setIntroduction(?string $introduction): self
    {
        $this->introduction = $introduction;

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

    /** @return Collection<int, TravelDayPart> */
    public function getParts(): Collection
    {
        return $this->parts;
    }

    public function addPart(TravelDayPart $part): self
    {
        if (!$this->parts->contains($part)) {
            $this->parts->add($part);
            $part->setTravelDay($this);
        }

        return $this;
    }

    public function removePart(TravelDayPart $part): self
    {
        if ($this->parts->removeElement($part) && $part->getTravelDay() === $this) {
            $part->setTravelDay(null);
        }

        return $this;
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTime();
    }
}

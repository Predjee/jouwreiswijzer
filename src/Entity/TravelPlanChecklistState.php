<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TravelPlanChecklistStateRepository;
use Doctrine\ORM\Mapping as ORM;
use Sulu\Bundle\ContactBundle\Entity\Contact;

#[ORM\Entity(repositoryClass: TravelPlanChecklistStateRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_travel_plan_checklist_state_item', columns: ['contact_id', 'travel_plan_id', 'item_key'])]
class TravelPlanChecklistState
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Contact::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Contact $contact;

    #[ORM\ManyToOne(targetEntity: TravelPlan::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private TravelPlan $travelPlan;

    #[ORM\Column(length: 190)]
    private string $itemKey;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $checkedAt = null;

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

    public function getTravelPlan(): TravelPlan
    {
        return $this->travelPlan;
    }

    public function setTravelPlan(TravelPlan $travelPlan): self
    {
        $this->travelPlan = $travelPlan;

        return $this;
    }

    public function getItemKey(): string
    {
        return $this->itemKey;
    }

    public function setItemKey(string $itemKey): self
    {
        $this->itemKey = $itemKey;

        return $this;
    }

    public function getCheckedAt(): ?\DateTimeImmutable
    {
        return $this->checkedAt;
    }

    public function setCheckedAt(?\DateTimeImmutable $checkedAt): self
    {
        $this->checkedAt = $checkedAt;

        return $this;
    }

    public function isChecked(): bool
    {
        return null !== $this->checkedAt;
    }
}

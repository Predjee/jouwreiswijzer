<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TravelPlanFeedbackRepository;
use Doctrine\ORM\Mapping as ORM;
use Sulu\Bundle\ContactBundle\Entity\Contact;

#[ORM\Entity(repositoryClass: TravelPlanFeedbackRepository::class)]
class TravelPlanFeedback
{
    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESOLVED = 'resolved';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: TravelPlan::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private TravelPlan $travelPlan;

    #[ORM\ManyToOne(targetEntity: Contact::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Contact $contact;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $blockPath = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $blockType = null;

    #[ORM\Column(type: 'text')]
    private string $message;

    #[ORM\Column(length: 30, options: ['default' => self::STATUS_OPEN])]
    private string $status = self::STATUS_OPEN;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $resolvedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $adminResolutionNote = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $resolvedContentSnapshot = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $acceptedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getContact(): Contact
    {
        return $this->contact;
    }

    public function setContact(Contact $contact): self
    {
        $this->contact = $contact;

        return $this;
    }

    public function getBlockPath(): ?string
    {
        return $this->blockPath;
    }

    public function setBlockPath(?string $blockPath): self
    {
        $this->blockPath = $blockPath;

        return $this;
    }

    public function getBlockType(): ?string
    {
        return $this->blockType;
    }

    public function setBlockType(?string $blockType): self
    {
        $this->blockType = $blockType;

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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getResolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function setResolvedAt(?\DateTimeImmutable $resolvedAt): self
    {
        $this->resolvedAt = $resolvedAt;

        return $this;
    }

    public function getAdminResolutionNote(): ?string
    {
        return $this->adminResolutionNote;
    }

    public function setAdminResolutionNote(?string $adminResolutionNote): self
    {
        $this->adminResolutionNote = $adminResolutionNote;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getResolvedContentSnapshot(): ?array
    {
        return $this->resolvedContentSnapshot;
    }

    /**
     * @param array<string, mixed>|null $resolvedContentSnapshot
     */
    public function setResolvedContentSnapshot(?array $resolvedContentSnapshot): self
    {
        $this->resolvedContentSnapshot = $resolvedContentSnapshot;

        return $this;
    }

    public function getAcceptedAt(): ?\DateTimeImmutable
    {
        return $this->acceptedAt;
    }

    public function setAcceptedAt(?\DateTimeImmutable $acceptedAt): self
    {
        $this->acceptedAt = $acceptedAt;

        return $this;
    }
}

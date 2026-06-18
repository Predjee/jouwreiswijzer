<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ScheduledPushMessageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ScheduledPushMessageRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_scheduled_push_message_source_key', columns: ['source_key'])]
class ScheduledPushMessage
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PushRule::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?PushRule $pushRule = null;

    #[ORM\ManyToOne(targetEntity: TravelPlan::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private TravelPlan $travelPlan;

    #[ORM\Column(length: 190)]
    private string $sourceKey;

    #[ORM\Column(length: 190)]
    private string $title;

    #[ORM\Column(type: 'text')]
    private string $body;

    #[ORM\Column(length: 30)]
    private string $channel;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $actionType = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $actionTarget = null;

    #[ORM\Column]
    private \DateTimeImmutable $scheduledFor;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPushRule(): ?PushRule
    {
        return $this->pushRule;
    }

    public function setPushRule(?PushRule $pushRule): self
    {
        $this->pushRule = $pushRule;

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

    public function getSourceKey(): string
    {
        return $this->sourceKey;
    }

    public function setSourceKey(string $sourceKey): self
    {
        $this->sourceKey = $sourceKey;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): self
    {
        $this->body = $body;

        return $this;
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function setChannel(string $channel): self
    {
        $this->channel = $channel;

        return $this;
    }

    public function getActionType(): ?string
    {
        return $this->actionType;
    }

    public function setActionType(?string $actionType): self
    {
        $this->actionType = $actionType;

        return $this;
    }

    public function getActionTarget(): ?string
    {
        return $this->actionTarget;
    }

    public function setActionTarget(?string $actionTarget): self
    {
        $this->actionTarget = $actionTarget;

        return $this;
    }

    public function getScheduledFor(): \DateTimeImmutable
    {
        return $this->scheduledFor;
    }

    public function setScheduledFor(\DateTimeImmutable $scheduledFor): self
    {
        $this->scheduledFor = $scheduledFor;

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

    public function isPending(): bool
    {
        return self::STATUS_PENDING === $this->status;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTimeImmutable $sentAt): self
    {
        $this->sentAt = $sentAt;

        return $this;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function setLastError(?string $lastError): self
    {
        $this->lastError = $lastError;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function markSent(): self
    {
        $this->status = self::STATUS_SENT;
        $this->sentAt = new \DateTimeImmutable();
        $this->lastError = null;

        return $this;
    }

    public function markFailed(string $error): self
    {
        $this->status = self::STATUS_FAILED;
        $this->lastError = $error;

        return $this;
    }
}

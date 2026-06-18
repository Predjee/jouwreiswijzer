<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PushSubscriptionRepository;
use Doctrine\ORM\Mapping as ORM;
use Sulu\Bundle\ContactBundle\Entity\Contact;

#[ORM\Entity(repositoryClass: PushSubscriptionRepository::class)]
class PushSubscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Contact::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Contact $contact;

    #[ORM\Column(length: 255, unique: true)]
    private string $expoPushToken;

    #[ORM\Column(length: 20)]
    private string $platform;

    #[ORM\Column(options: ['default' => true])]
    private bool $albumReadyEnabled = true;

    #[ORM\Column(options: ['default' => true])]
    private bool $tripReminderEnabled = true;

    #[ORM\Column(options: ['default' => true])]
    private bool $generalEnabled = true;

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

    public function getContact(): Contact
    {
        return $this->contact;
    }

    public function setContact(Contact $contact): self
    {
        $this->contact = $contact;

        return $this;
    }

    public function getExpoPushToken(): string
    {
        return $this->expoPushToken;
    }

    public function setExpoPushToken(string $expoPushToken): self
    {
        $this->expoPushToken = $expoPushToken;

        return $this;
    }

    public function getPlatform(): string
    {
        return $this->platform;
    }

    public function setPlatform(string $platform): self
    {
        $this->platform = $platform;

        return $this;
    }

    public function isAlbumReadyEnabled(): bool
    {
        return $this->albumReadyEnabled;
    }

    public function setAlbumReadyEnabled(bool $albumReadyEnabled): self
    {
        $this->albumReadyEnabled = $albumReadyEnabled;

        return $this;
    }

    public function isTripReminderEnabled(): bool
    {
        return $this->tripReminderEnabled;
    }

    public function setTripReminderEnabled(bool $tripReminderEnabled): self
    {
        $this->tripReminderEnabled = $tripReminderEnabled;

        return $this;
    }

    public function isGeneralEnabled(): bool
    {
        return $this->generalEnabled;
    }

    public function setGeneralEnabled(bool $generalEnabled): self
    {
        $this->generalEnabled = $generalEnabled;

        return $this;
    }

    public function isEnabledForChannel(string $channel): bool
    {
        return match ($channel) {
            'trip_reminders' => $this->tripReminderEnabled,
            'trip_plan_ready' => $this->albumReadyEnabled,
            'album_ready' => $this->albumReadyEnabled,
            'general' => $this->generalEnabled,
            default => true,
        };
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}

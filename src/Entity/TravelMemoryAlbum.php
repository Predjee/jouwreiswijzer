<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TravelMemoryAlbumRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TravelMemoryAlbumRepository::class)]
class TravelMemoryAlbum
{
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_READY = 'ready';
    public const STATUS_FAILED = 'failed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: TravelPlan::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private TravelPlan $travelPlan;

    #[ORM\Column(nullable: true)]
    private ?int $mediaId = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $photoCount = 0;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $generatedAt = null;

    #[ORM\Column(length: 20)]
    private string $status;

    public function __construct()
    {
        $this->status = self::STATUS_PROCESSING;
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

    public function getMediaId(): ?int
    {
        return $this->mediaId;
    }

    public function setMediaId(?int $mediaId): self
    {
        $this->mediaId = $mediaId;

        return $this;
    }

    public function getPhotoCount(): int
    {
        return $this->photoCount;
    }

    public function setPhotoCount(int $photoCount): self
    {
        $this->photoCount = $photoCount;

        return $this;
    }

    public function getGeneratedAt(): ?\DateTimeImmutable
    {
        return $this->generatedAt;
    }

    public function setGeneratedAt(?\DateTimeImmutable $generatedAt): self
    {
        $this->generatedAt = $generatedAt;

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
}

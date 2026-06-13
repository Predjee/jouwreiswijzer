<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TravelPlanRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TravelPlanRepository::class)]
#[ORM\HasLifecycleCallbacks]
class TravelPlan
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false)]
    private TravelRequest $travelRequest;

    #[ORM\Column(length: 255)]
    private string $title;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'json')]
    private array $content = [];

    #[ORM\Column(length: 30, options: ['default' => self::STATUS_DRAFT])]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $pdfMediaId = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $pdfGeneratedAt = null;

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

    public function getTravelRequest(): TravelRequest
    {
        return $this->travelRequest;
    }

    public function setTravelRequest(TravelRequest $travelRequest): self
    {
        $this->travelRequest = $travelRequest;

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

    /**
     * @return array<string, mixed>
     */
    public function getContent(): array
    {
        return $this->content;
    }

    /**
     * @param array<string, mixed> $content
     */
    public function setContent(array $content): self
    {
        $this->content = $content;

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

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): self
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function getPdfMediaId(): ?int
    {
        return $this->pdfMediaId;
    }

    public function setPdfMediaId(?int $pdfMediaId): self
    {
        $this->pdfMediaId = $pdfMediaId;

        return $this;
    }

    public function getPdfGeneratedAt(): ?\DateTimeImmutable
    {
        return $this->pdfGeneratedAt;
    }

    public function setPdfGeneratedAt(?\DateTimeImmutable $pdfGeneratedAt): self
    {
        $this->pdfGeneratedAt = $pdfGeneratedAt;

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

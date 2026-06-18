<?php

declare(strict_types=1);

namespace App\Api\App\ReadModel;

final readonly class TodayReadModel
{
    /**
     * @param array<string, mixed>|null $nextActivity
     * @param list<array<string, mixed>> $timelineItems
     * @param list<array<string, mixed>> $tips
     */
    public function __construct(
        public int $tripId,
        public string $tripTitle,
        public bool $pdfAvailable,
        public bool $active,
        public ?int $currentDayNumber,
        public int $totalDays,
        public string $periodLabel,
        public string $durationLabel,
        public string $timingLabel,
        public ?string $dayTitle,
        public ?string $dayDateLabel,
        public ?string $dayIntro,
        public ?array $nextActivity,
        public array $timelineItems,
        public array $tips,
    ) {
    }

    public function hasDayContent(): bool
    {
        return null !== $this->dayTitle
            || null !== $this->dayIntro
            || null !== $this->nextActivity
            || [] !== $this->timelineItems
            || [] !== $this->tips;
    }
}

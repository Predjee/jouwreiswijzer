<?php

declare(strict_types=1);

namespace App\ViewModel\TravelCompanion;

final readonly class TodayTravelPlan
{
    public function __construct(
        public int $id,
        public string $title,
        public string $mode,
        public ?int $currentDay,
        public int $totalDays,
        public string $periodLabel,
        public string $durationLabel,
        public string $timingLabel,
        public bool $pdfAvailable,
        public ?string $dayTitle,
        public ?string $dayDateLabel,
        public ?string $dayIntro,
        public bool $active,
        public bool $upcoming,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\ViewModel\TravelCompanion;

final readonly class CompanionDay
{
    /**
     * @param list<CompanionBlock> $blocks
     */
    public function __construct(
        public int $dayNumber,
        public string $title,
        public string $dateLabel,
        public string $subtitle,
        public string $location,
        public string $intro,
        public string $status,
        public bool $past,
        public bool $current,
        public bool $upcoming,
        public array $blocks,
    ) {
    }
}

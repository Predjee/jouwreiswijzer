<?php

declare(strict_types=1);

namespace App\ViewModel\TravelCompanion;

final readonly class CompanionBlock
{
    /**
     * @param list<array{key: string, label: string, checked: bool}> $checklistItems
     * @param list<array{title: string, location: string, text: string, icon: string}> $routeStops
     */
    public function __construct(
        public string $type,
        public string $title,
        public string $text,
        public string $icon,
        public string $location,
        public string $mapsUrl,
        public string $timeLabel,
        public string $startTime,
        public string $endTime,
        public string $timeRangeLabel,
        public string $bookingUrl,
        public array $checklistItems,
        public array $routeStops = [],
    ) {
    }

    public function hasContent(): bool
    {
        return '' !== $this->title
            || '' !== $this->text
            || '' !== $this->location
            || '' !== $this->timeLabel
            || '' !== $this->timeRangeLabel
            || [] !== $this->checklistItems
            || [] !== $this->routeStops;
    }

    public function isChecklist(): bool
    {
        return 'checklist' === $this->type && [] !== $this->checklistItems;
    }

    public function isNote(): bool
    {
        return \in_array($this->type, ['note', 'personal_note'], true);
    }
}

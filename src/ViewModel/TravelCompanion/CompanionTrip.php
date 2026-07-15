<?php

declare(strict_types=1);

namespace App\ViewModel\TravelCompanion;

final readonly class CompanionTrip
{
    /**
     * @param list<CompanionDay> $days
     * @param list<CompanionBlock> $blocks
     */
    public function __construct(
        public int $id,
        public string $title,
        public string $periodLabel,
        public string $durationLabel,
        public ?int $currentDayNumber,
        public string $dayStatusLabel,
        public bool $pdfAvailable,
        public array $days,
        public ?CompanionDay $currentDay,
        public array $blocks,
        public bool $hasChecklist,
        public bool $hasNotes,
    ) {
    }

    public function hasDays(): bool
    {
        return [] !== $this->days;
    }

    public function findDay(int $dayNumber): ?CompanionDay
    {
        foreach ($this->days as $day) {
            if ($day->dayNumber === $dayNumber) {
                return $day;
            }
        }

        return null;
    }

    /**
     * @return list<CompanionBlock>
     */
    public function getChecklistBlocks(): array
    {
        return \array_values(\array_filter(
            $this->blocks,
            static fn (CompanionBlock $block): bool => $block->isChecklist(),
        ));
    }

    /**
     * @return list<CompanionBlock>
     */
    public function getInfoBlocks(): array
    {
        return \array_values(\array_filter(
            $this->blocks,
            static fn (CompanionBlock $block): bool => !$block->isChecklist(),
        ));
    }

    public function hasInfoBlocks(): bool
    {
        return [] !== $this->getInfoBlocks();
    }

    public function hasType(string $type): bool
    {
        foreach ($this->blocks as $block) {
            if ($block->type === $type) {
                return true;
            }
        }

        foreach ($this->days as $day) {
            foreach ($day->blocks as $block) {
                if ($block->type === $type) {
                    return true;
                }
            }
        }

        return false;
    }
}

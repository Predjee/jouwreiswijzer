<?php

declare(strict_types=1);

namespace App\Api\App\ReadModel;

final readonly class ChecklistReadModel
{
    /**
     * @param list<array{title: string, items: list<array{id: string, label: string, checked: bool}>}> $checklists
     */
    public function __construct(
        public int $tripId,
        public string $tripTitle,
        public int $completed,
        public int $total,
        public array $checklists,
    ) {
    }

    public function hasItems(): bool
    {
        return $this->total > 0;
    }
}

<?php

declare(strict_types=1);

namespace App\TravelPlan\View;

use App\TravelPlan\BlockPath;

/**
 * @phpstan-import-type DayRow from SectionView
 */
final readonly class DayView
{
    /**
     * @param list<DayRow> $rows
     */
    public function __construct(
        public BlockPath $path,
        public bool $isPrimary,
        public bool $isSecondary,
        public string $meta,
        public string $title,
        public string $introHtml,
        public ?string $iconSrc,
        public bool $headerOnly,
        public ?BlockView $boundCard,
        public bool $boundCloses,
        public array $rows,
    ) {
    }
}

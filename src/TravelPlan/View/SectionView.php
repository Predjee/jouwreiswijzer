<?php

declare(strict_types=1);

namespace App\TravelPlan\View;

use App\TravelPlan\BlockPath;

/**
 * @phpstan-import-type RouteStop from DestinationView
 *
 * @phpstan-type DayRow array{solo: bool, block: BlockView, isFirstOfSegment: bool, isLastOfSegment: bool}
 */
final readonly class SectionView
{
    /**
     * @param list<BlockView> $blocks
     * @param list<DayRow> $rows
     * @param list<RouteStop> $routeStops
     */
    public function __construct(
        public BlockPath $path,
        public string $type,
        public string $title,
        public string $text,
        public string $textHtml,
        public string $intro,
        public string $introHtml,
        public string $iconSvg,
        public ?string $iconSrc,
        public string $dayNumber,
        public string $dateLabel,
        public bool $startOnNewPage,
        public string $pageBreakClass,
        public string $styleVariant,
        public string $variant,
        public string $variantClass,
        public bool $isPrimary,
        public bool $isSecondary,
        public bool $isGold,
        public string $meta,
        public bool $headerOnly,
        public ?BlockView $boundCard,
        public bool $boundCloses,
        public ?DayView $day,
        public array $blocks,
        public array $rows,
        public array $routeStops,
    ) {
    }
}

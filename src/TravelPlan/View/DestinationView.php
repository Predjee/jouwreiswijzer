<?php

declare(strict_types=1);

namespace App\TravelPlan\View;

use App\TravelPlan\BlockPath;

/**
 * @phpstan-type RouteStop array{type?: string, title?: string, location?: string, text?: string, icon?: string, _iconMarkup?: string|null}
 */
final readonly class DestinationView
{
    /**
     * @param list<SectionView> $sections
     * @param list<RouteStop> $routeStops
     */
    public function __construct(
        public BlockPath $path,
        public string $type,
        public string $title,
        public string $text,
        public string $textHtml,
        public string $iconSvg,
        public ?string $iconSrc,
        public string $city,
        public string $region,
        public string $country,
        public string $location,
        public string $caption,
        public ?string $imageSrc,
        public bool $startOnNewPage,
        public string $pageBreakClass,
        public string $styleVariant,
        public string $variant,
        public string $variantClass,
        public bool $isPrimary,
        public bool $isSecondary,
        public bool $isGold,
        public string $background,
        public string $edge,
        public string $accent,
        public string $barColor,
        public string $titleColor,
        public string $bodyColor,
        public string $metaColor,
        public bool $keep,
        public array $sections,
        public array $routeStops = [],
    ) {
    }
}

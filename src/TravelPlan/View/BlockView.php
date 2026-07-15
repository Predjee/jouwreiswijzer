<?php

declare(strict_types=1);

namespace App\TravelPlan\View;

use App\TravelPlan\BlockPath;

final readonly class BlockView
{
    public function __construct(
        public BlockPath $path,
        public string $type,
        public bool $isTip,
        public bool $isPrimary,
        public bool $isSecondary,
        public bool $isGold,
        public bool $flow,
        public bool $startOnNewPage,
        public string $pageBreakClass,
        public string $styleVariant,
        public string $variantClass,
        public string $title,
        public string $timeRangeLabel,
        public string $timeLabel,
        public string $location,
        public string $priceLabel,
        public string $text,
        public string $textHtml,
        public string $bookingUrl,
        public string $actionLabel,
        public string $iconSvg,
        public ?string $iconSrc,
    ) {
    }
}

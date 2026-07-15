<?php

declare(strict_types=1);

namespace App\TravelPlan\View;

use App\Entity\TravelPlanFeedback;
use App\TravelPlan\BlockPath;

final readonly class RenderedSection
{
    public function __construct(
        public string $html,
        public BlockPath $path,
        public string $blockPath,
        public string $blockType,
        public ?TravelPlanFeedback $feedback,
        public ?string $tocTitle = null,
        public int $tocLevel = 0,
        public bool $startOnNewPage = false,
    ) {
    }
}

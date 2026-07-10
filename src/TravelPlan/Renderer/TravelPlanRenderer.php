<?php

declare(strict_types=1);

namespace App\TravelPlan\Renderer;

use App\Entity\TravelPlan;
use App\Entity\TravelPlanFeedback;
use App\Service\IconResolver;
use App\TravelPlan\Content\ColorVariant;
use App\TravelPlan\Content\DayBlock;
use App\TravelPlan\Content\Destination;
use App\TravelPlan\Content\Section;
use App\TravelPlan\Content\SectionType;
use App\TravelPlan\Content\TravelPlanContent;
use App\TravelPlan\TravelPlanStyle;
use Twig\Environment;

/**
 * Rendert de account-weergave ("mijn omgeving") van een reisplan.
 * De PDF-weergave heeft een eigen renderer: TravelPlanPdfRenderer.
 */
final readonly class TravelPlanRenderer
{
    private const SECTION_TEMPLATES = [
        'destination' => 'travel_plan/render/sections/destination.html.twig',
        'route_overview' => 'travel_plan/render/sections/route_overview.html.twig',
        'day' => 'travel_plan/render/sections/day.html.twig',
        'practical_info' => 'travel_plan/render/sections/practical_info.html.twig',
        'checklist' => 'travel_plan/render/sections/checklist.html.twig',
        'budget_note' => 'travel_plan/render/sections/budget_note.html.twig',
        'personal_note' => 'travel_plan/render/sections/personal_note.html.twig',
        'free_text' => 'travel_plan/render/sections/free_text.html.twig',
        'image' => 'travel_plan/render/sections/image.html.twig',
    ];

    private const DAY_BLOCK_TEMPLATES = [
        'activity' => 'travel_plan/render/day_blocks/activity.html.twig',
        'accommodation' => 'travel_plan/render/day_blocks/accommodation.html.twig',
        'transport' => 'travel_plan/render/day_blocks/transport.html.twig',
        'meal' => 'travel_plan/render/day_blocks/meal.html.twig',
        'tip' => 'travel_plan/render/day_blocks/tip.html.twig',
        'note' => 'travel_plan/render/day_blocks/note.html.twig',
        'free_text' => 'travel_plan/render/day_blocks/free_text.html.twig',
    ];

    public function __construct(
        private Environment $twig,
        private IconResolver $iconResolver,
        private TravelPlanContentHelper $helper,
    ) {
    }

    /**
     * @param array<string, TravelPlanFeedback> $feedbackByPath
     */
    public function renderForAccount(
        TravelPlan $travelPlan,
        array $feedbackByPath = [],
        bool $feedbackEnabled = true,
    ): string {
        $content = TravelPlanContent::fromArray($travelPlan->getContent());
        $renderedSections = [];

        foreach ($content->destinations as $destination) {
            $destinationPath = \sprintf('destinations[%d]', $destination->sourceIndex);

            if ($destination->isImage()) {
                $renderedImage = $this->twig->render(self::SECTION_TEMPLATES['image'], [
                    'section' => $this->imageSection($destination),
                    'accountView' => true,
                    'travelPlan' => $travelPlan,
                    'feedbackEnabled' => $feedbackEnabled,
                ]);

                $renderedSections[] = [
                    'html' => $this->applyPageBreakClass($renderedImage, $destination->startOnNewPage),
                    'blockPath' => $destinationPath,
                    'blockType' => SectionType::Image->value,
                    'feedback' => $feedbackEnabled ? ($feedbackByPath[$destinationPath] ?? null) : null,
                ];

                continue;
            }

            $renderedDestination = $this->twig->render(self::SECTION_TEMPLATES['destination'], [
                'section' => $this->withVariantData($destination->raw, $destination->colorVariant),
                'accountView' => true,
                'travelPlan' => $travelPlan,
                'feedbackEnabled' => $feedbackEnabled,
            ]);
            $renderedDestination = $this->applyPageBreakClass(
                $this->prependIcon($renderedDestination, $destination->iconOrDefault()),
                $destination->startOnNewPage,
            );

            $renderedSections[] = [
                'html' => $renderedDestination,
                'blockPath' => $destinationPath,
                'blockType' => SectionType::Destination->value,
                'feedback' => $feedbackEnabled ? ($feedbackByPath[$destinationPath] ?? null) : null,
            ];

            foreach ($destination->sections as $section) {
                $renderedSections[] = $this->renderSection(
                    $section,
                    $travelPlan,
                    $destination->sourceIndex,
                    $feedbackByPath,
                    $feedbackEnabled,
                );
            }
        }

        return $this->twig->render('travel_plan/render/base.html.twig', [
            'travelPlan' => $travelPlan,
            'intro' => ['title' => $content->introTitle, 'text' => $content->introText],
            'tripProfile' => $content->tripProfile->raw,
            'renderedSections' => $renderedSections,
            'tableOfContents' => [],
            'showTableOfContents' => false,
            'logoSrc' => null,
            'accountView' => true,
            'feedbackEnabled' => $feedbackEnabled,
            'styleVariants' => TravelPlanStyle::variants(),
        ]);
    }

    /**
     * @param array<string, TravelPlanFeedback> $feedbackByPath
     *
     * @return array{html: string, blockPath: string, blockType: string, feedback: ?TravelPlanFeedback}
     */
    private function renderSection(
        Section $section,
        TravelPlan $travelPlan,
        int $destinationIndex,
        array $feedbackByPath,
        bool $feedbackEnabled,
    ): array {
        $type = $section->type->value;
        $rawSection = $this->accountSection($section);
        $context = [
            'section' => $rawSection,
            'accountView' => true,
            'travelPlan' => $travelPlan,
            'feedbackEnabled' => $feedbackEnabled,
        ];

        if (SectionType::Day === $section->type) {
            $context['renderedBlocks'] = $this->renderDayBlocks(
                $section->blocks,
                $travelPlan,
                $destinationIndex,
                $section->sourceIndex,
                $feedbackByPath,
                $feedbackEnabled,
            );
        }

        $sectionPath = \sprintf(
            'destinations[%d].sections[%d]',
            $destinationIndex,
            $section->sourceIndex,
        );
        $renderedSection = $this->twig->render(self::SECTION_TEMPLATES[$type], $context);
        $renderedSection = $this->applyPageBreakClass(
            $this->prependIcon($renderedSection, $section->iconOrDefault()),
            $section->startOnNewPage,
        );

        return [
            'html' => $renderedSection,
            'blockPath' => $sectionPath,
            'blockType' => $type,
            'feedback' => $feedbackEnabled ? ($feedbackByPath[$sectionPath] ?? null) : null,
        ];
    }

    /**
     * @param list<DayBlock> $blocks
     * @param array<string, TravelPlanFeedback> $feedbackByPath
     *
     * @return list<array{html: string, blockPath: string, blockType: string, feedback: ?TravelPlanFeedback}>
     */
    private function renderDayBlocks(
        array $blocks,
        TravelPlan $travelPlan,
        int $destinationIndex,
        int $sectionIndex,
        array $feedbackByPath,
        bool $feedbackEnabled,
    ): array {
        $renderedBlocks = [];

        foreach ($blocks as $block) {
            $type = $block->type->value;
            $renderedBlock = $this->twig->render(
                self::DAY_BLOCK_TEMPLATES[$type],
                [
                    'block' => $this->withVariantData($this->helper->withTimeRange($block->raw), $block->colorVariant),
                    'accountView' => true,
                    'travelPlan' => $travelPlan,
                ],
            );
            $blockPath = \sprintf(
                'destinations[%d].sections[%d].blocks[%d]',
                $destinationIndex,
                $sectionIndex,
                $block->sourceIndex,
            );
            $renderedBlock = $this->prependIcon($renderedBlock, $block->iconOrDefault());

            $renderedBlocks[] = [
                'html' => $this->applyPageBreakClass($renderedBlock, $block->startOnNewPage),
                'blockPath' => $blockPath,
                'blockType' => $type,
                'feedback' => $feedbackEnabled ? ($feedbackByPath[$blockPath] ?? null) : null,
            ];
        }

        return $renderedBlocks;
    }

    /**
     * @return array<string, mixed>
     */
    private function imageSection(Destination $destination): array
    {
        return \array_replace($destination->raw, [
            'imageSrc' => $this->helper->mediaImageSrc($destination->image, false),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function accountSection(Section $section): array
    {
        $raw = $this->withVariantData($section->raw, $section->colorVariant);

        if (SectionType::RouteOverview !== $section->type) {
            return $raw;
        }

        return \array_replace($raw, [
            'routeStops' => \array_map(
                fn (array $stop): array => \array_replace($stop, [
                    '_iconMarkup' => $this->iconSvg($this->iconOrDefault($stop['icon'] ?? null, SectionType::RouteOverview->defaultIcon())),
                ]),
                $section->routeStops,
            ),
        ]);
    }

    /**
     * @param array<string, mixed> $raw
     *
     * @return array<string, mixed>
     */
    private function withVariantData(array $raw, ColorVariant $variant): array
    {
        $variantName = $this->variantName($variant);

        return \array_replace($raw, [
            'styleVariant' => $variantName,
            'variantClass' => 'default' === $variantName ? '' : 'travel-plan-variant--' . $variantName,
        ]);
    }

    private function variantName(ColorVariant $variant): string
    {
        return ColorVariant::Auto === $variant ? 'default' : $variant->value;
    }

    private function prependIcon(string $html, mixed $icon): string
    {
        $iconMarkup = $this->iconSvg($icon);

        if (null === $iconMarkup) {
            return $html;
        }

        $injectedIcon = '<span class="travel-plan-icon-slot" aria-hidden="true">' . $iconMarkup . '</span>';

        return \preg_replace(
            '/(<(?:section|article|aside|div)\b[^>]*>)/',
            '$1' . $injectedIcon,
            $html,
            1,
        ) ?? $html;
    }

    private function applyPageBreakClass(string $html, bool $startOnNewPage): string
    {
        if (!$startOnNewPage) {
            return $html;
        }

        $html = \preg_replace(
            '/(<(?:section|article|aside|div)\b[^>]*class=")([^"]*)(")/',
            '$1$2 travel-plan-page-break-before$3',
            $html,
            1,
        ) ?? $html;

        if (1 !== \preg_match('/\btravel-plan-page-break-before\b/', $html)) {
            $html = \preg_replace(
                '/(<(?:section|article|aside|div)\b)(?![^>]*\bclass=)/',
                '$1 class="travel-plan-page-break-before"',
                $html,
                1,
            ) ?? $html;
        }

        return $html;
    }

    private function iconSvg(mixed $icon): ?string
    {
        if (!\is_scalar($icon)) {
            return null;
        }

        $icon = \trim((string) $icon);

        if ('' === $icon) {
            return null;
        }

        $svg = $this->iconResolver->getSvgIcon($icon);

        return '' === $svg ? null : $svg;
    }

    private function iconOrDefault(mixed $icon, string $default): string
    {
        if (!\is_scalar($icon)) {
            return $default;
        }

        $icon = \trim((string) $icon);

        return '' === $icon ? $default : $icon;
    }
}

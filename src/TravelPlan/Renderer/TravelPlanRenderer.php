<?php

declare(strict_types=1);

namespace App\TravelPlan\Renderer;

use App\Entity\TravelPlan;
use App\Entity\TravelPlanFeedback;
use App\Service\IconResolver;
use Twig\Environment;

/**
 * Rendert de account-weergave ("mijn omgeving") van een reisplan.
 * De PDF-weergave heeft een eigen renderer: TravelPlanPdfRenderer.
 */
final readonly class TravelPlanRenderer
{
    private const DEFAULT_SECTION_ICONS = [
        'destination' => 'map',
        'route_overview' => 'map',
        'practical_info' => 'info',
        'checklist' => 'list-check',
        'budget_note' => 'wallet',
        'personal_note' => 'heart',
        'free_text' => 'info',
        'image' => 'image',
    ];

    private const DEFAULT_DAY_BLOCK_ICONS = [
        'activity' => 'compass',
        'accommodation' => 'bed',
        'transport' => 'car',
        'meal' => 'utensils',
        'tip' => 'lightbulb',
        'note' => 'sticky-note',
        'free_text' => 'info',
    ];

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
        $content = $travelPlan->getContent();
        $renderedSections = [];

        foreach (($content['destinations'] ?? []) as $destinationIndex => $destination) {
            if (!\is_array($destination)) {
                continue;
            }

            $type = $destination['type'] ?? null;

            if ('image' === $type) {
                $destination['imageSrc'] = $this->helper->mediaImageSrc($destination['image'] ?? null, false);
                $destinationPath = \sprintf('destinations[%d]', (int) $destinationIndex);
                $renderedImage = $this->twig->render(self::SECTION_TEMPLATES['image'], [
                    'section' => $destination,
                    'accountView' => true,
                    'travelPlan' => $travelPlan,
                    'feedbackEnabled' => $feedbackEnabled,
                ]);
                $renderedSections[] = [
                    'html' => $this->applyPageBreakClass($renderedImage, $destination['startOnNewPage'] ?? false),
                    'blockPath' => $destinationPath,
                    'blockType' => 'image',
                    'feedback' => $feedbackEnabled ? ($feedbackByPath[$destinationPath] ?? null) : null,
                ];

                continue;
            }

            if ('destination' !== $type) {
                continue;
            }

            $destinationPath = \sprintf('destinations[%d]', $destinationIndex);
            $renderedDestination = $this->twig->render(self::SECTION_TEMPLATES['destination'], [
                'section' => $destination,
                'accountView' => true,
                'travelPlan' => $travelPlan,
                'feedbackEnabled' => $feedbackEnabled,
            ]);
            $renderedDestination = $this->applyPageBreakClass(
                $this->prependIcon($renderedDestination, $this->iconOrDefault($destination['icon'] ?? null, self::DEFAULT_SECTION_ICONS['destination'])),
                $destination['startOnNewPage'] ?? false,
            );
            $renderedSections[] = [
                'html' => $renderedDestination,
                'blockPath' => $destinationPath,
                'blockType' => 'destination',
                'feedback' => $feedbackEnabled ? ($feedbackByPath[$destinationPath] ?? null) : null,
            ];

            foreach ($destination['sections'] ?? [] as $sectionIndex => $section) {
                if (!\is_array($section)) {
                    continue;
                }

                $type = $section['type'] ?? null;

                if (!\is_string($type) || !isset(self::SECTION_TEMPLATES[$type]) || 'destination' === $type) {
                    continue;
                }

                if ('route_overview' === $type && \is_array($section['routeStops'] ?? null)) {
                    $section['routeStops'] = \array_map(function (mixed $stop): mixed {
                        if (!\is_array($stop)) {
                            return $stop;
                        }

                        $stop['_iconMarkup'] = $this->iconSvg($this->iconOrDefault($stop['icon'] ?? null, 'map'));

                        return $stop;
                    }, $section['routeStops']);
                }

                $context = [
                    'section' => $section,
                    'accountView' => true,
                    'travelPlan' => $travelPlan,
                    'feedbackEnabled' => $feedbackEnabled,
                ];

                if ('day' === $type) {
                    $context['renderedBlocks'] = $this->renderDayBlocks(
                        $section['blocks'] ?? [],
                        $travelPlan,
                        $destinationIndex,
                        (int) $sectionIndex,
                        $feedbackByPath,
                        $feedbackEnabled,
                    );
                }

                $sectionPath = \sprintf(
                    'destinations[%d].sections[%d]',
                    $destinationIndex,
                    (int) $sectionIndex,
                );
                $renderedSection = $this->twig->render(self::SECTION_TEMPLATES[$type], $context);
                $renderedSection = $this->applyPageBreakClass(
                    $this->prependIcon($renderedSection, $this->iconOrDefault($section['icon'] ?? null, self::DEFAULT_SECTION_ICONS[$type] ?? null)),
                    $section['startOnNewPage'] ?? false,
                );
                $renderedSections[] = [
                    'html' => $renderedSection,
                    'blockPath' => $sectionPath,
                    'blockType' => $type,
                    'feedback' => $feedbackEnabled ? ($feedbackByPath[$sectionPath] ?? null) : null,
                ];
            }
        }

        return $this->twig->render('travel_plan/render/base.html.twig', [
            'travelPlan' => $travelPlan,
            'intro' => \is_array($content['intro'] ?? null) ? $content['intro'] : [],
            'tripProfile' => \is_array($content['tripProfile'] ?? null) ? $content['tripProfile'] : [],
            'renderedSections' => $renderedSections,
            'tableOfContents' => [],
            'showTableOfContents' => false,
            'logoSrc' => null,
            'accountView' => true,
            'feedbackEnabled' => $feedbackEnabled,
        ]);
    }

    /**
     * @param array<string, TravelPlanFeedback> $feedbackByPath
     *
     * @return list<array{html: string, blockPath: string, blockType: string, feedback: ?TravelPlanFeedback}>
     */
    private function renderDayBlocks(
        mixed $blocks,
        TravelPlan $travelPlan,
        int $destinationIndex,
        int $sectionIndex,
        array $feedbackByPath,
        bool $feedbackEnabled,
    ): array {
        if (!\is_array($blocks)) {
            return [];
        }

        $renderedBlocks = [];

        foreach ($blocks as $blockIndex => $block) {
            if (!\is_array($block)) {
                continue;
            }

            $type = $block['type'] ?? null;

            if (!\is_string($type) || !isset(self::DAY_BLOCK_TEMPLATES[$type])) {
                continue;
            }

            $renderedBlock = $this->twig->render(
                self::DAY_BLOCK_TEMPLATES[$type],
                [
                    'block' => $this->helper->withTimeRange($block),
                    'accountView' => true,
                    'travelPlan' => $travelPlan,
                ],
            );
            $blockPath = \sprintf(
                'destinations[%d].sections[%d].blocks[%d]',
                $destinationIndex,
                $sectionIndex,
                $blockIndex,
            );
            $renderedBlock = $this->prependIcon(
                $renderedBlock,
                $this->iconOrDefault($block['icon'] ?? null, self::DEFAULT_DAY_BLOCK_ICONS[$type]),
            );

            $renderedBlocks[] = [
                'html' => $this->applyPageBreakClass($renderedBlock, $block['startOnNewPage'] ?? false),
                'blockPath' => $blockPath,
                'blockType' => $type,
                'feedback' => $feedbackEnabled ? ($feedbackByPath[$blockPath] ?? null) : null,
            ];
        }

        return $renderedBlocks;
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

    private function applyPageBreakClass(string $html, mixed $startOnNewPage): string
    {
        if (!$this->helper->isTruthy($startOnNewPage)) {
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

    private function iconOrDefault(mixed $icon, ?string $default): mixed
    {
        if (\is_string($icon) && '' !== \trim($icon)) {
            return $icon;
        }

        return $default;
    }

    private function iconSvg(mixed $icon): ?string
    {
        if (!\is_string($icon)) {
            return null;
        }

        $svg = $this->iconResolver->getSvgIcon($icon);

        return '' === $svg ? null : $svg;
    }
}

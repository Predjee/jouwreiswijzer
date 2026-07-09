<?php

declare(strict_types=1);

namespace App\TravelPlan\Renderer;

use App\Entity\TravelPlan;
use App\Entity\TravelPlanFeedback;
use App\Service\IconResolver;
use App\TravelPlan\Pdf\TravelPlanPdfRenderer;
use Twig\Environment;

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
        private TravelPlanContentHelper $contentHelper,
        private TravelPlanPdfRenderer $pdfRenderer,
    ) {
    }

    public function render(TravelPlan $travelPlan): string
    {
        return $this->renderView($travelPlan, false);
    }

    /**
     * @param array<string, TravelPlanFeedback> $feedbackByPath
     */
    public function renderForAccount(
        TravelPlan $travelPlan,
        array $feedbackByPath = [],
        bool $feedbackEnabled = true,
    ): string {
        return $this->renderView($travelPlan, true, $feedbackByPath, $feedbackEnabled);
    }

    /**
     * @param array<string, TravelPlanFeedback> $feedbackByPath
     */
    private function renderView(
        TravelPlan $travelPlan,
        bool $accountView,
        array $feedbackByPath = [],
        bool $feedbackEnabled = false,
    ): string {
        $content = $travelPlan->getContent();
        $renderedSections = [];
        $tableOfContents = [];
        $tableOfContentsDepth = $this->contentHelper->tableOfContentsDepth($content['tripProfile']['showTableOfContents'] ?? null);

        foreach (($content['destinations'] ?? []) as $destinationIndex => $destination) {
            if (!\is_array($destination)) {
                continue;
            }

            $type = $destination['type'] ?? null;
            $destinationPath = \sprintf('destinations[%d]', (int) $destinationIndex);

            if ('image' === $type) {
                $destination = $this->prepareImageBlock($destination, !$accountView);
                $renderedImage = $this->twig->render(self::SECTION_TEMPLATES['image'], [
                    'section' => $destination,
                    'accountView' => $accountView,
                    'travelPlan' => $travelPlan,
                    'feedbackEnabled' => $feedbackEnabled,
                ]);
                $renderedImage = $this->applyPageBreakClass($renderedImage, $destination['startOnNewPage'] ?? false, !$accountView);
                $this->addRenderedSection(
                    $renderedSections,
                    $tableOfContents,
                    $renderedImage,
                    $destinationPath,
                    'image',
                    $destination['title'] ?? 'Afbeelding',
                    0,
                    $accountView,
                    $tableOfContentsDepth,
                    $feedbackByPath,
                    $feedbackEnabled,
                );

                continue;
            }

            if ('destination' !== $type) {
                continue;
            }

            $destination = $this->pdfRenderer->prepareRichText($destination, $accountView);
            $renderedDestination = $this->twig->render(self::SECTION_TEMPLATES['destination'], [
                'section' => $destination,
                'accountView' => $accountView,
                'travelPlan' => $travelPlan,
                'feedbackEnabled' => $feedbackEnabled,
            ]);
            $renderedDestination = $this->prependIcon(
                $renderedDestination,
                $this->iconOrDefault($destination['icon'] ?? null, self::DEFAULT_SECTION_ICONS['destination']),
                $accountView,
                true,
            );

            if (!$accountView) {
                $renderedDestination = $this->pdfRenderer->wrapHeroKeep($renderedDestination);
            }

            $renderedDestination = $this->applyPageBreakClass(
                $renderedDestination,
                $destination['startOnNewPage'] ?? false,
                !$accountView,
            );

            $this->addRenderedSection(
                $renderedSections,
                $tableOfContents,
                $renderedDestination,
                $destinationPath,
                'destination',
                $destination['title'] ?? '',
                0,
                $accountView,
                $tableOfContentsDepth,
                $feedbackByPath,
                $feedbackEnabled,
            );

            foreach ($destination['sections'] ?? [] as $sectionIndex => $section) {
                if (!\is_array($section)) {
                    continue;
                }

                $section = $this->pdfRenderer->prepareRichText($section, $accountView);
                $type = $section['type'] ?? null;

                if (!\is_string($type) || !isset(self::SECTION_TEMPLATES[$type]) || 'destination' === $type) {
                    continue;
                }

                if ('route_overview' === $type && \is_array($section['routeStops'] ?? null)) {
                    $section['routeStops'] = $this->prepareRouteStops($section['routeStops'], $accountView);
                }

                $sectionPath = \sprintf('destinations[%d].sections[%d]', (int) $destinationIndex, (int) $sectionIndex);
                $context = [
                    'section' => $section,
                    'accountView' => $accountView,
                    'travelPlan' => $travelPlan,
                    'feedbackEnabled' => $feedbackEnabled,
                ];

                if ('day' === $type) {
                    if ($accountView) {
                        $context['renderedBlocks'] = $this->renderDayBlocks(
                            $section['blocks'] ?? [],
                            $travelPlan,
                            (int) $destinationIndex,
                            (int) $sectionIndex,
                            true,
                            $feedbackByPath,
                            $feedbackEnabled,
                        );
                    } else {
                        $renderedSection = $this->pdfRenderer->renderDayGroup(
                            $section,
                            $section['blocks'] ?? [],
                            $this->iconOrDefault($section['icon'] ?? null, self::DEFAULT_SECTION_ICONS[$type] ?? null),
                        );
                        $renderedSection = $this->applyPageBreakClass($renderedSection, $section['startOnNewPage'] ?? false);

                        $this->addRenderedSection(
                            $renderedSections,
                            $tableOfContents,
                            $renderedSection,
                            $sectionPath,
                            $type,
                            $section['title'] ?? '',
                            1,
                            false,
                            $tableOfContentsDepth,
                            $feedbackByPath,
                            $feedbackEnabled,
                        );

                        continue;
                    }
                }

                $renderedSection = $this->twig->render(self::SECTION_TEMPLATES[$type], $context);
                $renderedSection = $this->prependIcon(
                    $renderedSection,
                    $this->iconOrDefault($section['icon'] ?? null, self::DEFAULT_SECTION_ICONS[$type] ?? null),
                    $accountView,
                    true,
                );
                $renderedSection = $this->applyPageBreakClass(
                    $renderedSection,
                    $section['startOnNewPage'] ?? false,
                    !$accountView,
                );

                if (!$accountView) {
                    $renderedSection = $this->pdfRenderer->wrapHeroKeep($renderedSection);
                }

                $this->addRenderedSection(
                    $renderedSections,
                    $tableOfContents,
                    $renderedSection,
                    $sectionPath,
                    $type,
                    $section['title'] ?? '',
                    1,
                    $accountView,
                    $tableOfContentsDepth,
                    $feedbackByPath,
                    $feedbackEnabled,
                );
            }
        }

        if (!$accountView && $tableOfContentsDepth > 0 && [] !== $renderedSections) {
            $renderedSections[0]['html'] = $this->suppressInitialPdfPageBreak($renderedSections[0]['html']);
        }

        return $this->twig->render('travel_plan/render/base.html.twig', [
            'travelPlan' => $travelPlan,
            'intro' => \is_array($content['intro'] ?? null) ? $content['intro'] : [],
            'tripProfile' => \is_array($content['tripProfile'] ?? null) ? $content['tripProfile'] : [],
            'renderedSections' => $renderedSections,
            'tableOfContents' => $tableOfContents,
            'showTableOfContents' => $tableOfContentsDepth > 0,
            'logoSrc' => $this->contentHelper->assetDataUri('assets/images/pdf/logo-pdf.png', 'image/png'),
            'accountView' => $accountView,
            'feedbackEnabled' => $feedbackEnabled,
        ]);
    }

    /**
     * @param list<array<string, mixed>> $renderedSections
     * @param list<array{title: string, level: int}> $tableOfContents
     * @param array<string, TravelPlanFeedback> $feedbackByPath
     */
    private function addRenderedSection(
        array &$renderedSections,
        array &$tableOfContents,
        string $html,
        string $blockPath,
        string $blockType,
        mixed $title,
        int $tocLevel,
        bool $accountView,
        int $tableOfContentsDepth,
        array $feedbackByPath,
        bool $feedbackEnabled,
    ): void {
        $this->addTableOfContentsEntry($tableOfContents, $title, $tocLevel, $tableOfContentsDepth);

        $renderedSections[] = [
            'html' => $this->withPdfChunkMarker(
                $this->withTableOfContentsEntry($html, $title, $tocLevel, $accountView, $tableOfContentsDepth),
                $accountView,
            ),
            'blockPath' => $blockPath,
            'blockType' => $blockType,
            'tocTitle' => $this->contentHelper->tableOfContentsTitle($title),
            'tocLevel' => $tocLevel,
            'feedback' => $feedbackEnabled ? ($feedbackByPath[$blockPath] ?? null) : null,
        ];
    }

    /**
     * @param array<int, mixed> $blocks
     * @param array<string, TravelPlanFeedback> $feedbackByPath
     *
     * @return list<array{html: string, blockPath: string, blockType: string, feedback?: ?TravelPlanFeedback}>
     */
    private function renderDayBlocks(
        mixed $blocks,
        TravelPlan $travelPlan,
        int $destinationIndex,
        int $sectionIndex,
        bool $accountView,
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

            $block = $this->contentHelper->withTimeRange($block);
            $renderedBlock = $this->twig->render(self::DAY_BLOCK_TEMPLATES[$type], [
                'block' => $block,
                'accountView' => $accountView,
                'travelPlan' => $travelPlan,
            ]);
            $renderedBlock = $this->prependIcon(
                $renderedBlock,
                $this->iconOrDefault($block['icon'] ?? null, self::DEFAULT_DAY_BLOCK_ICONS[$type]),
                $accountView,
            );
            $renderedBlock = $this->applyPageBreakClass($renderedBlock, $block['startOnNewPage'] ?? false);

            $blockPath = \sprintf(
                'destinations[%d].sections[%d].blocks[%d]',
                $destinationIndex,
                $sectionIndex,
                (int) $blockIndex,
            );

            $renderedBlocks[] = [
                'html' => $renderedBlock,
                'blockPath' => $blockPath,
                'blockType' => $type,
                'feedback' => $feedbackEnabled ? ($feedbackByPath[$blockPath] ?? null) : null,
            ];
        }

        return $renderedBlocks;
    }

    /**
     * @param list<array<string, mixed>> $routeStops
     *
     * @return list<array<string, mixed>>
     */
    private function prepareRouteStops(array $routeStops, bool $accountView): array
    {
        foreach ($routeStops as $index => $stop) {
            if (!\is_array($stop)) {
                unset($routeStops[$index]);

                continue;
            }

            $icon = $this->iconOrDefault($stop['icon'] ?? null, 'map-pin');
            $stop['_iconMarkup'] = $this->iconMarkup($icon, $accountView);
            $routeStops[$index] = $stop;
        }

        return \array_values($routeStops);
    }

    private function prependIcon(string $html, mixed $icon, bool $accountView, bool $sectionIcon = false): string
    {
        $iconMarkup = $this->iconMarkup($icon, $accountView);

        if (null === $iconMarkup) {
            return $html;
        }

        if (!$accountView && $sectionIcon) {
            $iconMarkup = \str_replace(
                'class="travel-plan-icon"',
                'class="travel-plan-icon travel-plan-icon--section"',
                $iconMarkup,
            );
        }

        $injectedIcon = $accountView
            ? '<span class="travel-plan-icon-slot" aria-hidden="true">' . $iconMarkup . '</span>'
            : $iconMarkup;

        return \preg_replace(
            '/(<(?:section|article|aside|div)\b[^>]*>)/',
            '$1' . $injectedIcon,
            $html,
            1,
        ) ?? $html;
    }

    private function applyPageBreakClass(string $html, mixed $startOnNewPage, bool $prependPdfPageBreak = false): string
    {
        if (!$this->contentHelper->isTruthy($startOnNewPage)) {
            return $html;
        }

        if ($prependPdfPageBreak) {
            return '<pagebreak />' . $html;
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

    /**
     * @param list<array{title: string, level: int}> $tableOfContents
     */
    private function addTableOfContentsEntry(array &$tableOfContents, mixed $title, int $level, int $tableOfContentsDepth): void
    {
        if ($level >= $tableOfContentsDepth) {
            return;
        }

        $title = $this->contentHelper->tableOfContentsTitle($title);

        if (null === $title) {
            return;
        }

        $tableOfContents[] = [
            'title' => $title,
            'level' => $level,
        ];
    }

    private function withTableOfContentsEntry(
        string $html,
        mixed $title,
        int $level,
        bool $accountView,
        int $tableOfContentsDepth,
    ): string {
        if ($accountView || $level >= $tableOfContentsDepth) {
            return $html;
        }

        $title = $this->contentHelper->tableOfContentsTitle($title);

        if (null === $title) {
            return $html;
        }

        $entry = \sprintf(
            '<tocentry content="%s" level="%d" />',
            \htmlspecialchars($title, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'),
            $level,
        );
        $pageBreak = '<pagebreak />';

        if (\str_starts_with($html, $pageBreak)) {
            return $pageBreak . $entry . \substr($html, \strlen($pageBreak));
        }

        return $entry . $html;
    }

    private function withPdfChunkMarker(string $html, bool $accountView): string
    {
        return $accountView ? $html : $html . '<!--PDF-CHUNK-->';
    }

    private function suppressInitialPdfPageBreak(string $html): string
    {
        $html = \preg_replace('/^\s*<pagebreak\s*\/>\s*/i', '', $html, 1) ?? $html;

        return \preg_replace('/\s+travel-plan-page-break-before\b/', '', $html, 1) ?? $html;
    }

    /**
     * @param array<string, mixed> $block
     *
     * @return array<string, mixed>
     */
    private function prepareImageBlock(array $block, bool $pdfView): array
    {
        $block['imageSrc'] = $this->contentHelper->mediaImageSrc($block['image'] ?? null, $pdfView);

        return $block;
    }

    private function iconMarkup(mixed $icon, bool $accountView): ?string
    {
        if ($accountView) {
            return $this->iconSvg($icon);
        }

        $iconSrc = $this->iconPngDataUri($icon);

        if (null === $iconSrc) {
            return null;
        }

        return \sprintf('<img class="travel-plan-icon" src="%s" alt="">', $iconSrc);
    }

    private function iconPngDataUri(mixed $icon): ?string
    {
        if (!\is_string($icon)) {
            return null;
        }

        $dataUri = $this->iconResolver->getPdfIconBadgeDataUri($icon);

        return '' === $dataUri ? null : $dataUri;
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

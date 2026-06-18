<?php

declare(strict_types=1);

namespace App\TravelPlan\Renderer;

use App\Entity\TravelPlan;
use App\Entity\TravelPlanFeedback;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
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
    ): string
    {
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
    ): string
    {
        $content = $travelPlan->getContent();
        $renderedSections = [];

        foreach ($content['sections'] ?? [] as $sectionIndex => $section) {
            if (!\is_array($section)) {
                continue;
            }

            $type = $section['type'] ?? null;

            if (!\is_string($type) || !isset(self::SECTION_TEMPLATES[$type])) {
                continue;
            }

            if ('route_overview' === $type && \is_array($section['routeStops'] ?? null)) {
                $section['routeStops'] = array_map(function (mixed $stop) use ($accountView): mixed {
                    if (!\is_array($stop)) {
                        return $stop;
                    }

                    $stop['_iconMarkup'] = $this->iconMarkup(
                        $stop['icon'] ?? 'map',
                        $accountView,
                    );

                    return $stop;
                }, $section['routeStops']);
            }

            $context = [
                'section' => $section,
                'accountView' => $accountView,
                'travelPlan' => $travelPlan,
                'feedbackEnabled' => $feedbackEnabled,
            ];

            if ('day' === $type) {
                $context['renderedBlocks'] = $this->renderDayBlocks(
                    $section['blocks'] ?? [],
                    $travelPlan,
                    (int) $sectionIndex,
                    $accountView,
                    $feedbackByPath,
                    $feedbackEnabled,
                );
            }

            $renderedSection = $this->twig->render(self::SECTION_TEMPLATES[$type], $context);
            $renderedSections[] = [
                'html' => $this->prependIcon(
                    $renderedSection,
                    $section['icon'] ?? self::DEFAULT_SECTION_ICONS[$type] ?? null,
                    $accountView,
                ),
                'blockPath' => \sprintf('sections[%d]', $sectionIndex),
                'blockType' => $type,
                'feedback' => $feedbackEnabled
                    ? ($feedbackByPath[\sprintf('sections[%d]', $sectionIndex)] ?? null)
                    : null,
            ];
        }

        return $this->twig->render('travel_plan/render/base.html.twig', [
            'travelPlan' => $travelPlan,
            'intro' => \is_array($content['intro'] ?? null) ? $content['intro'] : [],
            'tripProfile' => \is_array($content['tripProfile'] ?? null) ? $content['tripProfile'] : [],
            'renderedSections' => $renderedSections,
            'logoSrc' => $this->assetDataUri('assets/images/pdf/logo-pdf.png', 'image/png'),
            'accountView' => $accountView,
            'feedbackEnabled' => $feedbackEnabled,
        ]);
    }

    /**
     * @return list<array{html: string, blockPath: string, blockType: string}>
     */
    private function renderDayBlocks(
        mixed $blocks,
        TravelPlan $travelPlan,
        int $sectionIndex,
        bool $accountView,
        array $feedbackByPath,
        bool $feedbackEnabled,
    ): array
    {
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
                    'block' => $this->withTimeRange($block),
                    'accountView' => $accountView,
                    'travelPlan' => $travelPlan,
                ],
            );
            $renderedBlocks[] = [
                'html' => $this->prependIcon(
                    $renderedBlock,
                    $block['icon'] ?? self::DEFAULT_DAY_BLOCK_ICONS[$type],
                    $accountView,
                ),
                'blockPath' => \sprintf('sections[%d].blocks[%d]', $sectionIndex, $blockIndex),
                'blockType' => $type,
                'feedback' => $feedbackEnabled
                    ? ($feedbackByPath[
                        \sprintf('sections[%d].blocks[%d]', $sectionIndex, $blockIndex)
                    ] ?? null)
                    : null,
            ];
        }

        return $renderedBlocks;
    }

    private function prependIcon(string $html, mixed $icon, bool $accountView): string
    {
        $iconMarkup = $this->iconMarkup($icon, $accountView);

        if (null === $iconMarkup) {
            return $html;
        }

        return preg_replace(
            '/(<(?:section|article|aside)\b[^>]*>)/',
            '$1'.$iconMarkup,
            $html,
            1,
        ) ?? $html;
    }

    /**
     * @param array<string, mixed> $block
     *
     * @return array<string, mixed>
     */
    private function withTimeRange(array $block): array
    {
        $startTime = $this->normalizeTime($block['startTime'] ?? $block['time'] ?? null);
        $endTime = $this->normalizeTime($block['endTime'] ?? null);

        $block['startTime'] = $startTime;
        $block['endTime'] = $endTime;
        $block['timeRangeLabel'] = match (true) {
            '' === $startTime => '',
            '' === $endTime || $endTime === $startTime => $startTime,
            default => \sprintf('%s - %s', $startTime, $endTime),
        };

        return $block;
    }

    private function normalizeTime(mixed $time): string
    {
        if ($time instanceof \DateTimeInterface) {
            return $time->format('H:i');
        }

        if (!\is_scalar($time)) {
            return '';
        }

        $time = \trim((string) $time);

        if (1 !== \preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/D', $time, $matches)) {
            return '';
        }

        return \sprintf('%02d:%s', (int) $matches[1], $matches[2]);
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

        return \sprintf(
            '<img class="travel-plan-icon" src="%s" alt="">',
            $iconSrc,
        );
    }

    private function iconSvg(mixed $icon): ?string
    {
        if (!\is_string($icon) || 1 !== preg_match('/^[a-z0-9][a-z0-9-]*$/', $icon)) {
            return null;
        }

        $path = $this->projectDir.'/assets/images/icons/'.$icon.'.svg';

        if (!is_file($path) || false === $contents = file_get_contents($path)) {
            return null;
        }

        $contents = str_replace(
            ['currentColor', '#000000', '#000'],
            '#d4af37',
            $contents,
        );
        $contents = preg_replace(
            '/<svg\b/',
            '<svg class="travel-plan-icon" color="#d4af37"',
            $contents,
            1,
        ) ?? $contents;

        return $contents;
    }

    private function iconPngDataUri(mixed $icon): ?string
    {
        if (!\is_string($icon) || 1 !== preg_match('/^[a-z0-9][a-z0-9-]*$/', $icon)) {
            return null;
        }

        return $this->assetDataUri(
            'assets/images/pdf/icons/'.$icon.'.png',
            'image/png',
        );
    }

    private function assetDataUri(string $relativePath, string $mimeType): ?string
    {
        $path = $this->projectDir.'/'.$relativePath;

        if (!is_file($path) || false === $contents = file_get_contents($path)) {
            return null;
        }

        return 'data:'.$mimeType.';base64,'.base64_encode($contents);
    }
}

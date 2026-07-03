<?php

declare(strict_types=1);

namespace App\TravelPlan\Renderer;

use App\Entity\TravelPlan;
use App\Entity\TravelPlanFeedback;
use App\Service\IconResolver;
use App\Service\TravelCompanion\CompanionContentHelper;
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
        private IconResolver $iconResolver,
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

        foreach (CompanionContentHelper::destinations($content) as $destinationData) {
            $destinationIndex = $destinationData['destinationIndex'];
            $destination = $destinationData['destination'];
            $destination = $this->preparePdfRichText($destination, $accountView);
            $destinationPath = \sprintf('destinations[%d]', $destinationIndex);
            $renderedDestination = $this->twig->render(self::SECTION_TEMPLATES['destination'], [
                'section' => $destination,
                'accountView' => $accountView,
                'travelPlan' => $travelPlan,
                'feedbackEnabled' => $feedbackEnabled,
            ]);
            $renderedSections[] = [
                'html' => $this->applyPageBreakClass(
                    $this->prependIcon(
                        $renderedDestination,
                        $destination['icon'] ?? self::DEFAULT_SECTION_ICONS['destination'],
                        $accountView,
                    ),
                    $destination['startOnNewPage'] ?? false,
                ),
                'blockPath' => $destinationPath,
                'blockType' => 'destination',
                'feedback' => $feedbackEnabled
                    ? ($feedbackByPath[$destinationPath] ?? null)
                    : null,
            ];

            foreach ($destination['sections'] ?? [] as $sectionIndex => $section) {
                if (!\is_array($section)) {
                    continue;
                }

                $section = $this->preparePdfRichText($section, $accountView);

                $type = $section['type'] ?? null;

                if (!\is_string($type) || !isset(self::SECTION_TEMPLATES[$type]) || 'destination' === $type) {
                    continue;
                }

                if ('route_overview' === $type && \is_array($section['routeStops'] ?? null)) {
                    $section['routeStops'] = \array_map(function (mixed $stop) use ($accountView): mixed {
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
                        $destinationIndex,
                        (int) $sectionIndex,
                        $accountView,
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
                $renderedSections[] = [
                    'html' => $this->applyPageBreakClass(
                        $this->prependIcon(
                            $renderedSection,
                            $section['icon'] ?? self::DEFAULT_SECTION_ICONS[$type] ?? null,
                            $accountView,
                        ),
                        $section['startOnNewPage'] ?? false,
                    ),
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

            $renderedBlock = $this->twig->render(
                self::DAY_BLOCK_TEMPLATES[$type],
                [
                    'block' => $this->withTimeRange($block),
                    'accountView' => $accountView,
                    'travelPlan' => $travelPlan,
                ],
            );
            $blockPath = \sprintf(
                'destinations[%d].sections[%d].blocks[%d]',
                $destinationIndex,
                $sectionIndex,
                $blockIndex,
            );
            $renderedBlocks[] = [
                'html' => $this->applyPageBreakClass(
                    $this->prependIcon(
                        $renderedBlock,
                        $block['icon'] ?? self::DEFAULT_DAY_BLOCK_ICONS[$type],
                        $accountView,
                    ),
                    $block['startOnNewPage'] ?? false,
                ),
                'blockPath' => $blockPath,
                'blockType' => $type,
                'feedback' => $feedbackEnabled ? ($feedbackByPath[$blockPath] ?? null) : null,
            ];
        }

        return $renderedBlocks;
    }

    /**
     * @param array<string, mixed> $block
     *
     * @return array<string, mixed>
     */
    private function preparePdfRichText(array $block, bool $accountView): array
    {
        if ($accountView || !\is_string($block['text'] ?? null)) {
            return $block;
        }

        $block['text'] = $this->normalizePdfRichText($block['text']);

        return $block;
    }

    private function normalizePdfRichText(string $html): string
    {
        if (!\str_contains($html, '<table') && !\str_contains($html, '<p')) {
            return $html;
        }

        $previousUseErrors = \libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8"><div>' . $html . '</div>',
            \LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD,
        );
        \libxml_clear_errors();
        \libxml_use_internal_errors($previousUseErrors);

        if (!$loaded) {
            return $html;
        }

        $xpath = new \DOMXPath($dom);
        $tables = [];

        foreach ($xpath->query('//figure[contains(concat(" ", normalize-space(@class), " "), " table ")]') ?: [] as $figure) {
            $tables[] = $figure;
        }

        foreach ($xpath->query('//table[not(ancestor::figure[contains(concat(" ", normalize-space(@class), " "), " table ")])]') ?: [] as $table) {
            $tables[] = $table;
        }

        foreach ($tables as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $replacement = $this->buildPdfTableReplacement($dom, $xpath, $node);

            if (null === $replacement || null === $node->parentNode) {
                continue;
            }

            $node->parentNode->replaceChild($replacement, $node);
        }

        $this->addPdfParagraphSpacers($dom, $xpath);

        $root = $dom->documentElement;

        if (!$root instanceof \DOMElement) {
            return $html;
        }

        $normalizedHtml = '';

        foreach ($root->childNodes as $childNode) {
            $normalizedHtml .= $dom->saveHTML($childNode);
        }

        return $normalizedHtml;
    }

    private function addPdfParagraphSpacers(\DOMDocument $dom, \DOMXPath $xpath): void
    {
        $paragraphs = [];

        foreach ($xpath->query('//p[not(ancestor::table[contains(concat(" ", normalize-space(@class), " "), " travel-plan-editor-table ")])]') ?: [] as $paragraph) {
            if ($paragraph instanceof \DOMElement) {
                $paragraphs[] = $paragraph;
            }
        }

        foreach ($paragraphs as $paragraph) {
            if (!$this->hasFollowingRichTextSibling($paragraph) || null === $paragraph->parentNode) {
                continue;
            }

            $spacer = $dom->createElement('div', "\xc2\xa0");
            $spacer->setAttribute('class', 'travel-plan-paragraph-spacer');
            $paragraph->parentNode->insertBefore($spacer, $paragraph->nextSibling);
        }
    }

    private function hasFollowingRichTextSibling(\DOMElement $element): bool
    {
        for ($sibling = $element->nextSibling; null !== $sibling; $sibling = $sibling->nextSibling) {
            if ($sibling instanceof \DOMText && '' === \trim(\str_replace("\xc2\xa0", ' ', $sibling->textContent))) {
                continue;
            }

            if (!$sibling instanceof \DOMElement) {
                continue;
            }

            if ('div' === $sibling->tagName && 'travel-plan-paragraph-spacer' === $sibling->getAttribute('class')) {
                continue;
            }

            return true;
        }

        return false;
    }

    private function buildPdfTableReplacement(\DOMDocument $dom, \DOMXPath $xpath, \DOMElement $node): ?\DOMElement
    {
        $table = 'table' === $node->tagName ? $node : $xpath->query('.//table', $node)?->item(0);

        if (!$table instanceof \DOMElement) {
            return null;
        }

        $rows = [];

        foreach ($xpath->query('.//tr', $table) ?: [] as $row) {
            if (!$row instanceof \DOMElement) {
                continue;
            }

            $cells = [];

            foreach ($xpath->query('./th|./td', $row) ?: [] as $cell) {
                if (!$cell instanceof \DOMElement) {
                    continue;
                }

                if ('' === \trim(\str_replace("\xc2\xa0", ' ', $cell->textContent))) {
                    continue;
                }

                $cells[] = $cell;
            }

            if ([] !== $cells) {
                $rows[] = $cells;
            }
        }

        if (\count($rows) < 2) {
            return null;
        }

        $headings = $rows[0];
        $bodies = $rows[1];
        $columnCount = \min(\count($headings), \count($bodies));

        if (0 === $columnCount) {
            return null;
        }

        $replacementTable = $dom->createElement('table');
        $replacementTable->setAttribute('class', 'travel-plan-editor-table');

        $headingRow = $dom->createElement('tr');
        $bodyRow = $dom->createElement('tr');
        $columnWidth = \sprintf('%.4F%%', 100 / $columnCount);

        for ($index = 0; $index < $columnCount; ++$index) {
            $heading = $dom->createElement('th');
            $heading->setAttribute('style', 'width: ' . $columnWidth . ';');
            $this->appendChildren($dom, $heading, $headings[$index]);
            $headingRow->appendChild($heading);

            $body = $dom->createElement('td');
            $body->setAttribute('style', 'width: ' . $columnWidth . ';');
            $this->appendChildren($dom, $body, $bodies[$index]);
            $bodyRow->appendChild($body);
        }

        $replacementTable->appendChild($headingRow);
        $replacementTable->appendChild($bodyRow);

        return $replacementTable;
    }

    private function appendChildren(\DOMDocument $dom, \DOMElement $target, \DOMElement $source): void
    {
        foreach ($source->childNodes as $childNode) {
            $target->appendChild($dom->importNode($childNode, true));
        }
    }

    private function prependIcon(string $html, mixed $icon, bool $accountView): string
    {
        $iconMarkup = $this->iconMarkup($icon, $accountView);

        if (null === $iconMarkup) {
            return $html;
        }

        if (!$accountView) {
            return $html;
        }

        return \preg_replace(
            '/(<(?:section|article|aside)\b[^>]*>)/',
            '$1' . $iconMarkup,
            $html,
            1,
        ) ?? $html;
    }

    private function applyPageBreakClass(string $html, mixed $startOnNewPage): string
    {
        if (!$this->isTruthy($startOnNewPage)) {
            return $html;
        }

        return \preg_replace(
            '/(<(?:section|article|aside)\b[^>]*class=")([^"]*)(")/',
            '$1$2 travel-plan-page-break-before$3',
            $html,
            1,
        ) ?? $html;
    }

    private function isTruthy(mixed $value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        if (\is_int($value)) {
            return 1 === $value;
        }

        if (\is_string($value)) {
            return \in_array(\strtolower(\trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
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
        if (!\is_string($icon)) {
            return null;
        }

        $svg = $this->iconResolver->getSvgIcon($icon);

        return '' === $svg ? null : $svg;
    }

    private function iconPngDataUri(mixed $icon): ?string
    {
        if (!\is_string($icon)) {
            return null;
        }

        $dataUri = $this->iconResolver->getPdfIconDataUri($icon);

        return '' === $dataUri ? null : $dataUri;
    }

    private function assetDataUri(string $relativePath, string $mimeType): ?string
    {
        $path = $this->projectDir . '/' . $relativePath;

        if (!\is_file($path) || false === $contents = \file_get_contents($path)) {
            return null;
        }

        return 'data:' . $mimeType . ';base64,' . \base64_encode($contents);
    }
}

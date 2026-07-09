<?php

declare(strict_types=1);

namespace App\TravelPlan\Renderer;

use App\Entity\TravelPlan;
use App\Service\IconResolver;
use App\TravelPlan\Pdf\TravelPlanPdfStyle;
use Twig\Environment;

/**
 * Bouwt de HTML voor de PDF-weergave van een reisplan. Contentlogica en
 * view-modellen leven hier; alle markup en inline styling staat in de
 * Twig-templates onder templates/travel_plan/pdf/ (met de tokens uit
 * TravelPlanPdfStyle). De account-weergave heeft zijn eigen renderer.
 */
final readonly class TravelPlanPdfRenderer
{
    private const SECTION_ICONS = [
        'destination' => 'map',
        'route_overview' => 'map',
        'practical_info' => 'info',
        'checklist' => 'list-check',
        'budget_note' => 'wallet',
        'personal_note' => 'heart',
        'free_text' => 'info',
        'image' => 'image',
    ];

    private const BLOCK_ICONS = [
        'activity' => 'compass',
        'accommodation' => 'bed',
        'transport' => 'car',
        'meal' => 'utensils',
        'tip' => 'lightbulb',
        'note' => 'sticky-note',
        'free_text' => 'info',
    ];

    private const BLOCK_ACTION_LABELS = [
        'accommodation' => 'Bekijk accommodatie',
        'activity' => 'Bekijk of reserveer',
        'meal' => 'Bekijk restaurant',
        'transport' => 'Bekijk vervoer',
    ];

    /** Sectietypes die (nog) via de gedeelde templates renderen. */
    private const SHARED_TEMPLATE_SECTIONS = [
        'route_overview' => 'travel_plan/render/sections/route_overview.html.twig',
        'checklist' => 'travel_plan/render/sections/checklist.html.twig',
        'budget_note' => 'travel_plan/render/sections/budget_note.html.twig',
        'personal_note' => 'travel_plan/render/sections/personal_note.html.twig',
        'image' => 'travel_plan/render/sections/image.html.twig',
    ];

    public function __construct(
        private Environment $twig,
        private IconResolver $iconResolver,
        private TravelPlanContentHelper $helper,
    ) {
    }

    public function render(TravelPlan $travelPlan): string
    {
        $content = $travelPlan->getContent();
        $tokens = TravelPlanPdfStyle::tokens();
        $tocDepth = $this->helper->tableOfContentsDepth($content['tripProfile']['showTableOfContents'] ?? null);
        $sections = [];
        $tableOfContents = [];

        foreach (($content['destinations'] ?? []) as $destination) {
            if (!\is_array($destination)) {
                continue;
            }

            $type = $destination['type'] ?? null;

            if ('image' === $type) {
                $sections[] = $this->sectionViewModel($destination, 0, $tocDepth, $tableOfContents, $tokens, $travelPlan);

                continue;
            }

            if ('destination' !== $type) {
                continue;
            }

            $sections[] = $this->sectionViewModel($destination, 0, $tocDepth, $tableOfContents, $tokens, $travelPlan);

            foreach ($destination['sections'] ?? [] as $section) {
                if (!\is_array($section) || !\is_string($section['type'] ?? null) || 'destination' === $section['type']) {
                    continue;
                }

                $sections[] = $this->sectionViewModel($section, 1, $tocDepth, $tableOfContents, $tokens, $travelPlan);
            }
        }

        $request = $travelPlan->getTravelRequest();
        $contact = $request?->getContact();
        $customerName = null !== $contact
            ? \trim(\implode(' ', \array_filter([$contact->getFirstName(), $contact->getLastName()])))
            : '';
        $tripProfile = \is_array($content['tripProfile'] ?? null) ? $content['tripProfile'] : [];

        return $this->twig->render('travel_plan/pdf/document.html.twig', [
            'travelPlan' => $travelPlan,
            'customerName' => $customerName,
            'intro' => \is_array($content['intro'] ?? null) ? $content['intro'] : [],
            'tripProfile' => $tripProfile,
            'logoSrc' => $this->helper->assetDataUri('assets/images/pdf/logo-pdf.png', 'image/png'),
            'sections' => \array_filter($sections),
            'tableOfContents' => $tableOfContents,
            't' => $tokens,
        ]);
    }

    /**
     * @param array<string, mixed> $section
     * @param list<array{title: string, level: int}> $tableOfContents
     * @param array<string, string> $tokens
     *
     * @return array{html: string, tocTitle: ?string, tocLevel: int, startOnNewPage: bool}|null
     */
    private function sectionViewModel(
        array $section,
        int $level,
        int $tocDepth,
        array &$tableOfContents,
        array $tokens,
        TravelPlan $travelPlan,
    ): ?array {
        $type = (string) ($section['type'] ?? '');
        $title = $section['title'] ?? ('image' === $type ? 'Afbeelding' : '');
        $tocTitle = null;

        if ($level < $tocDepth && null !== $tocTitle = $this->helper->tableOfContentsTitle($title)) {
            $tableOfContents[] = ['title' => $tocTitle, 'level' => $level];
        }

        $html = match ($type) {
            'destination' => $this->renderDestination($section, $tokens),
            'day' => $this->renderDayGroup($section, $tokens),
            'free_text', 'practical_info' => $this->renderFrame($section, $tokens),
            default => $this->renderSharedSection($type, $section, $travelPlan),
        };

        if (null === $html) {
            return null;
        }

        return [
            'html' => $html,
            'tocTitle' => $tocTitle,
            'tocLevel' => $level,
            'startOnNewPage' => $this->helper->isTruthy($section['startOnNewPage'] ?? false),
        ];
    }

    /**
     * @param array<string, mixed> $section
     * @param array<string, string> $tokens
     */
    private function renderDestination(array $section, array $tokens): string
    {
        $textHtml = $this->normalizeRichText((string) ($section['text'] ?? ''));
        $location = \implode(', ', \array_filter([
            \trim((string) ($section['city'] ?? '')),
            \trim((string) ($section['region'] ?? '')),
            \trim((string) ($section['country'] ?? '')),
        ]));

        return $this->twig->render('travel_plan/pdf/destination.html.twig', [
            'section' => [
                'title' => \trim((string) ($section['title'] ?? '')),
                'location' => $location,
                'textHtml' => $textHtml,
                'barColor' => $this->barColor($section),
                'iconSrc' => $this->iconBadge($section['icon'] ?? null, self::SECTION_ICONS['destination']),
                'keep' => \mb_strlen(\strip_tags($textHtml)) <= TravelPlanPdfStyle::KEEP_TOGETHER_MAX_CHARS,
            ],
            't' => $tokens,
        ]);
    }

    /**
     * @param array<string, mixed> $section
     * @param array<string, string> $tokens
     */
    private function renderDayGroup(array $section, array $tokens): string
    {
        $meta = \array_filter([
            '' !== \trim((string) ($section['dayNumber'] ?? '')) ? 'Dag ' . $section['dayNumber'] : null,
            '' !== \trim((string) ($section['dateLabel'] ?? '')) ? (string) $section['dateLabel'] : null,
        ]);
        $introHtml = \trim((string) ($section['intro'] ?? ''));

        if ('' !== $introHtml) {
            $introHtml = \str_replace(
                ['<p>', '<li>'],
                ['<p style="color: ' . TravelPlanPdfStyle::TEXT_LIGHT . ';">', '<li style="color: ' . TravelPlanPdfStyle::TEXT_LIGHT . ';">'],
                $introHtml,
            );
        }

        $blocks = [];

        foreach (($section['blocks'] ?? []) as $block) {
            if (\is_array($block) && \is_string($block['type'] ?? null) && isset(self::BLOCK_ICONS[$block['type']])) {
                $blocks[] = $this->blockViewModel($block);
            }
        }

        // Eerste (korte, niet-brekende) kaart bindt aan de header; daarna
        // per kaart segmentflags zodat de template een domme loop blijft.
        $boundCard = null;

        if ([] !== $blocks && !$blocks[0]['flow']) {
            $boundCard = \array_shift($blocks);
        }

        $rows = [];
        $count = \count($blocks);

        for ($i = 0; $i < $count; ++$i) {
            $solo = $blocks[$i]['flow'];
            $rows[] = [
                'solo' => $solo,
                'block' => $blocks[$i],
                'isFirstOfSegment' => false, // hieronder bepaald
                'isLastOfSegment' => !$solo && ($i + 1 >= $count || $blocks[$i + 1]['flow']),
            ];
        }

        // Een nieuw segment begint na een solo-blok, of bij de allereerste
        // rij wanneer er geen kaart aan de header gebonden is.
        $previousWasSolo = null === $boundCard;

        foreach ($rows as $index => $row) {
            if ($row['solo']) {
                $previousWasSolo = true;

                continue;
            }

            $rows[$index]['isFirstOfSegment'] = $previousWasSolo;
            $previousWasSolo = false;
        }

        $variant = $this->colorVariant($section);

        return $this->twig->render('travel_plan/pdf/day_group.html.twig', [
            'group' => [
                'isPrimary' => 'primary' === $variant,
                'isSecondary' => 'secondary' === $variant,
                'meta' => [] !== $meta ? \implode(' · ', $meta) : null,
                'title' => \trim((string) ($section['title'] ?? '')),
                'introHtml' => $introHtml,
                'iconSrc' => $this->iconBadge($section['icon'] ?? null, null),
                'headerOnly' => null === $boundCard && [] === $rows,
                'boundCard' => $boundCard,
                'boundCloses' => [] === $rows || $rows[0]['solo'],
                'rows' => $rows,
            ],
            't' => $tokens,
        ]);
    }

    /**
     * @param array<string, mixed> $block
     *
     * @return array<string, mixed>
     */
    private function blockViewModel(array $block): array
    {
        $block = $this->helper->withTimeRange($block);
        $type = (string) $block['type'];
        $isTip = 'tip' === $type;
        $textHtml = \trim((string) ($block['text'] ?? ''));

        if ($isTip && '' !== $textHtml) {
            $textHtml = \str_replace(
                ['<p>', '<li>'],
                ['<p style="color: ' . TravelPlanPdfStyle::TEXT_LIGHT . ';">', '<li style="color: ' . TravelPlanPdfStyle::TEXT_LIGHT . ';">'],
                $textHtml,
            );
        }

        // Kleurvariant per blok (CMS-veld "PDF kleur"); tips zonder
        // expliciete keuze behouden hun donkere (primary) uitstraling.
        $variant = $this->colorVariant($block);

        if ('auto' === $variant && $isTip) {
            $variant = 'primary';
        }

        return [
            'type' => $type,
            'isTip' => $isTip,
            'isPrimary' => 'primary' === $variant,
            'isSecondary' => 'secondary' === $variant,
            'isGold' => 'gold' === $variant,
            'flow' => \mb_strlen(\strip_tags($textHtml)) > TravelPlanPdfStyle::KEEP_TOGETHER_MAX_CHARS
                || $this->helper->isTruthy($block['startOnNewPage'] ?? false),
            'startOnNewPage' => $this->helper->isTruthy($block['startOnNewPage'] ?? false),
            'title' => \trim((string) ($block['title'] ?? '')),
            'timeRangeLabel' => $block['timeRangeLabel'] ?? '',
            'timeLabel' => \trim((string) ($block['timeLabel'] ?? '')),
            'location' => \trim((string) ($block['location'] ?? '')),
            'textHtml' => $textHtml,
            'bookingUrl' => \trim((string) ($block['bookingUrl'] ?? '')),
            'actionLabel' => self::BLOCK_ACTION_LABELS[$type] ?? 'Bekijk',
            'iconSrc' => $this->iconBadge($block['icon'] ?? null, self::BLOCK_ICONS[$type]),
        ];
    }

    /**
     * @param array<string, mixed> $section
     * @param array<string, string> $tokens
     */
    private function renderFrame(array $section, array $tokens): string
    {
        return $this->twig->render('travel_plan/pdf/frame_section.html.twig', [
            'section' => [
                'title' => \trim((string) ($section['title'] ?? '')),
                'textHtml' => $this->normalizeRichText((string) ($section['text'] ?? '')),
            ],
            't' => $tokens,
        ]);
    }

    /**
     * @param array<string, mixed> $section
     */
    private function renderSharedSection(string $type, array $section, TravelPlan $travelPlan): ?string
    {
        $template = self::SHARED_TEMPLATE_SECTIONS[$type] ?? null;

        if (null === $template) {
            return null;
        }

        if ('image' === $type) {
            $section['imageSrc'] = $this->helper->mediaImageSrc($section['image'] ?? null, true);
        }

        if ('route_overview' === $type && \is_array($section['routeStops'] ?? null)) {
            $section['routeStops'] = \array_map(function (mixed $stop): mixed {
                if (!\is_array($stop)) {
                    return $stop;
                }

                $iconSrc = $this->iconBadge($stop['icon'] ?? null, 'map');
                $stop['_iconMarkup'] = null !== $iconSrc
                    ? '<img class="travel-plan-icon" src="' . $iconSrc . '" alt="">'
                    : null;

                return $stop;
            }, $section['routeStops']);
        }

        if (\is_string($section['text'] ?? null)) {
            $section['text'] = $this->normalizeRichText($section['text']);
        }

        $html = $this->twig->render($template, [
            'section' => $section,
            'accountView' => false,
            'travelPlan' => $travelPlan,
            'feedbackEnabled' => false,
        ]);

        // Sectie-icoon rechtsboven injecteren (zoals in de account-weergave).
        $iconSrc = $this->iconBadge($section['icon'] ?? null, self::SECTION_ICONS[$type] ?? null);

        if (null !== $iconSrc) {
            $html = \preg_replace(
                '/(<(?:section|article|aside|div|table)\b[^>]*>)/',
                '$1<img class="travel-plan-icon travel-plan-icon--section" src="' . $iconSrc . '" alt="">',
                $html,
                1,
            ) ?? $html;
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $section
     */
    private function barColor(array $section): string
    {
        $variants = TravelPlanPdfStyle::variants();
        $palette = $variants[$this->colorVariant($section)] ?? $variants['default'];

        return (string) ($palette['bar'] ?? TravelPlanPdfStyle::GOLD);
    }

    /**
     * @param array<string, mixed> $element
     */
    private function colorVariant(array $element): string
    {
        return \strtolower(\trim((string) ($element['colorVariant'] ?? 'auto')));
    }

    private function iconBadge(mixed $icon, ?string $default): ?string
    {
        $type = \is_string($icon) && '' !== \trim($icon) ? $icon : $default;

        if (null === $type) {
            return null;
        }

        $dataUri = $this->iconResolver->getPdfIconBadgeDataUri($type);

        return '' === $dataUri ? null : $dataUri;
    }

    /**
     * Normaliseert CKEditor-HTML voor mPDF: tabellen naar het vaste
     * twee-rijen-formaat en spacers tussen alinea's.
     */
    private function normalizeRichText(string $html): string
    {
        $html = \trim($html);

        if ('' === $html || (!\str_contains($html, '<table') && !\str_contains($html, '<p'))) {
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

            $replacement = $this->buildTableReplacement($dom, $xpath, $node);

            if (null === $replacement || null === $node->parentNode) {
                continue;
            }

            $node->parentNode->replaceChild($replacement, $node);
        }

        $this->addParagraphSpacers($dom, $xpath);

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

    private function addParagraphSpacers(\DOMDocument $dom, \DOMXPath $xpath): void
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

    private function buildTableReplacement(\DOMDocument $dom, \DOMXPath $xpath, \DOMElement $node): ?\DOMElement
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
}

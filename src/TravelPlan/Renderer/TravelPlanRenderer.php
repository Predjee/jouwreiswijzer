<?php

declare(strict_types=1);

namespace App\TravelPlan\Renderer;

use App\Entity\TravelPlan;
use App\Entity\TravelPlanFeedback;
use App\Service\IconResolver;
use Sulu\Bundle\MediaBundle\Api\Media;
use Sulu\Bundle\MediaBundle\Media\Exception\MediaNotFoundException;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
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
        private MediaManagerInterface $mediaManager,
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
        $tableOfContents = [];
        $tableOfContentsDepth = $this->tableOfContentsDepth($content['tripProfile']['showTableOfContents'] ?? null);

        foreach (($content['destinations'] ?? []) as $destinationIndex => $destination) {
            if (!\is_array($destination)) {
                continue;
            }

            $type = $destination['type'] ?? null;

            if ('image' === $type) {
                $destination = $this->prepareImageBlock($destination, !$accountView);
                $destinationPath = \sprintf('destinations[%d]', (int) $destinationIndex);
                $renderedImage = $this->twig->render(self::SECTION_TEMPLATES['image'], [
                    'section' => $destination,
                    'accountView' => $accountView,
                    'travelPlan' => $travelPlan,
                    'feedbackEnabled' => $feedbackEnabled,
                ]);
                $renderedImage = $this->applyPageBreakClass($renderedImage, $destination['startOnNewPage'] ?? false, !$accountView);
                $renderedSections[] = [
                    'html' => $this->withPdfChunkMarker($this->withTableOfContentsEntry($renderedImage, $destination['title'] ?? 'Afbeelding', 0, $accountView, $tableOfContentsDepth), $accountView),
                    'blockPath' => $destinationPath,
                    'blockType' => 'image',
                    'tocTitle' => $this->tableOfContentsTitle($destination['title'] ?? 'Afbeelding'),
                    'tocLevel' => 0,
                    'feedback' => $feedbackEnabled ? ($feedbackByPath[$destinationPath] ?? null) : null,
                ];
                $this->addTableOfContentsEntry($tableOfContents, $destination['title'] ?? 'Afbeelding', 0, $tableOfContentsDepth);

                continue;
            }

            if ('destination' !== $type) {
                continue;
            }

            $destination = $this->preparePdfRichText($destination, $accountView);
            $destinationPath = \sprintf('destinations[%d]', $destinationIndex);
            $this->addTableOfContentsEntry($tableOfContents, $destination['title'] ?? '', 0, $tableOfContentsDepth);
            $renderedDestination = $this->twig->render(self::SECTION_TEMPLATES['destination'], [
                'section' => $destination,
                'accountView' => $accountView,
                'travelPlan' => $travelPlan,
                'feedbackEnabled' => $feedbackEnabled,
            ]);
            $renderedDestination = $this->applyPageBreakClass(
                $this->prependIcon(
                    $renderedDestination,
                    $this->iconOrDefault($destination['icon'] ?? null, self::DEFAULT_SECTION_ICONS['destination']),
                    $accountView,
                    true,
                ),
                $destination['startOnNewPage'] ?? false,
                !$accountView,
            );

            if (!$accountView) {
                $renderedDestination = $this->wrapPdfHeroKeep($renderedDestination);
            }

            $renderedSections[] = [
                'html' => $this->withPdfChunkMarker($this->withTableOfContentsEntry($renderedDestination, $destination['title'] ?? '', 0, $accountView, $tableOfContentsDepth), $accountView),
                'blockPath' => $destinationPath,
                'blockType' => 'destination',
                'tocTitle' => $this->tableOfContentsTitle($destination['title'] ?? ''),
                'tocLevel' => 0,
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
                        $this->iconOrDefault($stop['icon'] ?? null, 'map'),
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
                $this->addTableOfContentsEntry($tableOfContents, $section['title'] ?? '', 1, $tableOfContentsDepth);

                if ('day' === $type && !$accountView) {
                    // Dag-groepen worden voor de PDF als één tabel gebouwd:
                    // doorlopende gele achtergrond over pagina's, header
                    // gebonden aan de eerste kaart en nooit lege hulzen.
                    // Het icoon zit al in de groepsheader verwerkt.
                    $renderedSection = $this->applyPageBreakClass(
                        $this->buildPdfDayGroup(
                            $section,
                            $context['renderedBlocks'] ?? [],
                            $this->iconMarkup(
                                $this->iconOrDefault($section['icon'] ?? null, self::DEFAULT_SECTION_ICONS[$type] ?? null),
                                false,
                            ),
                        ),
                        $section['startOnNewPage'] ?? false,
                        true,
                    );
                } else {
                    $renderedSection = $this->twig->render(self::SECTION_TEMPLATES[$type], $context);
                    $renderedSection = $this->applyPageBreakClass(
                        $this->prependIcon(
                            $renderedSection,
                            $this->iconOrDefault($section['icon'] ?? null, self::DEFAULT_SECTION_ICONS[$type] ?? null),
                            $accountView,
                            true,
                        ),
                        $section['startOnNewPage'] ?? false,
                        !$accountView,
                    );

                    if (!$accountView) {
                        $renderedSection = $this->wrapPdfHeroKeep($renderedSection);
                    }
                }

                $renderedSections[] = [
                    'html' => $this->withPdfChunkMarker($this->withTableOfContentsEntry($renderedSection, $section['title'] ?? '', 1, $accountView, $tableOfContentsDepth), $accountView),
                    'blockPath' => $sectionPath,
                    'blockType' => $type,
                    'tocTitle' => $this->tableOfContentsTitle($section['title'] ?? ''),
                    'tocLevel' => 1,
                    'feedback' => $feedbackEnabled ? ($feedbackByPath[$sectionPath] ?? null) : null,
                ];
            }
        }

        return $this->twig->render('travel_plan/render/base.html.twig', [
            'travelPlan' => $travelPlan,
            'intro' => \is_array($content['intro'] ?? null) ? $content['intro'] : [],
            'tripProfile' => \is_array($content['tripProfile'] ?? null) ? $content['tripProfile'] : [],
            'renderedSections' => $renderedSections,
            'tableOfContents' => $tableOfContents,
            'showTableOfContents' => $tableOfContentsDepth > 0,
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
            $renderedBlock = $this->prependIcon(
                $renderedBlock,
                $this->iconOrDefault($block['icon'] ?? null, self::DEFAULT_DAY_BLOCK_ICONS[$type]),
                $accountView,
            );

            if (!$accountView) {
                $renderedBlock = $this->wrapPdfDayBlockBody($renderedBlock);
            }

            // NB: voor dagblokken géén losse <pagebreak />-tag: die zou
            // midden in de geneste day-section-div terechtkomen en de
            // HTML-structuur breken bij het chunk-splitsen. De klasse
            // .travel-plan-page-break-before (page-break-before: always)
            // werkt wél binnen geneste divs.
            $renderedBlock = $this->applyPageBreakClass(
                $renderedBlock,
                $block['startOnNewPage'] ?? false,
                false,
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

    private function prependIcon(string $html, mixed $icon, bool $accountView, bool $sectionIcon = false): string
    {
        $iconMarkup = $this->iconMarkup($icon, $accountView);

        if (null === $iconMarkup) {
            return $html;
        }

        if (!$accountView && $sectionIcon) {
            // Sectie-icoon rechtsboven in de hero, zoals in de account-omgeving.
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

    /**
     * Bouwt een dag-sectie voor de PDF als één tabel ("groep"): rij 1 is de
     * navy header (gebonden aan de eerste kaart, zodat die nooit los
     * onderaan een pagina bungelt), elke volgende kaart een eigen rij met de
     * gele groepsachtergrond. Tabellen zijn mPDF's enige betrouwbare pad:
     * ze pagineren tussen rijen, schilderen achtergronden altijd (ook op
     * vervolgpagina's) en laten geen lege hulzen achter. Prijs: de groeps-
     * container zelf heeft rechte hoeken (radius werkt niet op tabellen);
     * de kaarten erin behouden hun ronding.
     *
     * Kaarten met "toon op nieuwe pagina" of langer dan één pagina kunnen
     * niet in een tabelrij (rijen zijn atomair); de groep wordt daar
     * gesplitst en zo'n kaart staat er los tussen.
     *
     * @param list<array{html: string}> $renderedBlocks
     */
    private function buildPdfDayGroup(array $section, array $renderedBlocks, ?string $icon): string
    {
        // Zijranden op de cellen (geen tabelrand): de afgeronde boven- en
        // onderkant komen van cap-afbeeldingen die er naadloos op aansluiten.
        $sideBorders = 'border-left: 0.2mm solid #e3ddcd; border-right: 0.2mm solid #e3ddcd;';
        $groupOpen = '<table class="travel-plan-day-group" style="width: 100%; border-collapse: collapse; page-break-inside: auto;">';
        $creamCell = '<tr><td style="padding: 3mm 4mm 0; background-color: #f8f5ef; vertical-align: top; ' . $sideBorders . '">';
        // Header (navy) als geneste tabel in de eerste groepsrij.
        $meta = \array_filter([
            '' !== \trim((string) ($section['dayNumber'] ?? '')) ? 'Dag ' . $section['dayNumber'] : null,
            '' !== \trim((string) ($section['dateLabel'] ?? '')) ? (string) $section['dateLabel'] : null,
        ]);
        $headerInner = '';

        if ([] !== $meta) {
            $headerInner .= '<p class="travel-plan-day__meta" style="color: #d4af62;">'
                . \htmlspecialchars(\implode(' · ', $meta), \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8')
                . '</p>';
        }

        if (\is_string($section['title'] ?? null) && '' !== \trim($section['title'])) {
            $headerInner .= '<h2 style="margin: 0 0 2mm; padding: 0; border: 0; color: #ffffff; font-size: 17.5pt;">'
                . \htmlspecialchars($section['title'], \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8')
                . '</h2>';
        }

        if (\is_string($section['intro'] ?? null) && '' !== \trim($section['intro'])) {
            $intro = \str_replace(
                ['<p>', '<li>'],
                ['<p style="color: #f7f4ee;">', '<li style="color: #f7f4ee;">'],
                $section['intro'],
            );
            $headerInner .= '<div class="travel-plan-section__content travel-plan-day__intro" style="color: #e7e0d3;">' . $intro . '</div>';
        }

        $iconCell = '';

        if (\is_string($icon) && '' !== $icon) {
            $icon = \str_replace(
                'class="travel-plan-icon"',
                'class="travel-plan-icon" style="float: none; display: block; width: 7.5mm; height: 7.5mm; margin: 0;"',
                $icon,
            );
            $iconCell = '<td style="width: 15mm; padding: 5mm 6mm 0 0; vertical-align: top; background-color: #12213d;">' . $icon . '</td>';
        }

        $blocks = \array_values($renderedBlocks);

        // Geen vervolg (geen kaarten)? Dan geen gele groep: alleen een
        // losstaand, afgerond navy blok — als top-level div schildert die
        // zijn achtergrond mét ronding betrouwbaar (destination-patroon).
        if ([] === $blocks) {
            return '<div class="travel-plan-section travel-plan-section--day" style="background-color: #12213d; border-radius: 2mm; padding: 0; color: #f7f4ee;">'
                . '<table style="width: 100%; border-collapse: collapse; page-break-inside: avoid;"><tr>'
                . '<td style="padding: 5mm 2.5mm 4.5mm 6mm; vertical-align: top; color: #f7f4ee;">' . $headerInner . '</td>'
                . $iconCell
                . '</tr></table>'
                . '</div>';
        }

        // Navy op de cellen (niet op de tabel): de cap-hoeken zijn
        // transparant en tonen zo het wit van de rij eronder.
        $header = '<table style="width: 100%; border-collapse: collapse; page-break-inside: avoid;">'
            . '<tr>'
            . '<td style="padding: 5mm 2.5mm 4.5mm 6mm; vertical-align: top; color: #f7f4ee; background-color: #12213d;">' . $headerInner . '</td>'
            . $iconCell
            . '</tr></table>';

        // Verdeel de kaarten in groepssegmenten en losstaande blokken (een
        // kaart met "toon op nieuwe pagina" of langer dan een pagina staat
        // los tussen de segmenten).
        $items = [];

        foreach ($blocks as $block) {
            $blockHtml = $block['html'] ?? '';

            if ('' === $blockHtml) {
                continue;
            }

            $items[] = [
                'solo' => \str_contains($blockHtml, 'travel-plan-page-break-before')
                    || \str_contains($blockHtml, 'travel-plan-block__main--flow'),
                'html' => $blockHtml,
            ];
        }

        // Witte cellen: de transparante hoeken van de cap-afbeeldingen tonen
        // paginawit (echte ronding). Caps zitten ÍN de eerste/laatste rij van
        // elk segment, zodat ze met de content meeverhuizen en nooit als
        // losse ronding op een volgende pagina achterblijven.
        // Geen zijranden op de buitenste rij-td: zo loopt de navy header van
        // buitenrand tot buitenrand. De gouden zijranden (en optioneel de
        // onderafsluiting na het laatste blok) zitten op de creamWrap-cel.
        $whiteCell = '<tr><td style="padding: 0; background-color: #f8f5ef; vertical-align: top;">';
        $creamWrap = static fn (string $inner, string $padding, bool $closeBottom = false): string => '<table style="width: 100%; border-collapse: collapse;"><tr>'
            . '<td style="padding: ' . $padding . '; vertical-align: top; background-color: #f8f5ef; ' . $sideBorders . ($closeBottom ? ' border-bottom: 0.2mm solid #e3ddcd;' : '') . '">'
            . $inner
            . '</td></tr></table>';

        // Rij 1: navy top-cap + header, met de eerste (korte) kaart eraan
        // gebonden zodat de header nooit los onderaan een pagina staat.
        $boundCard = null;

        if (isset($items[0]) && !$items[0]['solo']) {
            $boundCard = $items[0]['html'];
            \array_shift($items);
        }

        $firstSegmentContinues = isset($items[0]) && !$items[0]['solo'];
        // Celinhoud in één neutrale div: mPDF berekent "100%" voor een
        // afbeelding (cap) en een tabel (header) anders wanneer ze los in
        // een cel staan; binnen dezelfde div krijgen ze dezelfde breedte.
        $rowInner = $header;

        if (null !== $boundCard) {
            $rowInner .= $creamWrap($boundCard, $firstSegmentContinues ? '3mm 4mm 0' : '3mm 4mm 3mm', !$firstSegmentContinues);
        }

        // NB: de groep is bewust hoekloos (navy header + gele container):
        // dit is in mPDF de enige artefactvrije vorm — cap-strips en
        // border-radius op tabellen renderen niet betrouwbaar. Ronding zit
        // in de kaarten, hero's, frames en badges (div-radius/PNG: bewezen).
        $html = '<div class="travel-plan-section travel-plan-section--day">'
            . $groupOpen
            . $whiteCell . '<div style="margin: 0; padding: 0;">' . $rowInner . '</div>'
            . '</td></tr>';
        $tableOpen = true;

        if (!$firstSegmentContinues) {
            $html .= '</table>';
            $tableOpen = false;
        }

        $count = \count($items);

        for ($i = 0; $i < $count; ++$i) {
            $item = $items[$i];

            if ($item['solo']) {
                // Losstaand blok tussen de segmenten (mag splitsen of op een
                // nieuwe pagina beginnen).
                $html .= $item['html'];

                continue;
            }

            $isFirstOfSegment = !$tableOpen;
            $isLastOfSegment = !isset($items[$i + 1]) || $items[$i + 1]['solo'];

            if ($isFirstOfSegment) {
                $html .= $groupOpen;
                $tableOpen = true;
            }

            if ($isFirstOfSegment || $isLastOfSegment) {
                $html .= $whiteCell
                    . '<div style="margin: 0; padding: 0;">'
                    . $creamWrap($item['html'], '3mm 4mm ' . ($isLastOfSegment ? '3mm' : '0'), $isLastOfSegment)
                    . '</div></td></tr>';
            } else {
                $html .= $creamCell . $item['html'] . '</td></tr>';
            }

            if ($isLastOfSegment) {
                $html .= '</table>';
                $tableOpen = false;
            }
        }

        if ($tableOpen) {
            $html .= '</table>';
        }

        return $html . '</div>';
    }

    /**
     * Houdt hero-koppen (destination-sectie en day-header) op één pagina via
     * een één-rij-tabel. Een splitsende bg-div laat mPDF daarna namelijk
     * achtergrondkleuren "vergeten" (verbleekte blokken), en avoid op divs
     * triggert de kwt-buffer-bug. Het sectie-icoon verhuist mee naar een
     * eigen kolom, omdat floats binnen tabelcellen niet werken.
     */
    private function wrapPdfHeroKeep(string $html): string
    {
        $prefix = '';
        $pageBreak = '<pagebreak />';
        $trimmed = \trim($html);

        if (\str_starts_with($trimmed, $pageBreak)) {
            $prefix = $pageBreak;
            $trimmed = \trim(\substr($trimmed, \strlen($pageBreak)));
        }

        // Destination-hero: hele sectie-inhoud bijeenhouden.
        if (1 === \preg_match(
            '/^(<div class="[^"]*travel-plan-section--destination[^"]*"[^>]*>)(<img[^>]*>)?(.*)(<\/div>)$/s',
            $trimmed,
            $matches,
        )) {
            [, $openingTag, $icon, $inner, $closingTag] = $matches;

            if (\mb_strlen(\strip_tags($inner)) > 3200) {
                return $html;
            }

            // Padding verhuist naar de tabelcellen: zo laat een hero die
            // doorschuift geen hoge lege huls achter op de vorige pagina.
            $openingTag = \str_replace('padding: 6mm 7mm 5mm;', 'padding: 0;', $openingTag);

            // Transparante tabel: de destination-div is top-level en
            // schildert de afgeronde navy achtergrond zelf betrouwbaar.
            return $prefix . $openingTag . $this->heroKeepTable($inner, $icon, '') . $closingTag;
        }

        // Day-sectie: alleen de header bijeenhouden; het icoon (vóór de
        // header geïnjecteerd) verhuist mee de tabel in.
        if (1 === \preg_match(
            '/^(<div class="[^"]*travel-plan-section--day[^"]*"[^>]*>)\s*(<img[^>]*>)?\s*(<div class="travel-plan-day__header"[^>]*>)(.*?)(<\/div>\s*<div class="travel-plan-day__blocks)/s',
            $trimmed,
            $matches,
        )) {
            [$fullMatch, $openingTag, $icon, $headerTag, $headerInner, $tail] = $matches;

            if (\mb_strlen(\strip_tags($headerInner)) > 3200) {
                return $html;
            }

            // Padding verhuist naar de tabelcellen (zie destination-branch).
            $headerTag = \str_contains($headerTag, 'style="')
                ? \str_replace('style="', 'style="padding: 0; ', $headerTag)
                : \substr($headerTag, 0, -1) . ' style="padding: 0;">';

            // Bind de eerste kaart aan de header (extra rij in de keep-
            // tabel): een header mag nooit alleen onderaan een pagina
            // achterblijven terwijl de kaarten pas op de volgende beginnen.
            $rest = \substr($trimmed, \strlen($fullMatch));
            $extraRow = '';

            if (
                1 === \preg_match(
                    '/<div class="[^"]*travel-plan-block\b[^"]*"[^>]*>.*?<\/table><\/div>(?=\s*(?:<div|<\/div>))/s',
                    $rest,
                    $cardMatch,
                    \PREG_OFFSET_CAPTURE,
                )
                && !\str_contains($cardMatch[0][0], 'travel-plan-page-break-before')
            ) {
                $card = $cardMatch[0][0];
                $rest = \substr_replace($rest, '', $cardMatch[0][1], \strlen($card));
                $colspan = '' !== $icon ? 2 : 1;
                $extraRow = '<tr><td colspan="' . $colspan . '" style="background-color: #f8f5ef; padding: 4mm 4mm 3mm; vertical-align: top;">'
                    . $card
                    . '</td></tr>';
            }

            $replacement = $openingTag
                . $headerTag
                . $this->heroKeepTable($headerInner, $icon, '#12213d', $extraRow)
                . $tail;

            return $prefix . $replacement . $rest;
        }

        // Day-sectie ZONDER blokken (alleen een header): mPDF schildert de
        // achtergrond van zo'n kale div niet betrouwbaar — ook hier is de
        // keep-tabel dus nodig.
        if (1 === \preg_match(
            '/^(<div class="[^"]*travel-plan-section--day[^"]*"[^>]*>)\s*(<img[^>]*>)?\s*(<div class="travel-plan-day__header"[^>]*>)(.*)(<\/div>)\s*(<\/div>)\s*$/s',
            $trimmed,
            $matches,
        )) {
            [, $openingTag, $icon, $headerTag, $headerInner, $headerClose, $sectionClose] = $matches;

            if (\mb_strlen(\strip_tags($headerInner)) > 3200) {
                return $html;
            }

            $headerTag = \str_contains($headerTag, 'style="')
                ? \str_replace('style="', 'style="padding: 0; ', $headerTag)
                : \substr($headerTag, 0, -1) . ' style="padding: 0;">';

            return $prefix
                . $openingTag
                . $headerTag
                . $this->heroKeepTable($headerInner, $icon)
                . $headerClose
                . $sectionClose;
        }

        return $html;
    }

    /**
     * Elke sectie moet als eigen chunk naar mPDF (aparte WriteHTML-call):
     * binnen grote chunks raakt mPDF's schilderstatus corrupt en verbleken
     * achtergronden verderop op de pagina. Voorheen dwongen de <tocentry>-
     * tags dit impliciet af; sinds de inhoudsopgave op diepte 1 staat is
     * deze expliciete marker nodig (TravelPlanPdfGenerator splitst erop).
     */
    private function withPdfChunkMarker(string $html, bool $accountView): string
    {
        return $accountView ? $html : $html . '<!--PDF-CHUNK-->';
    }

    /**
     * Paddings en achtergrond inline: mPDF's tabel-CSS-cascade laat
     * celklasse-regels verliezen van table-scoped regels, en geneste divs
     * schilderen hun achtergrond niet betrouwbaar — tabellen wel.
     *
     * @param string $background Inline tabelachtergrond ('' = transparant,
     *                           voor de destination-hero: die div is
     *                           top-level en schildert zijn afgeronde navy
     *                           achtergrond wél betrouwbaar zelf).
     * @param string $extraRow  Optionele extra rij (de eerste kaart, zodat
     *                          header en eerste kaart samen blijven).
     */
    private function heroKeepTable(string $inner, string $icon, string $background = '#12213d', string $extraRow = ''): string
    {
        $backgroundStyle = '' !== $background
            ? ' style="background-color: ' . $background . ';"'
            : '';
        $iconCell = '' !== $icon
            ? '<td class="travel-plan-hero__icon-cell" style="width: 16mm; padding: 5.5mm 7mm 0 0; vertical-align: top;">' . $icon . '</td>'
            : '';

        return '<table class="travel-plan-hero__keep"' . $backgroundStyle . '><tr>'
            . '<td class="travel-plan-hero__body" style="padding: 5.5mm 2.5mm 4.5mm 7mm; vertical-align: top;">' . $inner . '</td>'
            . $iconCell
            . '</tr>'
            . $extraRow
            . '</table>';
    }

    private function wrapPdfDayBlockBody(string $html): string
    {
        $html = \trim($html);

        if (1 !== \preg_match(
            '/^(<div\b[^>]*\btravel-plan-block\b[^>]*>)(<img class="travel-plan-icon"[^>]*>)(.*)(<\/div>)$/s',
            $html,
            $matches,
        )) {
            return $html;
        }

        [, $openingTag, $iconMarkup, $body, $closingTag] = $matches;
        $splitAt = \strlen($body);

        foreach ([
            '<div class="travel-plan-section__content travel-plan-block__content"',
            '<p class="travel-plan-block__action"',
        ] as $needle) {
            $position = \strpos($body, $needle);

            if (false !== $position) {
                $splitAt = \min($splitAt, $position);
            }
        }

        $header = \trim(\substr($body, 0, $splitAt));
        $content = \trim(\substr($body, $splitAt));

        // Blokken die (mogelijk) hoger zijn dan een pagina mogen niet in een
        // keep-together-tabel: mPDF kan een tabelrij nooit splitsen en gooit
        // dan een exception. Die blokken behouden de oude div-opbouw.
        if (\mb_strlen(\strip_tags($body)) > 3200) {
            $openingTag = \str_replace('class="', 'class="travel-plan-block--flow ', $openingTag);
            $main = '' !== $header
                ? '<div class="travel-plan-block__main travel-plan-block__main--flow"><div class="travel-plan-block__header">' . $iconMarkup . $header . '</div>' . $content . '</div>'
                : '<div class="travel-plan-block__main travel-plan-block__main--flow"><div class="travel-plan-block__header">' . $iconMarkup . '</div>' . $content . '</div>';

            return $openingTag . $main . $closingTag;
        }

        $main = '' !== $header
            ? '<div class="travel-plan-block__main"><div class="travel-plan-block__header">' . $header . '</div>' . $content . '</div>'
            : '<div class="travel-plan-block__main">' . $content . '</div>';

        // Eén-rij-tabel binnen de kaart: mPDF houdt tabellen wél betrouwbaar
        // op één pagina (page-break-inside: avoid op divs triggert de
        // kwt-buffer-bug, en een splitsende bg-div laat mPDF daarna alle
        // achtergronden "vergeten"). Icoon in eigen kolom: floats werken
        // niet in tabelcellen.
        // Kaartstijl volledig inline op de div: ronde rand (border-radius
        // werkt op divs) en een fractie padding zodat de vierkante
        // tabelvulling BINNEN de curve blijft (geen rechthoek-overshoot).
        // Hoek-PNG-strips zijn bewust verwijderd: mPDF rendert geneste
        // strip-tabellen nooit op volledige breedte ("pukkeltjes").
        $isTip = \str_contains($openingTag, 'travel-plan-block--tip');
        $background = $isTip ? '#12213d' : '#ffffff';
        $edge = $isTip ? '#12213d' : '#e1e4ea';
        // Rand op de layout-tabel (divranden in tabelcellen rendert mPDF
        // niet). Tabellen kunnen geen border-radius hebben; de kaartronding
        // is daarmee binnen mPDF niet haalbaar — de account-radius (0.35rem)
        // is dermate subtiel dat strak-recht het dichtstbijzijnde stabiele
        // equivalent is.
        return $openingTag
            . '<table class="travel-plan-block__layout" style="background-color: ' . $background . '; border: 0.25mm solid ' . $edge . ';"><tr>'
            . '<td class="travel-plan-block__icon-cell" style="width: 13mm; padding: 3.6mm 2.5mm 3.4mm 5mm; vertical-align: top;">' . $iconMarkup . '</td>'
            . '<td class="travel-plan-block__body-cell" style="padding: 3.6mm 5mm 3.4mm 0; vertical-align: top;">' . $main . '</td>'
            . '</tr></table>'
            . $closingTag;
    }

    private function applyPageBreakClass(string $html, mixed $startOnNewPage, bool $prependPdfPageBreak = false): string
    {
        if (!$this->isTruthy($startOnNewPage)) {
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

        $title = $this->tableOfContentsTitle($title);

        if (null === $title) {
            return;
        }

        $tableOfContents[] = [
            'title' => $title,
            'level' => $level,
        ];
    }

    private function withTableOfContentsEntry(string $html, mixed $title, int $level, bool $accountView, int $tableOfContentsDepth): string
    {
        if ($accountView || $level >= $tableOfContentsDepth) {
            return $html;
        }

        $title = $this->tableOfContentsTitle($title);

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

    private function tableOfContentsTitle(mixed $title): ?string
    {
        if (!\is_scalar($title)) {
            return null;
        }

        $title = \trim((string) $title);

        return '' === $title ? null : $title;
    }

    /**
     * @param array<string, mixed> $block
     *
     * @return array<string, mixed>
     */
    private function prepareImageBlock(array $block, bool $pdfView): array
    {
        $block['imageSrc'] = $this->mediaImageSrc($block['image'] ?? null, $pdfView);

        return $block;
    }

    private function mediaImageSrc(mixed $image, bool $pdfView): ?string
    {
        $media = $this->resolveMedia($image);

        if (null !== $media) {
            $storagePath = $this->mediaStoragePath($media);

            if ($pdfView && null !== $storagePath) {
                return $storagePath;
            }

            $format = $media->getFormats()['text-media-landscape'] ?? $media->getFormats()['package-card'] ?? null;

            if (\is_scalar($format)) {
                $formatUrl = (string) $format;
                $formatPath = $this->imagePathFromUrl($formatUrl);

                if ($pdfView && null !== $formatPath) {
                    return $formatPath;
                }

                return $formatUrl;
            }

            $url = $media->getUrl();

            if ('' !== $url) {
                $path = $this->imagePathFromUrl($url);

                if ($pdfView && null !== $path) {
                    return $path;
                }

                return $url;
            }
        }

        if (\is_array($image)) {
            $thumbnail = $image['thumbnails']['text-media-landscape'] ?? $image['thumbnails']['package-card'] ?? $image['thumbnails']['large'] ?? $image['thumbnails']['default'] ?? null;
            $url = \is_scalar($thumbnail) ? (string) $thumbnail : null;

            if (null === $url && \is_scalar($image['url'] ?? null)) {
                $url = (string) $image['url'];
            }

            if (null === $url && \is_scalar($image['path'] ?? null)) {
                $url = (string) $image['path'];
            }

            if (null !== $url) {
                $path = $this->imagePathFromUrl($url);

                if ($pdfView && null !== $path) {
                    return $path;
                }

                return $url;
            }
        }

        if (\is_scalar($image)) {
            $url = (string) $image;
            $path = $this->imagePathFromUrl($url);

            if ($pdfView && null !== $path) {
                return $path;
            }

            return $url;
        }

        return null;
    }

    private function resolveMedia(mixed $image): ?Media
    {
        $id = null;

        if (\is_array($image) && \is_scalar($image['id'] ?? null)) {
            $id = (int) $image['id'];
        } elseif (\is_scalar($image) && '' !== \trim((string) $image)) {
            $id = (int) $image;
        }

        if (null === $id || $id <= 0) {
            return null;
        }

        try {
            return $this->mediaManager->getById($id, 'nl');
        } catch (MediaNotFoundException) {
            return null;
        }
    }

    private function mediaStoragePath(Media $media): ?string
    {
        $storageOptions = $media->getStorageOptions();
        $segment = $storageOptions['segment'] ?? null;
        $fileName = $storageOptions['fileName'] ?? null;

        if (!\is_scalar($segment) || !\is_scalar($fileName)) {
            return null;
        }

        $path = $this->projectDir . '/var/storage/default/' . \trim((string) $segment, '/') . '/' . \ltrim((string) $fileName, '/');

        if (!\is_file($path)) {
            return null;
        }

        return $path;
    }

    private function imagePathFromUrl(string $url): ?string
    {
        $path = \parse_url($url, \PHP_URL_PATH);

        if (!\is_string($path) || '' === $path) {
            return null;
        }

        $localPath = $this->projectDir . '/public/' . \ltrim($path, '/');

        if (!\is_file($localPath)) {
            return null;
        }

        return $localPath;
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

    private function tableOfContentsDepth(mixed $value): int
    {
        if (\is_bool($value)) {
            return $value ? 2 : 0;
        }

        if (\is_int($value)) {
            return match ($value) {
                1 => 1,
                2 => 2,
                default => 0,
            };
        }

        if (!\is_string($value)) {
            return 0;
        }

        return match (\strtolower(\trim($value))) {
            'one', '1', 'destination', 'destinations', 'een laag' => 1,
            'two', '2', 'true', 'yes', 'on', 'twee lagen' => 2,
            default => 0,
        };
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

    private function iconPngDataUri(mixed $icon): ?string
    {
        if (!\is_string($icon)) {
            return null;
        }

        // Icoon inclusief in de PNG gebakken ronde badge (account-stijl);
        // ronde divs/floats zijn in mPDF geen optie.
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


    private function assetDataUri(string $relativePath, string $mimeType): ?string
    {
        $path = $this->projectDir . '/' . $relativePath;

        if (!\is_file($path) || false === $contents = \file_get_contents($path)) {
            return null;
        }

        return 'data:' . $mimeType . ';base64,' . \base64_encode($contents);
    }
}

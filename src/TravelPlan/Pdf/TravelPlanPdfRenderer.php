<?php

declare(strict_types=1);

namespace App\TravelPlan\Pdf;

use App\Service\IconResolver;
use App\TravelPlan\Renderer\TravelPlanContentHelper;
use Twig\Environment;

final readonly class TravelPlanPdfRenderer
{
    public function __construct(
        private Environment $twig,
        private IconResolver $iconResolver,
        private TravelPlanContentHelper $contentHelper,
        private TravelPlanPdfRichTextNormalizer $richTextNormalizer,
    ) {
    }

    /**
     * @param array<string, mixed> $block
     *
     * @return array<string, mixed>
     */
    public function prepareRichText(array $block, bool $accountView): array
    {
        if ($accountView || !\is_string($block['text'] ?? null)) {
            return $block;
        }

        $block['text'] = $this->richTextNormalizer->normalize($block['text']);

        return $block;
    }

    /**
     * @param array<string, mixed> $section
     */
    public function renderDayGroup(array $section, mixed $blocks, mixed $icon): string
    {
        return $this->twig->render('travel_plan/pdf/day_group.html.twig', [
            'group' => $this->buildDayGroup($section, $blocks, $icon),
            't' => TravelPlanPdfStyle::tokens(),
        ]);
    }

    public function wrapHeroKeep(string $html): string
    {
        $prefix = '';

        if (\str_starts_with($html, '<pagebreak />')) {
            $prefix = '<pagebreak />';
            $html = \substr($html, \strlen($prefix));
        }

        $trimmed = \trim($html);

        if (1 === \preg_match(
            '/^(<div class="[^"]*travel-plan-section--destination[^"]*"[^>]*>)(<img[^>]*>)?(.*)(<\/div>)$/s',
            $trimmed,
            $matches,
        )) {
            [, $openingTag, $icon, $inner, $closingTag] = $matches;

            if (\mb_strlen(\strip_tags($inner)) > TravelPlanPdfStyle::KEEP_TOGETHER_MAX_CHARS) {
                return $html;
            }

            $openingTag = \str_replace('padding: 6mm 7mm 5mm;', 'padding: 0;', $openingTag);

            return $prefix . $openingTag . $this->heroKeepTable($inner, $icon, '') . $closingTag;
        }

        if (1 === \preg_match(
            '/^(<div class="[^"]*travel-plan-section--day[^"]*"[^>]*>)\s*(<img[^>]*>)?\s*(<div class="travel-plan-day__header"[^>]*>)(.*?)(<\/div>\s*<div class="travel-plan-day__blocks)/s',
            $trimmed,
            $matches,
        )) {
            [$fullMatch, $openingTag, $icon, $headerTag, $headerInner, $tail] = $matches;

            if (\mb_strlen(\strip_tags($headerInner)) > TravelPlanPdfStyle::KEEP_TOGETHER_MAX_CHARS) {
                return $html;
            }

            $headerTag = \str_contains($headerTag, 'style="')
                ? \str_replace('style="', 'style="padding: 0; ', $headerTag)
                : \substr($headerTag, 0, -1) . ' style="padding: 0;">';
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
                $extraRow = '<tr><td colspan="' . $colspan . '" style="background-color: ' . TravelPlanPdfStyle::CREAM . '; padding: 4mm 4mm 3mm; vertical-align: top;">' . $card . '</td></tr>';
            }

            $replacement = $openingTag . $headerTag . $this->heroKeepTable($headerInner, $icon, TravelPlanPdfStyle::NAVY, $extraRow) . $tail;

            return $prefix . $replacement . $rest;
        }

        if (1 === \preg_match(
            '/^(<div class="[^"]*travel-plan-section--day[^"]*"[^>]*>)\s*(<img[^>]*>)?\s*(<div class="travel-plan-day__header"[^>]*>)(.*?)(<\/div>)(\s*<\/div>)$/s',
            $trimmed,
            $matches,
        )) {
            [, $openingTag, $icon, $headerTag, $headerInner, $headerClose, $sectionClose] = $matches;

            if (\mb_strlen(\strip_tags($headerInner)) > TravelPlanPdfStyle::KEEP_TOGETHER_MAX_CHARS) {
                return $html;
            }

            $headerTag = \str_contains($headerTag, 'style="')
                ? \str_replace('style="', 'style="padding: 0; ', $headerTag)
                : \substr($headerTag, 0, -1) . ' style="padding: 0;">';

            return $prefix . $openingTag . $headerTag . $this->heroKeepTable($headerInner, $icon) . $headerClose . $sectionClose;
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $section
     *
     * @return array<string, mixed>
     */
    private function buildDayGroup(array $section, mixed $blocks, mixed $icon): array
    {
        $pdfBlocks = $this->preparePdfBlocks($blocks);
        $boundCard = null;

        if ([] !== $pdfBlocks && !$pdfBlocks[0]['solo']) {
            $boundCard = $pdfBlocks[0]['block'];
            \array_shift($pdfBlocks);
        }

        $boundCloses = null !== $boundCard && !$this->hasNormalBlockBeforeSolo($pdfBlocks);
        $rows = [];
        $normalSegmentOpen = null !== $boundCard && !$boundCloses;

        foreach ($pdfBlocks as $index => $item) {
            if ($item['solo']) {
                $rows[] = [
                    'solo' => true,
                    'block' => $item['block'],
                    'isFirstOfSegment' => false,
                    'isLastOfSegment' => false,
                ];
                $normalSegmentOpen = false;

                continue;
            }

            $nextIsNormal = isset($pdfBlocks[$index + 1]) && !$pdfBlocks[$index + 1]['solo'];
            $isFirstOfSegment = !$normalSegmentOpen;
            $isLastOfSegment = !$nextIsNormal;
            $normalSegmentOpen = !$isLastOfSegment;

            $rows[] = [
                'solo' => false,
                'block' => $item['block'],
                'isFirstOfSegment' => $isFirstOfSegment,
                'isLastOfSegment' => $isLastOfSegment,
            ];
        }

        $meta = \array_filter([
            '' !== \trim((string) ($section['dayNumber'] ?? '')) ? 'Dag ' . $section['dayNumber'] : null,
            '' !== \trim((string) ($section['dateLabel'] ?? '')) ? (string) $section['dateLabel'] : null,
        ]);

        return [
            'meta' => \implode(' · ', $meta),
            'title' => \is_scalar($section['title'] ?? null) ? (string) $section['title'] : '',
            'introHtml' => \is_string($section['intro'] ?? null) ? $this->prepareHeaderIntroHtml($section['intro']) : '',
            'iconSrc' => $this->iconPngDataUri($icon),
            'headerOnly' => null === $boundCard && [] === $rows,
            'boundCard' => $boundCard,
            'boundCloses' => $boundCloses,
            'rows' => $rows,
        ];
    }

    /**
     * @return list<array{solo: bool, block: array<string, mixed>}>
     */
    private function preparePdfBlocks(mixed $blocks): array
    {
        if (!\is_array($blocks)) {
            return [];
        }

        $prepared = [];

        foreach ($blocks as $block) {
            if (!\is_array($block)) {
                continue;
            }

            $type = $block['type'] ?? null;

            if (!\is_string($type)) {
                continue;
            }

            $block = $this->contentHelper->withTimeRange($block);
            $block = $this->prepareRichText($block, false);
            $block['type'] = $type;
            $block['textHtml'] = \is_string($block['text'] ?? null) ? $block['text'] : '';
            $block['isTip'] = 'tip' === $type;
            $block['actionLabel'] = $this->actionLabel($type);
            $block['iconSrc'] = $this->iconPngDataUri(
                $this->iconOrDefault($block['icon'] ?? null, $this->defaultDayBlockIcon($type)),
            );
            $block['startOnNewPage'] = $this->contentHelper->isTruthy($block['startOnNewPage'] ?? false);
            $block['flow'] = \mb_strlen(\strip_tags($block['textHtml'])) > TravelPlanPdfStyle::KEEP_TOGETHER_MAX_CHARS;

            $prepared[] = [
                'solo' => $block['flow'] || $block['startOnNewPage'],
                'block' => $block,
            ];
        }

        return $prepared;
    }

    /**
     * @param list<array{solo: bool, block: array<string, mixed>}> $items
     */
    private function hasNormalBlockBeforeSolo(array $items): bool
    {
        foreach ($items as $item) {
            if ($item['solo']) {
                return false;
            }

            return true;
        }

        return false;
    }

    private function heroKeepTable(string $inner, string $icon, string $background = TravelPlanPdfStyle::NAVY, string $extraRow = ''): string
    {
        $backgroundStyle = '' !== $background ? ' style="background-color: ' . $background . ';"' : '';
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

    private function prepareHeaderIntroHtml(string $html): string
    {
        $html = $this->richTextNormalizer->normalize($html);

        return \preg_replace_callback(
            '/<(p|li)\b([^>]*)>/i',
            static function (array $matches): string {
                $attributes = $matches[2];

                if (1 === \preg_match('/\sstyle="([^"]*)"/i', $attributes, $styleMatches)) {
                    $style = \rtrim($styleMatches[1]);

                    if ('' !== $style && !\str_ends_with($style, ';')) {
                        $style .= ';';
                    }

                    $attributes = \preg_replace(
                        '/\sstyle="[^"]*"/i',
                        ' style="' . $style . ' color: ' . TravelPlanPdfStyle::TEXT_SOFT . ';"',
                        $attributes,
                        1,
                    ) ?? $attributes;

                    return '<' . $matches[1] . $attributes . '>';
                }

                return '<' . $matches[1] . $attributes . ' style="color: ' . TravelPlanPdfStyle::TEXT_SOFT . ';">';
            },
            $html,
        ) ?? $html;
    }

    private function iconPngDataUri(mixed $icon): ?string
    {
        if (!\is_string($icon)) {
            return null;
        }

        $dataUri = $this->iconResolver->getPdfIconBadgeDataUri($icon);

        return '' === $dataUri ? null : $dataUri;
    }

    private function iconOrDefault(mixed $icon, string $default): string
    {
        if (\is_string($icon) && '' !== \trim($icon)) {
            return $icon;
        }

        return $default;
    }

    private function defaultDayBlockIcon(string $type): string
    {
        return match ($type) {
            'activity' => 'compass',
            'accommodation' => 'bed',
            'transport' => 'car',
            'meal' => 'utensils',
            'tip' => 'lightbulb',
            'note' => 'sticky-note',
            default => 'info',
        };
    }

    private function actionLabel(string $type): string
    {
        return match ($type) {
            'accommodation' => 'Bekijk accommodatie',
            'transport' => 'Bekijk vervoer',
            default => 'Bekijk link',
        };
    }
}

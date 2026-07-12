<?php

declare(strict_types=1);

namespace App\TravelPlan\View;

use App\Service\IconResolver;
use App\TravelPlan\BlockPath;
use App\TravelPlan\Content\BlockType;
use App\TravelPlan\Content\ColorVariant;
use App\TravelPlan\Content\DayBlock;
use App\TravelPlan\Content\Destination;
use App\TravelPlan\Content\Section;
use App\TravelPlan\Content\SectionType;
use App\TravelPlan\Content\TravelPlanContent;
use App\TravelPlan\Pdf\TravelPlanPdfRichTextNormalizer;
use App\TravelPlan\Renderer\TravelPlanContentHelper;
use App\TravelPlan\TravelPlanStyle;

final readonly class TravelPlanViewFactory
{
    public function __construct(
        private IconResolver $iconResolver,
        private TravelPlanContentHelper $helper,
        private TravelPlanPdfRichTextNormalizer $richTextNormalizer,
    ) {
    }

    public function theme(): ThemeView
    {
        return new ThemeView(
            navy: TravelPlanStyle::NAVY,
            gold: TravelPlanStyle::GOLD,
            goldLight: TravelPlanStyle::GOLD_LIGHT,
            textBody: TravelPlanStyle::TEXT_BODY,
            textSoft: TravelPlanStyle::TEXT_SOFT,
            textContentLight: TravelPlanStyle::TEXT_CONTENT_LIGHT,
            textLight: TravelPlanStyle::TEXT_LIGHT,
            white: TravelPlanStyle::WHITE,
            cream: TravelPlanStyle::CREAM,
            zone: TravelPlanStyle::ZONE,
            edge: TravelPlanStyle::EDGE,
            edgeCard: TravelPlanStyle::EDGE_CARD,
            sectionRadius: TravelPlanStyle::SECTION_RADIUS,
            cardRadius: TravelPlanStyle::CARD_RADIUS,
            variants: $this->variants(),
        );
    }

    /**
     * @return list<DestinationView>
     */
    public function destinations(TravelPlanContent $content, bool $includePdfIcons = true): array
    {
        return \array_map(
            fn (Destination $destination): DestinationView => $this->destination($destination, $includePdfIcons),
            $content->destinations,
        );
    }

    public function destination(Destination $destination, bool $includePdfIcons = true): DestinationView
    {
        $path = BlockPath::destination($destination->sourceIndex);
        $variant = $this->variantName($destination->colorVariant);
        $palette = $this->palette($variant);
        $textHtml = $this->richTextNormalizer->normalize($destination->text);

        return new DestinationView(
            path: $path,
            type: $destination->type->value,
            title: $destination->title,
            text: $destination->text,
            textHtml: $textHtml,
            iconSvg: $this->iconSvg($destination->iconOrDefault()),
            iconSrc: $includePdfIcons ? $this->iconBadge($destination->iconOrDefault()) : null,
            city: $destination->city,
            region: $destination->region,
            country: $destination->country,
            location: $destination->locationLabel(),
            caption: $destination->caption,
            imageSrc: $this->helper->mediaImageSrc($destination->image, false),
            startOnNewPage: $destination->startOnNewPage,
            pageBreakClass: $this->pageBreakClass($destination->startOnNewPage),
            styleVariant: $variant,
            variant: $variant,
            variantClass: $this->variantClass($variant),
            isPrimary: ColorVariant::Primary === $destination->colorVariant,
            isSecondary: ColorVariant::Secondary === $destination->colorVariant,
            isGold: ColorVariant::Gold === $destination->colorVariant,
            background: (string) ($palette['background'] ?? TravelPlanStyle::WHITE),
            edge: (string) ($palette['edge'] ?? TravelPlanStyle::EDGE),
            accent: (string) ($palette['accent'] ?? $palette['bar'] ?? TravelPlanStyle::GOLD),
            barColor: (string) ($palette['bar'] ?? TravelPlanStyle::GOLD),
            titleColor: (string) ($palette['title'] ?? TravelPlanStyle::NAVY),
            bodyColor: (string) ($palette['body'] ?? TravelPlanStyle::TEXT_BODY),
            metaColor: (string) ($palette['meta'] ?? TravelPlanStyle::GOLD),
            keep: \mb_strlen(\strip_tags($textHtml)) <= TravelPlanStyle::KEEP_TOGETHER_MAX_CHARS,
            sections: \array_map(
                fn (Section $section): SectionView => $this->section($section, $path, $includePdfIcons),
                $destination->sections,
            ),
            routeStops: [],
        );
    }

    public function section(Section $section, BlockPath $destinationPath, bool $includePdfIcons = true): SectionView
    {
        $path = $destinationPath->section($section->sourceIndex);
        $variant = $this->variantName($section->colorVariant);
        $blocks = \array_map(
            fn (DayBlock $block): BlockView => $this->block($block, $path, $includePdfIcons),
            $section->blocks,
        );
        $day = SectionType::Day === $section->type ? $this->day($section, $path, $blocks, $includePdfIcons) : null;
        $textHtml = $this->richTextNormalizer->normalize($section->text);

        return new SectionView(
            path: $path,
            type: $section->type->value,
            title: $section->title,
            text: $section->text,
            textHtml: $textHtml,
            intro: $section->intro,
            introHtml: $this->introHtml($section->intro),
            iconSvg: $this->iconSvg($section->iconOrDefault()),
            iconSrc: $includePdfIcons && '' !== $section->icon ? $this->iconBadge($section->icon) : null,
            dayNumber: $section->dayNumber,
            dateLabel: $section->dateLabel,
            startOnNewPage: $section->startOnNewPage,
            pageBreakClass: $this->pageBreakClass($section->startOnNewPage),
            styleVariant: $variant,
            variant: $variant,
            variantClass: $this->variantClass($variant),
            isPrimary: ColorVariant::Primary === $section->colorVariant,
            isSecondary: ColorVariant::Secondary === $section->colorVariant,
            isGold: ColorVariant::Gold === $section->colorVariant,
            meta: null !== $day ? $day->meta : '',
            headerOnly: null !== $day && $day->headerOnly,
            boundCard: $day?->boundCard,
            boundCloses: null !== $day && $day->boundCloses,
            day: $day,
            blocks: $blocks,
            rows: null !== $day ? $day->rows : [],
            routeStops: $this->routeStops($section),
        );
    }

    public function block(DayBlock $block, BlockPath $sectionPath, bool $includePdfIcons = true): BlockView
    {
        $timed = $this->helper->withTimeRange($block->raw);
        $variant = $this->variantName($block->colorVariant);
        $textHtml = $this->richTextNormalizer->normalize($block->text);

        if (ColorVariant::Primary === $block->colorVariant && '' !== $textHtml) {
            $textHtml = \str_replace(
                ['<p>', '<li>'],
                ['<p style="color: ' . TravelPlanStyle::TEXT_LIGHT . ';">', '<li style="color: ' . TravelPlanStyle::TEXT_LIGHT . ';">'],
                $textHtml,
            );
        }

        return new BlockView(
            path: $sectionPath->block($block->sourceIndex),
            type: $block->type->value,
            isTip: BlockType::Tip === $block->type,
            isPrimary: ColorVariant::Primary === $block->colorVariant,
            isSecondary: ColorVariant::Secondary === $block->colorVariant,
            isGold: ColorVariant::Gold === $block->colorVariant,
            flow: \mb_strlen(\strip_tags($textHtml)) > TravelPlanStyle::KEEP_TOGETHER_MAX_CHARS || $block->startOnNewPage,
            startOnNewPage: $block->startOnNewPage,
            pageBreakClass: $this->pageBreakClass($block->startOnNewPage),
            styleVariant: $variant,
            variantClass: $this->variantClass($variant),
            title: $block->title,
            timeRangeLabel: \is_string($timed['timeRangeLabel'] ?? null) ? $timed['timeRangeLabel'] : '',
            timeLabel: $block->timeLabel,
            location: $block->location,
            text: $block->text,
            textHtml: $textHtml,
            bookingUrl: $block->bookingUrl,
            actionLabel: $block->type->actionLabel(),
            iconSvg: $this->iconSvg($block->iconOrDefault()),
            iconSrc: $includePdfIcons ? $this->iconBadge($block->iconOrDefault()) : null,
        );
    }

    /**
     * @param list<BlockView> $blocks
     */
    private function day(Section $section, BlockPath $path, array $blocks, bool $includePdfIcons): DayView
    {
        $boundCard = null;
        $rows = [];

        if ([] !== $blocks && !$blocks[0]->flow) {
            $boundCard = \array_shift($blocks);
        }

        $count = \count($blocks);
        for ($index = 0; $index < $count; ++$index) {
            $rows[] = [
                'solo' => $blocks[$index]->flow,
                'block' => $blocks[$index],
                'isFirstOfSegment' => false,
                'isLastOfSegment' => !$blocks[$index]->flow && ($index + 1 >= $count || $blocks[$index + 1]->flow),
            ];
        }

        $this->markDayRowSegments($rows, null === $boundCard);

        $meta = \array_filter([
            '' !== $section->dayNumber ? 'Dag ' . $section->dayNumber : null,
            '' !== $section->dateLabel ? $section->dateLabel : null,
        ]);

        return new DayView(
            path: $path,
            isPrimary: ColorVariant::Primary === $section->colorVariant,
            isSecondary: ColorVariant::Secondary === $section->colorVariant,
            meta: [] !== $meta ? \implode(' · ', $meta) : '',
            title: $section->title,
            introHtml: $this->introHtml($section->intro),
            iconSrc: $includePdfIcons && '' !== $section->icon ? $this->iconBadge($section->icon) : null,
            headerOnly: null === $boundCard && [] === $rows,
            boundCard: $boundCard,
            boundCloses: [] === $rows || $rows[0]['solo'],
            rows: $rows,
        );
    }

    /**
     * @param list<array{solo: bool, block: BlockView, isFirstOfSegment: bool, isLastOfSegment: bool}> $rows
     */
    private function markDayRowSegments(array &$rows, bool $previousWasSolo): void
    {
        foreach ($rows as $index => $row) {
            if ($row['solo']) {
                $previousWasSolo = true;
                continue;
            }

            $rows[$index]['isFirstOfSegment'] = $previousWasSolo;
            $previousWasSolo = false;
        }
    }

    /**
     * @return list<array{type?: string, title?: string, location?: string, text?: string, icon?: string, _iconMarkup?: string|null}>
     */
    private function routeStops(Section $section): array
    {
        if (SectionType::RouteOverview !== $section->type) {
            return [];
        }

        return \array_map(function (array $stop): array {
            $result = [];

            foreach (['type', 'title', 'icon'] as $key) {
                if (\is_string($stop[$key] ?? null)) {
                    $result[$key] = $stop[$key];
                }
            }

            $result['_iconMarkup'] = $this->iconSvg($this->iconOrDefault(
                $stop['icon'] ?? null,
                SectionType::RouteOverview->defaultIcon(),
            ));

            return $result;
        }, $section->routeStops);
    }

    private function introHtml(string $intro): string
    {
        if ('' === $intro) {
            return '';
        }

        return \str_replace(
            ['<p>', '<li>'],
            ['<p style="color: ' . TravelPlanStyle::TEXT_LIGHT . ';">', '<li style="color: ' . TravelPlanStyle::TEXT_LIGHT . ';">'],
            $this->richTextNormalizer->normalize($intro),
        );
    }

    /**
     * @return array<string, string|null>
     */
    private function palette(string $variant): array
    {
        $variants = TravelPlanStyle::variants();

        return $variants[$variant] ?? $variants['default'];
    }

    private function variantName(ColorVariant $variant): string
    {
        return ColorVariant::Auto === $variant ? 'default' : $variant->value;
    }

    private function variantClass(string $variant): string
    {
        return 'default' === $variant ? '' : 'travel-plan-variant--' . $variant;
    }

    /**
     * @return array<string, array{background: string, edge: string, bar: string, accent: string|null, title: string, body: string, meta: string, link: string}>
     */
    private function variants(): array
    {
        $variants = [];

        foreach (TravelPlanStyle::variants() as $name => $variant) {
            $variants[$name] = [
                'background' => (string) ($variant['background'] ?? TravelPlanStyle::WHITE),
                'edge' => (string) ($variant['edge'] ?? TravelPlanStyle::EDGE),
                'bar' => (string) ($variant['bar'] ?? TravelPlanStyle::GOLD),
                'accent' => $variant['accent'] ?? null,
                'title' => (string) ($variant['title'] ?? TravelPlanStyle::NAVY),
                'body' => (string) ($variant['body'] ?? TravelPlanStyle::TEXT_BODY),
                'meta' => (string) ($variant['meta'] ?? TravelPlanStyle::GOLD),
                'link' => (string) ($variant['link'] ?? TravelPlanStyle::GOLD),
            ];
        }

        return $variants;
    }

    private function pageBreakClass(bool $startOnNewPage): string
    {
        return $startOnNewPage ? 'travel-plan-page-break-before' : '';
    }

    private function iconSvg(string $icon): string
    {
        $svg = $this->iconResolver->getSvgIcon($icon);

        return '' === $svg ? '' : $svg;
    }

    private function iconBadge(string $icon): ?string
    {
        $dataUri = $this->iconResolver->getPdfIconBadgeDataUri($icon);

        return '' === $dataUri ? null : $dataUri;
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

<?php

declare(strict_types=1);

namespace App\TravelPlan\Renderer;

use App\Entity\TravelPlan;
use App\Service\IconResolver;
use App\TravelPlan\Content\BlockType;
use App\TravelPlan\Content\ColorVariant;
use App\TravelPlan\Content\DayBlock;
use App\TravelPlan\Content\Destination;
use App\TravelPlan\Content\Section;
use App\TravelPlan\Content\SectionType;
use App\TravelPlan\Content\TravelPlanContent;
use App\TravelPlan\Pdf\TravelPlanPdfRichTextNormalizer;
use App\TravelPlan\TravelPlanStyle;
use Twig\Environment;

/**
 * Bouwt de HTML voor de PDF-weergave van een reisplan.
 *
 * Werkt op het getypeerde contentmodel (TravelPlanContent); alle markup en
 * inline styling staat in de Twig-templates onder templates/travel_plan/pdf/
 * met de tokens uit TravelPlanStyle. De account-weergave heeft zijn eigen
 * renderer (TravelPlanRenderer).
 */
final readonly class TravelPlanPdfRenderer
{
    /** Sectietypes die (nog) via de gedeelde templates renderen. */
    private const SHARED_TEMPLATE_SECTIONS = [
        SectionType::RouteOverview->value => 'travel_plan/render/sections/route_overview.html.twig',
        SectionType::Checklist->value => 'travel_plan/render/sections/checklist.html.twig',
        SectionType::BudgetNote->value => 'travel_plan/render/sections/budget_note.html.twig',
        SectionType::PersonalNote->value => 'travel_plan/render/sections/personal_note.html.twig',
        SectionType::Image->value => 'travel_plan/render/sections/image.html.twig',
    ];

    public function __construct(
        private Environment $twig,
        private IconResolver $iconResolver,
        private TravelPlanContentHelper $helper,
        private TravelPlanPdfRichTextNormalizer $richTextNormalizer,
    ) {
    }

    public function render(TravelPlan $travelPlan): string
    {
        $content = TravelPlanContent::fromArray($travelPlan->getContent());
        $tokens = TravelPlanStyle::tokens();
        $tocDepth = $this->helper->tableOfContentsDepth($content->tripProfile->showTableOfContents);
        $sections = [];
        $tableOfContents = [];

        foreach ($content->destinations as $destination) {
            $sections[] = $this->destinationViewModel($destination, $tocDepth, $tableOfContents, $tokens, $travelPlan);

            foreach ($destination->sections as $section) {
                $sections[] = $this->sectionViewModel($section, $tocDepth, $tableOfContents, $tokens, $travelPlan);
            }
        }

        $contact = $travelPlan->getTravelRequest()->getContact();
        $customerName = \trim(\implode(' ', \array_filter([$contact->getFirstName(), $contact->getLastName()])));

        return $this->twig->render('travel_plan/pdf/document.html.twig', [
            'travelPlan' => $travelPlan,
            'customerName' => $customerName,
            'intro' => ['title' => $content->introTitle, 'text' => $content->introText],
            'tripProfile' => $content->tripProfile->raw,
            'logoSrc' => $this->helper->assetDataUri('assets/images/pdf/logo-pdf.png', 'image/png'),
            'sections' => \array_values(\array_filter($sections)),
            'tableOfContents' => $tableOfContents,
            't' => $tokens,
        ]);
    }

    /**
     * @param list<array{title: string, level: int}> $tableOfContents
     * @param array<string, mixed> $tokens
     *
     * @return array{html: string, tocTitle: ?string, tocLevel: int, startOnNewPage: bool}|null
     */
    private function destinationViewModel(
        Destination $destination,
        int $tocDepth,
        array &$tableOfContents,
        array $tokens,
        TravelPlan $travelPlan,
    ): ?array {
        $tocTitle = $this->registerTocEntry($tableOfContents, $destination->title, 0, $tocDepth);

        $html = $destination->isImage()
            ? $this->renderSharedSection(SectionType::Image, $destination->raw, $travelPlan)
            : $this->renderDestination($destination, $tokens);

        if (null === $html) {
            return null;
        }

        return [
            'html' => $html,
            'tocTitle' => $tocTitle,
            'tocLevel' => 0,
            'startOnNewPage' => $destination->startOnNewPage,
        ];
    }

    /**
     * @param list<array{title: string, level: int}> $tableOfContents
     * @param array<string, mixed> $tokens
     *
     * @return array{html: string, tocTitle: ?string, tocLevel: int, startOnNewPage: bool}|null
     */
    private function sectionViewModel(
        Section $section,
        int $tocDepth,
        array &$tableOfContents,
        array $tokens,
        TravelPlan $travelPlan,
    ): ?array {
        $tocTitle = $this->registerTocEntry($tableOfContents, $section->title, 1, $tocDepth);

        $html = match ($section->type) {
            SectionType::Day => $this->renderDayGroup($section, $tokens),
            SectionType::FreeText, SectionType::PracticalInfo => $this->renderFrame($section, $tokens),
            default => $this->renderSharedSection($section->type, $section->raw, $travelPlan),
        };

        if (null === $html) {
            return null;
        }

        return [
            'html' => $html,
            'tocTitle' => $tocTitle,
            'tocLevel' => 1,
            'startOnNewPage' => $section->startOnNewPage,
        ];
    }

    /**
     * @param list<array{title: string, level: int}> $tableOfContents
     */
    private function registerTocEntry(array &$tableOfContents, string $title, int $level, int $tocDepth): ?string
    {
        if ($level >= $tocDepth) {
            return null;
        }

        $tocTitle = $this->helper->tableOfContentsTitle($title);

        if (null !== $tocTitle) {
            $tableOfContents[] = ['title' => $tocTitle, 'level' => $level];
        }

        return $tocTitle;
    }

    /**
     * @param array<string, mixed> $tokens
     */
    private function renderDestination(Destination $destination, array $tokens): string
    {
        $textHtml = $this->richTextNormalizer->normalize($destination->text);
        $variants = TravelPlanStyle::variants();
        $variantName = \array_key_exists($destination->colorVariant->value, $variants)
            ? $destination->colorVariant->value
            : 'default';
        $palette = $variants[$variantName] ?? $variants['default'];

        return $this->twig->render('travel_plan/pdf/destination.html.twig', [
            'section' => [
                'title' => $destination->title,
                'location' => $destination->locationLabel(),
                'textHtml' => $textHtml,
                'variant' => $variantName,
                'background' => (string) ($palette['background'] ?? TravelPlanStyle::WHITE),
                'edge' => (string) ($palette['edge'] ?? TravelPlanStyle::EDGE),
                'accent' => (string) ($palette['accent'] ?? $palette['bar'] ?? TravelPlanStyle::GOLD),
                'barColor' => (string) ($palette['bar'] ?? TravelPlanStyle::GOLD),
                'titleColor' => (string) ($palette['title'] ?? TravelPlanStyle::NAVY),
                'bodyColor' => (string) ($palette['body'] ?? TravelPlanStyle::TEXT_BODY),
                'metaColor' => (string) ($palette['meta'] ?? TravelPlanStyle::GOLD),
                'iconSrc' => $this->iconBadge($destination->iconOrDefault()),
                'keep' => \mb_strlen(\strip_tags($textHtml)) <= TravelPlanStyle::KEEP_TOGETHER_MAX_CHARS,
            ],
            't' => $tokens,
        ]);
    }

    /**
     * @param array<string, mixed> $tokens
     */
    private function renderDayGroup(Section $section, array $tokens): string
    {
        $blocks = \array_map($this->blockViewModel(...), $section->blocks);

        // Eerste (korte, niet-brekende) kaart bindt aan de header; daarna
        // per kaart segmentflags zodat de template een domme loop blijft.
        $boundCard = null;

        if ([] !== $blocks && !$blocks[0]['flow']) {
            $boundCard = \array_shift($blocks);
        }

        $rows = [];
        $count = \count($blocks);

        for ($i = 0; $i < $count; ++$i) {
            $rows[] = [
                'solo' => $blocks[$i]['flow'],
                'block' => $blocks[$i],
                'isFirstOfSegment' => false, // hieronder bepaald
                'isLastOfSegment' => !$blocks[$i]['flow'] && ($i + 1 >= $count || $blocks[$i + 1]['flow']),
            ];
        }

        $previousWasSolo = null === $boundCard;

        foreach ($rows as $index => $row) {
            if ($row['solo']) {
                $previousWasSolo = true;

                continue;
            }

            $rows[$index]['isFirstOfSegment'] = $previousWasSolo;
            $previousWasSolo = false;
        }

        $meta = \array_filter([
            '' !== $section->dayNumber ? 'Dag ' . $section->dayNumber : null,
            '' !== $section->dateLabel ? $section->dateLabel : null,
        ]);
        $variant = $section->colorVariant;

        return $this->twig->render('travel_plan/pdf/day_group.html.twig', [
            'group' => [
                'isPrimary' => ColorVariant::Primary === $variant,
                'isSecondary' => ColorVariant::Secondary === $variant,
                'meta' => [] !== $meta ? \implode(' · ', $meta) : null,
                'title' => $section->title,
                'introHtml' => $this->introHtml($section->intro),
                'iconSrc' => '' !== $section->icon ? $this->iconBadge($section->icon) : null,
                'headerOnly' => null === $boundCard && [] === $rows,
                'boundCard' => $boundCard,
                'boundCloses' => [] === $rows || $rows[0]['solo'],
                'rows' => $rows,
            ],
            't' => $tokens,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function blockViewModel(DayBlock $block): array
    {
        $timed = $this->helper->withTimeRange($block->raw);
        $textHtml = $this->richTextNormalizer->normalize($block->text);

        // Kleurvariant per blok (CMS-veld "Kleur"). Auto/default blijft wit;
        // alleen expliciete keuzes kleuren mee.
        $variant = $block->colorVariant;

        if (ColorVariant::Primary === $variant && '' !== $textHtml) {
            $textHtml = \str_replace(
                ['<p>', '<li>'],
                ['<p style="color: ' . TravelPlanStyle::TEXT_LIGHT . ';">', '<li style="color: ' . TravelPlanStyle::TEXT_LIGHT . ';">'],
                $textHtml,
            );
        }

        return [
            'type' => $block->type->value,
            'isTip' => BlockType::Tip === $block->type,
            'isPrimary' => ColorVariant::Primary === $variant,
            'isSecondary' => ColorVariant::Secondary === $variant,
            'isGold' => ColorVariant::Gold === $variant,
            'flow' => \mb_strlen(\strip_tags($textHtml)) > TravelPlanStyle::KEEP_TOGETHER_MAX_CHARS
                || $block->startOnNewPage,
            'startOnNewPage' => $block->startOnNewPage,
            'title' => $block->title,
            'timeRangeLabel' => $timed['timeRangeLabel'] ?? '',
            'timeLabel' => $block->timeLabel,
            'location' => $block->location,
            'textHtml' => $textHtml,
            'bookingUrl' => $block->bookingUrl,
            'actionLabel' => $block->type->actionLabel(),
            'iconSrc' => $this->iconBadge($block->iconOrDefault()),
        ];
    }

    /**
     * @param array<string, mixed> $tokens
     */
    private function renderFrame(Section $section, array $tokens): string
    {
        return $this->twig->render('travel_plan/pdf/frame_section.html.twig', [
            'section' => [
                'title' => $section->title,
                'textHtml' => $this->richTextNormalizer->normalize($section->text),
            ],
            't' => $tokens,
        ]);
    }

    /**
     * Rendert secties die (nog) de gedeelde account/PDF-templates gebruiken.
     * Die templates verwachten het ruwe array; het model levert dat via
     * $raw, met hier de PDF-specifieke verrijkingen (afbeeldingspaden,
     * route-iconen, genormaliseerde tekst).
     *
     * @param array<string, mixed> $raw
     */
    private function renderSharedSection(SectionType $type, array $raw, TravelPlan $travelPlan): ?string
    {
        $template = self::SHARED_TEMPLATE_SECTIONS[$type->value] ?? null;

        if (null === $template) {
            return null;
        }

        if (SectionType::Image === $type) {
            $raw['imageSrc'] = $this->helper->mediaImageSrc($raw['image'] ?? null, true);
        }

        if (SectionType::RouteOverview === $type && \is_array($raw['routeStops'] ?? null)) {
            $raw['routeStops'] = \array_map(function (mixed $stop): mixed {
                if (!\is_array($stop)) {
                    return $stop;
                }

                $icon = \is_string($stop['icon'] ?? null) && '' !== \trim($stop['icon']) ? $stop['icon'] : 'map';
                $iconSrc = $this->iconBadge($icon);
                $stop['_iconMarkup'] = null !== $iconSrc
                    ? '<img class="travel-plan-icon" src="' . $iconSrc . '" alt="">'
                    : null;

                return $stop;
            }, $raw['routeStops']);
        }

        if (\is_string($raw['text'] ?? null)) {
            $raw['text'] = $this->richTextNormalizer->normalize($raw['text']);
        }

        $html = $this->twig->render($template, [
            'section' => $raw,
            'accountView' => false,
            'travelPlan' => $travelPlan,
            'feedbackEnabled' => false,
        ]);

        // Sectie-icoon rechtsboven injecteren (zoals in de account-weergave).
        $iconSrc = $this->iconBadge(
            \is_string($raw['icon'] ?? null) && '' !== \trim($raw['icon']) ? $raw['icon'] : $type->defaultIcon(),
        );

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

    private function iconBadge(string $icon): ?string
    {
        $dataUri = $this->iconResolver->getPdfIconBadgeDataUri($icon);

        return '' === $dataUri ? null : $dataUri;
    }
}

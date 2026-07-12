<?php

declare(strict_types=1);

namespace App\Service\TravelCompanion;

use App\Entity\TravelPlan;
use App\TravelPlan\Content\DayBlock;
use App\TravelPlan\Content\Section;
use App\TravelPlan\Content\SectionType;
use App\TravelPlan\Content\TravelPlanContent;
use App\ViewModel\TravelCompanion\CompanionBlock;
use App\ViewModel\TravelCompanion\CompanionDay;
use App\ViewModel\TravelCompanion\CompanionTrip;
use Sulu\Bundle\ContactBundle\Entity\Contact;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class TravelCompanionBuilder
{
    /** @var array<string, string> */
    private array $iconCache = [];

    public function __construct(
        private readonly TravelPlanChecklistStateProvider $checklistStateRepository,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function build(TravelPlan $travelPlan, Contact $contact): CompanionTrip
    {
        $content = TravelPlanContent::fromArray($travelPlan->getContent());
        $tripProfile = $content->tripProfile->raw;
        $startDate = CompanionContentHelper::createDate($content->tripProfile->startDate);
        $endDate = CompanionContentHelper::createDate($content->tripProfile->endDate);
        $currentDayNumber = $this->currentDayNumber($startDate, $endDate);
        $checkedItems = $this->checklistStateRepository->checkedMapForPlan($contact, $travelPlan);
        $days = $this->days($content, $startDate, $endDate, $currentDayNumber, $checkedItems);
        $currentDay = $this->currentDay($days, $currentDayNumber);
        $blocks = $this->overviewBlocks($content, $checkedItems);

        return new CompanionTrip(
            $travelPlan->getId() ?? 0,
            $travelPlan->getTitle(),
            $this->periodLabel($tripProfile, $startDate, $endDate),
            $this->durationLabel($tripProfile, $startDate, $endDate),
            $currentDayNumber,
            $this->dayStatusLabel($currentDayNumber, $days),
            $travelPlan->isPdfReleased(),
            $days,
            $currentDay,
            $blocks,
            $this->hasChecklist($blocks, $days),
            $this->hasNotes($blocks, $days),
        );
    }

    /**
     * @param array<string, bool> $checkedItems
     *
     * @return list<CompanionDay>
     */
    private function days(
        TravelPlanContent $content,
        ?\DateTimeImmutable $startDate,
        ?\DateTimeImmutable $endDate,
        ?int $currentDayNumber,
        array $checkedItems,
    ): array {
        $sectionsByDayNumber = [];

        foreach ($content->destinations as $destination) {
            foreach ($destination->sections as $section) {
                if (SectionType::Day !== $section->type) {
                    continue;
                }

                $dayNumber = \max(1, (int) ($section->dayNumber ?: 1));
                $sectionsByDayNumber[$dayNumber] = [
                    'destinationIndex' => $destination->sourceIndex,
                    'destinationTitle' => $destination->title,
                    'sectionIndex' => $section->sourceIndex,
                    'section' => $section,
                ];
            }
        }

        $dayNumbers = $this->dayNumbers($sectionsByDayNumber, $startDate, $endDate);
        $days = [];

        foreach ($dayNumbers as $dayNumber) {
            $sectionData = $sectionsByDayNumber[$dayNumber] ?? null;
            $section = $sectionData['section'] ?? null;
            $destinationIndex = null !== $sectionData ? $sectionData['destinationIndex'] : 0;
            $destinationTitle = null !== $sectionData ? $sectionData['destinationTitle'] : '';
            $sectionIndex = null !== $sectionData ? $sectionData['sectionIndex'] : 0;
            $status = $this->dayStatus($dayNumber, $currentDayNumber);
            $dateLabel = $section instanceof Section ? $section->dateLabel : '';
            $dateLabel = '' !== $dateLabel ? $dateLabel : $this->dateLabelForDay($startDate, $dayNumber);

            $days[] = new CompanionDay(
                $dayNumber,
                $destinationTitle,
                $destinationIndex,
                ($section instanceof Section ? $section->title : '') ?: \sprintf('Dag %d', $dayNumber),
                $dateLabel,
                $section instanceof Section ? CompanionContentHelper::stringValue($section->raw, 'subtitle') : '',
                $section instanceof Section ? CompanionContentHelper::stringValue($section->raw, 'location') : '',
                $section instanceof Section ? $section->intro : '',
                $status,
                'past' === $status,
                'current' === $status,
                'upcoming' === $status,
                $this->blocks(
                    $section instanceof Section ? $section->blocks : [],
                    \sprintf('destinations[%d].sections[%d].blocks', $destinationIndex, $sectionIndex),
                    $checkedItems,
                ),
            );
        }

        \usort($days, static fn (CompanionDay $left, CompanionDay $right): int => $left->dayNumber <=> $right->dayNumber);

        return $days;
    }

    /**
     * @param array<int, array{destinationIndex: int, destinationTitle: string, sectionIndex: int, section: Section}> $sectionsByDayNumber
     *
     * @return list<int>
     */
    private function dayNumbers(
        array $sectionsByDayNumber,
        ?\DateTimeImmutable $startDate,
        ?\DateTimeImmutable $endDate,
    ): array {
        if ($startDate instanceof \DateTimeImmutable && $endDate instanceof \DateTimeImmutable && $endDate >= $startDate) {
            return \range(1, CompanionContentHelper::inclusiveDays($startDate, $endDate));
        }

        $dayNumbers = \array_keys($sectionsByDayNumber);
        \sort($dayNumbers);

        return $dayNumbers;
    }

    private function dateLabelForDay(?\DateTimeImmutable $startDate, int $dayNumber): string
    {
        if (!$startDate instanceof \DateTimeImmutable) {
            return '';
        }

        return CompanionContentHelper::dateLabel($startDate->modify(\sprintf('+%d days', $dayNumber - 1)));
    }

    /**
     * @param array<string, bool> $checkedItems
     *
     * @return list<CompanionBlock>
     */
    private function overviewBlocks(TravelPlanContent $content, array $checkedItems): array
    {
        $blocks = [];

        foreach ($content->destinations as $destination) {
            if ($destination->isImage()) {
                continue;
            }

            $destinationIndex = $destination->sourceIndex;
            $destinationBlock = $this->block(
                $destination->raw,
                \sprintf('destinations[%d]', $destinationIndex),
                $checkedItems,
            );

            if ($destinationBlock->hasContent()) {
                $blocks[] = $destinationBlock;
            }

            foreach ($destination->sections as $section) {
                if (SectionType::Day === $section->type) {
                    continue;
                }

                if ($this->isTechnicalType($section->type->value)) {
                    continue;
                }

                $block = $this->block(
                    $section->raw,
                    \sprintf('destinations[%d].sections[%d]', $destinationIndex, $section->sourceIndex),
                    $checkedItems,
                );

                if ($block->hasContent()) {
                    $blocks[] = $block;
                }
            }
        }

        return $blocks;
    }

    /**
     * @param list<DayBlock> $blocks
     * @param array<string, bool> $checkedItems
     *
     * @return list<CompanionBlock>
     */
    private function blocks(array $blocks, string $pathPrefix, array $checkedItems): array
    {
        $items = [];

        foreach ($blocks as $block) {
            if ($this->isTechnicalType($block->type->value)) {
                continue;
            }

            $item = $this->block($block->raw, \sprintf('%s[%d]', $pathPrefix, $block->sourceIndex), $checkedItems);

            if ($item->hasContent()) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $block
     * @param array<string, bool>  $checkedItems
     */
    private function block(array $block, string $path, array $checkedItems): CompanionBlock
    {
        $type = $this->normalizeType(CompanionContentHelper::stringValue($block, 'type'));
        $text = CompanionContentHelper::stringValue($block, 'text');

        return new CompanionBlock(
            $type,
            CompanionContentHelper::stringValue($block, 'title'),
            $text,
            $this->renderableIcon(CompanionContentHelper::stringValue($block, 'icon')),
            $this->location($block, $type),
            CompanionContentHelper::stringValue($block, 'mapsUrl'),
            CompanionContentHelper::stringValue($block, 'timeLabel'),
            $this->startTime($block),
            $this->endTime($block),
            $this->timeRangeLabel($block),
            CompanionContentHelper::stringValue($block, 'bookingUrl'),
            'checklist' === $type ? $this->checklistItems($text, $path, $checkedItems) : [],
            'route_overview' === $type ? $this->routeStops($block['routeStops'] ?? []) : [],
        );
    }

    /**
     * @return list<array{title: string, location: string, text: string, icon: string}>
     */
    private function routeStops(mixed $routeStops): array
    {
        if (!\is_array($routeStops)) {
            return [];
        }

        $items = [];

        foreach ($routeStops as $routeStop) {
            if (!\is_array($routeStop)) {
                continue;
            }

            $routeStop = $this->stringKeyedArray($routeStop);
            $item = [
                'title' => CompanionContentHelper::stringValue($routeStop, 'title'),
                'location' => CompanionContentHelper::stringValue($routeStop, 'location'),
                'text' => CompanionContentHelper::stringValue($routeStop, 'text'),
                'icon' => $this->renderableIcon(CompanionContentHelper::stringValue($routeStop, 'icon')),
            ];

            if ('' !== $item['title'] || '' !== $item['location'] || '' !== $item['text']) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @param array<string, bool> $checkedItems
     *
     * @return list<array{key: string, label: string, checked: bool}>
     */
    private function checklistItems(string $text, string $path, array $checkedItems): array
    {
        $labels = [];

        if (1 === \preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $text, $matches)) {
            foreach ($matches[1] as $item) {
                $labels[] = \trim(\strip_tags((string) $item));
            }
        } else {
            foreach (\preg_split('/\R/', \strip_tags($text)) ?: [] as $line) {
                $line = \trim((string) $line, " \t\n\r\0\x0B-*•");

                if ('' !== $line) {
                    $labels[] = $line;
                }
            }
        }

        $items = [];

        foreach (\array_values(\array_unique(\array_filter($labels))) as $index => $label) {
            $key = \substr(\sha1($path.'|'.$index.'|'.$label), 0, 40);
            $items[] = [
                'key' => $key,
                'label' => $label,
                'checked' => $checkedItems[$key] ?? false,
            ];
        }

        return $items;
    }

    private function currentDayNumber(?\DateTimeImmutable $startDate, ?\DateTimeImmutable $endDate): ?int
    {
        if (!$startDate instanceof \DateTimeImmutable || !$endDate instanceof \DateTimeImmutable) {
            return null;
        }

        $today = new \DateTimeImmutable('today');

        if ($startDate > $today || $endDate < $today) {
            return null;
        }

        return CompanionContentHelper::inclusiveDays($startDate, $today);
    }

    /**
     * @param list<CompanionDay> $days
     */
    private function currentDay(array $days, ?int $currentDayNumber): ?CompanionDay
    {
        if (null === $currentDayNumber) {
            return null;
        }

        foreach ($days as $day) {
            if ($day->dayNumber === $currentDayNumber) {
                return $day;
            }
        }

        return null;
    }

    /**
     * @param list<CompanionDay> $days
     */
    private function dayStatusLabel(?int $currentDayNumber, array $days): string
    {
        if (null !== $currentDayNumber) {
            return \sprintf('Vandaag is dag %d', $currentDayNumber);
        }

        if ([] === $days) {
            return 'Dagplanning nog niet beschikbaar';
        }

        return 'Reisplanning';
    }

    private function dayStatus(int $dayNumber, ?int $currentDayNumber): string
    {
        if (null === $currentDayNumber) {
            return 'upcoming';
        }

        return match (true) {
            $dayNumber < $currentDayNumber => 'past',
            $dayNumber === $currentDayNumber => 'current',
            default => 'upcoming',
        };
    }

    /**
     * @param array<string, mixed> $tripProfile
     */
    private function periodLabel(array $tripProfile, ?\DateTimeImmutable $startDate, ?\DateTimeImmutable $endDate): string
    {
        if ($startDate instanceof \DateTimeImmutable && $endDate instanceof \DateTimeImmutable) {
            return \sprintf(
                '%s t/m %s',
                CompanionContentHelper::dateLabel($startDate),
                CompanionContentHelper::dateLabel($endDate),
            );
        }

        return CompanionContentHelper::stringValue($tripProfile, 'period');
    }

    /**
     * @param array<string, mixed> $tripProfile
     */
    private function durationLabel(array $tripProfile, ?\DateTimeImmutable $startDate, ?\DateTimeImmutable $endDate): string
    {
        if ($startDate instanceof \DateTimeImmutable && $endDate instanceof \DateTimeImmutable && $endDate >= $startDate) {
            $days = CompanionContentHelper::inclusiveDays($startDate, $endDate);

            return \sprintf('%d %s', $days, 1 === $days ? 'dag' : 'dagen');
        }

        return CompanionContentHelper::stringValue($tripProfile, 'duration');
    }

    private function renderableIcon(string $icon): string
    {
        $icon = \trim($icon);

        if ('' === $icon) {
            return '';
        }

        if (isset($this->iconCache[$icon])) {
            return $this->iconCache[$icon];
        }

        if (1 === \preg_match('/^(https?:\/\/|\/).+\.(svg|png|webp|jpg|jpeg)$/i', $icon)) {
            return $this->iconCache[$icon] = $icon;
        }

        if (1 !== \preg_match('/^[a-z0-9][a-z0-9-]*$/', $icon)) {
            return $this->iconCache[$icon] = '';
        }

        $path = $this->projectDir.'/assets/images/icons/'.$icon.'.svg';

        if (!\is_file($path) || false === $contents = \file_get_contents($path)) {
            return $this->iconCache[$icon] = '';
        }

        return $this->iconCache[$icon] = 'data:image/svg+xml;base64,'.\base64_encode($contents);
    }

    private function normalizeType(string $type): string
    {
        return match ($type) {
            'text', '' => 'free_text',
            'notes' => 'note',
            default => $type,
        };
    }

    private function isTechnicalType(string $type): bool
    {
        return \in_array($type, [
            '_feedback',
            'feedbackSummary',
            'feedback_summary',
            'planFeedback',
            'plan_feedback',
            'status',
            'pdf',
            'pdfStatus',
            'pdf_status',
            'publishedAt',
            'published_at',
            'createdAt',
            'created_at',
            'updatedAt',
            'updated_at',
        ], true);
    }

    /**
     * @param array<string, mixed> $block
     */
    private function location(array $block, string $type): string
    {
        $location = CompanionContentHelper::stringValue($block, 'location');

        if ('' !== $location || 'destination' !== $type) {
            return $location;
        }

        return \implode(', ', \array_filter([
            CompanionContentHelper::stringValue($block, 'city'),
            CompanionContentHelper::stringValue($block, 'region'),
            CompanionContentHelper::stringValue($block, 'country'),
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function stringKeyedArray(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $key => $item) {
            if (\is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $block
     */
    private function startTime(array $block): string
    {
        return $this->normalizeTime(CompanionContentHelper::stringValue($block, 'startTime'))
            ?: $this->normalizeTime(CompanionContentHelper::stringValue($block, 'time'));
    }

    /**
     * @param array<string, mixed> $block
     */
    private function endTime(array $block): string
    {
        return $this->normalizeTime(CompanionContentHelper::stringValue($block, 'endTime'));
    }

    /**
     * @param array<string, mixed> $block
     */
    private function timeRangeLabel(array $block): string
    {
        $startTime = $this->startTime($block);
        $endTime = $this->endTime($block);

        if ('' === $startTime) {
            return '';
        }

        if ('' === $endTime || $endTime === $startTime) {
            return $startTime;
        }

        return \sprintf('%s - %s', $startTime, $endTime);
    }

    private function normalizeTime(string $time): string
    {
        $time = \trim($time);

        if (1 !== \preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/D', $time, $matches)) {
            return '';
        }

        return \sprintf('%02d:%s', (int) $matches[1], $matches[2]);
    }

    /**
     * @param list<CompanionBlock> $blocks
     * @param list<CompanionDay>   $days
     */
    private function hasChecklist(array $blocks, array $days): bool
    {
        foreach ($blocks as $block) {
            if ($block->isChecklist()) {
                return true;
            }
        }

        foreach ($days as $day) {
            foreach ($day->blocks as $block) {
                if ($block->isChecklist()) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param list<CompanionBlock> $blocks
     * @param list<CompanionDay>   $days
     */
    private function hasNotes(array $blocks, array $days): bool
    {
        foreach ($blocks as $block) {
            if ($block->isNote()) {
                return true;
            }
        }

        foreach ($days as $day) {
            foreach ($day->blocks as $block) {
                if ($block->isNote()) {
                    return true;
                }
            }
        }

        return false;
    }
}

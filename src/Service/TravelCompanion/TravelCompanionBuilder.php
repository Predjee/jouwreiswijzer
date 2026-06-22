<?php

declare(strict_types=1);

namespace App\Service\TravelCompanion;

use App\Entity\TravelPlan;
use App\Repository\TravelPlanChecklistStateRepository;
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
        private readonly TravelPlanChecklistStateRepository $checklistStateRepository,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function build(TravelPlan $travelPlan, Contact $contact): CompanionTrip
    {
        $content = $travelPlan->getContent();
        $tripProfile = \is_array($content['tripProfile'] ?? null) ? $content['tripProfile'] : [];
        $startDate = CompanionContentHelper::createDate($tripProfile['startDate'] ?? null);
        $endDate = CompanionContentHelper::createDate($tripProfile['endDate'] ?? null);
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
        mixed $content,
        ?\DateTimeImmutable $startDate,
        ?\DateTimeImmutable $endDate,
        ?int $currentDayNumber,
        array $checkedItems,
    ): array {
        if (!\is_array($content)) {
            $content = [];
        }

        $sectionsByDayNumber = [];

        foreach (CompanionContentHelper::destinationSections($content) as $sectionData) {
            $section = $sectionData['section'];

            if (!\is_array($section) || 'day' !== ($section['type'] ?? null)) {
                continue;
            }

            $dayNumber = \max(1, (int) ($section['dayNumber'] ?? 1));
            $sectionsByDayNumber[$dayNumber] = [
                'destinationIndex' => $sectionData['destinationIndex'],
                'destinationTitle' => CompanionContentHelper::stringValue($sectionData['destination'], 'title'),
                'sectionIndex' => $sectionData['sectionIndex'],
                'section' => $section,
            ];
        }

        $dayNumbers = $this->dayNumbers($sectionsByDayNumber, $startDate, $endDate);
        $days = [];

        foreach ($dayNumbers as $dayNumber) {
            $sectionData = $sectionsByDayNumber[$dayNumber] ?? null;
            $section = \is_array($sectionData) && \is_array($sectionData['section']) ? $sectionData['section'] : [];
            $destinationIndex = \is_array($sectionData) ? (int) $sectionData['destinationIndex'] : 0;
            $destinationTitle = \is_array($sectionData) ? (string) $sectionData['destinationTitle'] : '';
            $sectionIndex = \is_array($sectionData) ? (int) $sectionData['sectionIndex'] : 0;
            $status = $this->dayStatus($dayNumber, $currentDayNumber);
            $dateLabel = CompanionContentHelper::stringValue($section, 'dateLabel');
            $dateLabel = '' !== $dateLabel ? $dateLabel : $this->dateLabelForDay($startDate, $dayNumber);

            $days[] = new CompanionDay(
                $dayNumber,
                $destinationTitle,
                $destinationIndex,
                CompanionContentHelper::stringValue($section, 'title') ?: \sprintf('Dag %d', $dayNumber),
                $dateLabel,
                CompanionContentHelper::stringValue($section, 'subtitle'),
                CompanionContentHelper::stringValue($section, 'location'),
                CompanionContentHelper::stringValue($section, 'intro'),
                $status,
                'past' === $status,
                'current' === $status,
                'upcoming' === $status,
                $this->blocks(
                    $section['blocks'] ?? [],
                    \sprintf('destinations[%d].sections[%d].blocks', $destinationIndex, $sectionIndex),
                    $checkedItems,
                ),
            );
        }

        \usort($days, static fn (CompanionDay $left, CompanionDay $right): int => $left->dayNumber <=> $right->dayNumber);

        return $days;
    }

    /**
     * @param array<int, array{destinationIndex: int, destinationTitle: string, sectionIndex: int, section: array<string, mixed>}> $sectionsByDayNumber
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

        return \array_values($dayNumbers);
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
    private function overviewBlocks(mixed $content, array $checkedItems): array
    {
        if (!\is_array($content)) {
            return [];
        }

        $blocks = [];

        foreach (CompanionContentHelper::destinations($content) as $destinationData) {
            $destinationIndex = $destinationData['destinationIndex'];
            $destination = $destinationData['destination'];
            $destinationBlock = $this->block(
                $destination,
                \sprintf('destinations[%d]', $destinationIndex),
                $checkedItems,
            );

            if ($destinationBlock->hasContent()) {
                $blocks[] = $destinationBlock;
            }

            foreach ($destination['sections'] ?? [] as $sectionIndex => $section) {
                if (!\is_array($section) || 'day' === ($section['type'] ?? null)) {
                    continue;
                }

                if ($this->isTechnicalType(CompanionContentHelper::stringValue($section, 'type'))) {
                    continue;
                }

                $block = $this->block(
                    $section,
                    \sprintf('destinations[%d].sections[%d]', $destinationIndex, (int) $sectionIndex),
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
     * @param array<string, bool> $checkedItems
     *
     * @return list<CompanionBlock>
     */
    private function blocks(mixed $blocks, string $pathPrefix, array $checkedItems): array
    {
        if (!\is_array($blocks)) {
            return [];
        }

        $items = [];

        foreach ($blocks as $blockIndex => $block) {
            if (!\is_array($block)) {
                continue;
            }

            if ($this->isTechnicalType(CompanionContentHelper::stringValue($block, 'type'))) {
                continue;
            }

            $item = $this->block($block, \sprintf('%s[%d]', $pathPrefix, (int) $blockIndex), $checkedItems);

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

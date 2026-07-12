<?php

declare(strict_types=1);

namespace App\Service;

use App\TravelPlan\Content\BlockType;
use App\TravelPlan\Content\ColorVariant;
use App\TravelPlan\Content\DayBlock;
use App\TravelPlan\Content\Destination;
use App\TravelPlan\Content\Section;
use App\TravelPlan\Content\SectionType;
use App\TravelPlan\Content\TravelPlanContent;

final class TravelPlanContentFactory
{
    public const TYPE_TRAVEL_PLAN_INTRO = 'travel_plan_intro';
    public const TYPE_TRIP_PROFILE = 'trip_profile';
    public const TYPE_DESTINATION = 'destination';
    public const TYPE_ROUTE_OVERVIEW = 'route_overview';
    public const TYPE_ROUTE_STOP = 'route_stop';
    public const TYPE_DAY = 'day';
    public const TYPE_PRACTICAL_INFO = 'practical_info';
    public const TYPE_CHECKLIST = 'checklist';
    public const TYPE_BUDGET_NOTE = 'budget_note';
    public const TYPE_PERSONAL_NOTE = 'personal_note';
    public const TYPE_FREE_TEXT = 'free_text';
    public const TYPE_ACTIVITY = 'activity';
    public const TYPE_ACCOMMODATION = 'accommodation';
    public const TYPE_TRANSPORT = 'transport';
    public const TYPE_MEAL = 'meal';
    public const TYPE_TIP = 'tip';
    public const TYPE_NOTE = 'note';
    public const TYPE_IMAGE = 'image';

    /** @var list<string> */
    private const DESTINATION_SECTION_TYPES = [
        self::TYPE_ROUTE_OVERVIEW,
        self::TYPE_DAY,
        self::TYPE_PRACTICAL_INFO,
        self::TYPE_CHECKLIST,
        self::TYPE_BUDGET_NOTE,
        self::TYPE_PERSONAL_NOTE,
        self::TYPE_FREE_TEXT,
    ];

    /**
     * @return array<string, mixed>
     */
    public function createDefault(): array
    {
        return [
            'intro' => $this->createBlock(self::TYPE_TRAVEL_PLAN_INTRO),
            'tripProfile' => $this->createBlock(self::TYPE_TRIP_PROFILE),
            'destinations' => [],
        ];
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    public function createBlock(string $type, array $values = []): array
    {
        $block = match ($type) {
            self::TYPE_TRAVEL_PLAN_INTRO => [
                'type' => $type,
                'title' => '',
                'text' => '',
            ],
            self::TYPE_TRIP_PROFILE => [
                'type' => $type,
                'startDate' => '',
                'endDate' => '',
                'period' => '',
                'duration' => '',
                'travelParty' => '',
                'travelStyle' => '',
                'packageType' => '',
                'showTableOfContents' => false,
            ],
            self::TYPE_DESTINATION => [
                'type' => $type,
                'startOnNewPage' => false,
                'colorVariant' => ColorVariant::Auto->value,
                'title' => '',
                'country' => '',
                'region' => '',
                'city' => '',
                'text' => '',
                'icon' => SectionType::Destination->defaultIcon(),
                'sections' => [],
            ],
            self::TYPE_IMAGE => [
                'type' => $type,
                'startOnNewPage' => false,
                'title' => '',
                'image' => null,
                'caption' => '',
            ],
            self::TYPE_ROUTE_OVERVIEW => [
                'type' => $type,
                'startOnNewPage' => false,
                'colorVariant' => ColorVariant::Auto->value,
                'title' => '',
                'text' => '',
                'routeStops' => [],
            ],
            self::TYPE_ROUTE_STOP => [
                'type' => $type,
                'title' => '',
                'location' => '',
                'text' => '',
                'icon' => SectionType::RouteOverview->defaultIcon(),
            ],
            self::TYPE_DAY => [
                'type' => $type,
                'startOnNewPage' => false,
                'colorVariant' => ColorVariant::Auto->value,
                'dayNumber' => '',
                'title' => '',
                'dateLabel' => '',
                'destinationTimezone' => '',
                'intro' => '',
                'blocks' => [],
            ],
            self::TYPE_ACTIVITY,
            self::TYPE_ACCOMMODATION,
            self::TYPE_TRANSPORT,
            self::TYPE_MEAL => [
                'type' => $type,
                'startOnNewPage' => false,
                'colorVariant' => ColorVariant::Auto->value,
                'title' => '',
                'text' => '',
                'icon' => $this->defaultIcon($type),
                'location' => '',
                'timeLabel' => '',
                'time' => '',
                'startTime' => '',
                'endTime' => '',
                'bookingUrl' => '',
            ],
            self::TYPE_TIP,
            self::TYPE_NOTE,
            self::TYPE_FREE_TEXT => [
                'type' => $type,
                'startOnNewPage' => false,
                'colorVariant' => ColorVariant::Auto->value,
                'title' => '',
                'text' => '',
                'icon' => $this->defaultIcon($type),
                'location' => '',
                'timeLabel' => '',
                'time' => '',
                'startTime' => '',
                'endTime' => '',
            ],
            self::TYPE_PRACTICAL_INFO,
            self::TYPE_BUDGET_NOTE,
            self::TYPE_PERSONAL_NOTE => [
                'type' => $type,
                'startOnNewPage' => false,
                'colorVariant' => ColorVariant::Auto->value,
                'title' => '',
                'text' => '',
                'icon' => $this->defaultIcon($type),
            ],
            self::TYPE_CHECKLIST => [
                'type' => $type,
                'startOnNewPage' => false,
                'colorVariant' => ColorVariant::Auto->value,
                'title' => '',
                'text' => '',
            ],
            default => throw new \InvalidArgumentException(\sprintf(
                'Unsupported travel plan block type "%s".',
                $type,
            )),
        };

        return \array_replace($block, $values);
    }

    /**
     * @return list<string>
     */
    public static function supportedBlockTypes(): array
    {
        return [
            self::TYPE_TRAVEL_PLAN_INTRO,
            self::TYPE_TRIP_PROFILE,
            self::TYPE_DESTINATION,
            self::TYPE_IMAGE,
            self::TYPE_ROUTE_OVERVIEW,
            self::TYPE_ROUTE_STOP,
            self::TYPE_DAY,
            self::TYPE_PRACTICAL_INFO,
            self::TYPE_CHECKLIST,
            self::TYPE_BUDGET_NOTE,
            self::TYPE_PERSONAL_NOTE,
            self::TYPE_FREE_TEXT,
            self::TYPE_ACTIVITY,
            self::TYPE_ACCOMMODATION,
            self::TYPE_TRANSPORT,
            self::TYPE_MEAL,
            self::TYPE_TIP,
            self::TYPE_NOTE,
        ];
    }

    /**
     * @param array<string, mixed> $content
     *
     * @return array<string, mixed>
     */
    public function toFormData(array $content): array
    {
        $content = TravelPlanContent::fromArray($this->stringKeyedArray(\array_replace_recursive($this->createDefault(), $content)));
        $tripProfile = $content->tripProfile->raw;

        return [
            'introTitle' => $content->introTitle,
            'introText' => $content->introText,
            'startDate' => $this->normalizeDateValue($tripProfile['startDate'] ?? null),
            'endDate' => $this->normalizeDateValue($tripProfile['endDate'] ?? null),
            'travelParty' => $content->tripProfile->travelParty,
            'travelStyle' => $content->tripProfile->travelStyle,
            'packageType' => $content->tripProfile->packageType,
            'showTableOfContents' => $this->tableOfContentsValue($content->tripProfile->showTableOfContents),
            'destinations' => $this->normalizeDestinations($content->destinations),
        ];
    }

    /**
     * @param array<string, mixed> $formData
     * @param array<string, mixed> $currentContent
     *
     * @return array<string, mixed>
     */
    public function fromFormData(array $formData, array $currentContent = []): array
    {
        $currentContent = TravelPlanContent::fromArray($currentContent);
        $currentTripProfile = $currentContent->tripProfile->raw;
        $startDate = $this->normalizeDateValue($formData['startDate'] ?? null);
        $endDate = $this->normalizeDateValue($formData['endDate'] ?? null);

        $this->validateDateRange($startDate, $endDate);

        $derivedPeriod = $this->formatPeriod($startDate, $endDate);
        $derivedDuration = $this->formatDuration($startDate, $endDate);
        $rawContent = [
            'intro' => $this->createBlock(self::TYPE_TRAVEL_PLAN_INTRO, [
                'title' => $this->stringValue($formData, 'introTitle'),
                'text' => $this->stringValue($formData, 'introText'),
            ]),
            'tripProfile' => $this->createBlock(self::TYPE_TRIP_PROFILE, [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'period' => '' !== $derivedPeriod ? $derivedPeriod : $this->stringValue($currentTripProfile, 'period'),
                'duration' => '' !== $derivedDuration ? $derivedDuration : $this->stringValue($currentTripProfile, 'duration'),
                'travelParty' => $this->stringValue($formData, 'travelParty'),
                'travelStyle' => $this->stringValue($formData, 'travelStyle'),
                'packageType' => $this->stringValue($formData, 'packageType'),
                'showTableOfContents' => $this->tableOfContentsValue($formData['showTableOfContents'] ?? null),
            ]),
            'destinations' => $this->normalizeFormDestinations($formData['destinations'] ?? []),
        ];

        return $this->contentToStorageArray(TravelPlanContent::fromArray($rawContent));
    }

    /**
     * @param list<Destination> $destinations
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeDestinations(array $destinations): array
    {
        $normalized = [];

        foreach ($destinations as $destination) {
            if ($destination->isImage()) {
                $normalized[] = $this->imageToArray($destination);
                continue;
            }

            $normalized[] = $this->destinationToArray($destination);
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function destinationToArray(Destination $destination): array
    {
        return $this->createBlock(self::TYPE_DESTINATION, [
            'startOnNewPage' => $destination->startOnNewPage,
            'colorVariant' => $destination->colorVariant->value,
            'title' => $this->stringValue($destination->raw, 'title'),
            'country' => $this->stringValue($destination->raw, 'country'),
            'region' => $this->stringValue($destination->raw, 'region'),
            'city' => $this->stringValue($destination->raw, 'city'),
            'text' => $this->stringValue($destination->raw, 'text'),
            'icon' => $destination->iconOrDefault(),
            'sections' => $this->normalizeDestinationSections($destination->sections),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function imageToArray(Destination $destination): array
    {
        return $this->createBlock(self::TYPE_IMAGE, [
            'startOnNewPage' => $destination->startOnNewPage,
            'title' => $this->stringValue($destination->raw, 'title'),
            'image' => $this->mediaValue($destination->raw['image'] ?? null),
            'caption' => $this->stringValue($destination->raw, 'caption'),
        ]);
    }

    /**
     * @param list<Section> $sections
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeDestinationSections(array $sections): array
    {
        $normalized = [];

        foreach ($sections as $section) {
            $type = $section->type->value;
            if (!\in_array($type, self::DESTINATION_SECTION_TYPES, true)) {
                continue;
            }

            $defaults = $this->createBlock($type);
            $values = [];
            foreach (\array_keys($defaults) as $field) {
                if ('type' === $field) {
                    continue;
                }

                $values[$field] = match ($field) {
                    'blocks' => $this->normalizeDayBlocks($section->blocks),
                    'routeStops' => $this->normalizeRouteStops($section->routeStops),
                    'startOnNewPage' => $section->startOnNewPage,
                    'colorVariant' => $section->colorVariant->value,
                    'icon' => $this->sectionIcon($section),
                    default => $this->stringValue($section->raw, $field),
                };
            }

            $normalized[] = $this->createBlock($type, $values);
        }

        return $normalized;
    }

    /**
     * @param list<array<string, mixed>> $routeStops
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeRouteStops(array $routeStops): array
    {
        $normalized = [];

        foreach ($routeStops as $routeStop) {
            $type = $routeStop['type'] ?? null;
            if (!\in_array($type, [self::TYPE_ROUTE_STOP, 'route_step'], true)) {
                continue;
            }

            $normalized[] = $this->createBlock(self::TYPE_ROUTE_STOP, [
                'title' => $this->stringValue($routeStop, 'title'),
                'location' => $this->stringValue($routeStop, 'location'),
                'text' => $this->stringValue($routeStop, 'text'),
                'icon' => $this->stringValue($routeStop, 'icon') ?: SectionType::RouteOverview->defaultIcon(),
            ]);
        }

        return $normalized;
    }

    /**
     * @param list<DayBlock> $blocks
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeDayBlocks(array $blocks): array
    {
        $normalized = [];

        foreach ($blocks as $block) {
            $type = $block->type->value;
            $defaults = $this->createBlock($type);
            $values = [];

            foreach (\array_keys($defaults) as $field) {
                if ('type' === $field) {
                    continue;
                }

                $values[$field] = match (true) {
                    'startOnNewPage' === $field => $block->startOnNewPage,
                    'colorVariant' === $field => $block->colorVariant->value,
                    'icon' === $field => $block->iconOrDefault(),
                    \in_array($field, ['time', 'startTime', 'endTime'], true) => $this->normalizeTimeValue($block->raw[$field] ?? null),
                    default => $this->stringValue($block->raw, $field),
                };
            }

            $normalized[] = $this->createBlock($type, $values);
        }

        return $normalized;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeFormDestinations(mixed $destinations): array
    {
        $rawDestinations = [];
        if (\is_array($destinations)) {
            foreach ($destinations as $destination) {
                $destination = $this->stringKeyedArray($destination);
                if ([] === $destination) {
                    continue;
                }

                $type = $destination['type'] ?? self::TYPE_DESTINATION;
                if (self::TYPE_IMAGE === $type) {
                    $rawDestinations[] = $this->createBlock(self::TYPE_IMAGE, [
                        'startOnNewPage' => $this->boolValue($destination, 'startOnNewPage'),
                        'title' => $this->stringValue($destination, 'title'),
                        'image' => $this->mediaValue($destination['image'] ?? null),
                        'caption' => $this->stringValue($destination, 'caption'),
                    ]);
                    continue;
                }

                if (self::TYPE_DESTINATION !== $type) {
                    continue;
                }

                $rawDestinations[] = $this->createBlock(self::TYPE_DESTINATION, [
                    'startOnNewPage' => $this->boolValue($destination, 'startOnNewPage'),
                    'colorVariant' => $this->colorVariantValue($destination['colorVariant'] ?? null),
                    'title' => $this->stringValue($destination, 'title'),
                    'country' => $this->stringValue($destination, 'country'),
                    'region' => $this->stringValue($destination, 'region'),
                    'city' => $this->stringValue($destination, 'city'),
                    'text' => $this->stringValue($destination, 'text'),
                    'icon' => $this->stringValue($destination, 'icon') ?: SectionType::Destination->defaultIcon(),
                    'sections' => $this->normalizeFormDestinationSections($destination['sections'] ?? []),
                ]);
            }
        }

        return $this->normalizeDestinations(TravelPlanContent::fromArray([
            'destinations' => $rawDestinations,
        ])->destinations);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeFormDestinationSections(mixed $sections): array
    {
        if (!\is_array($sections)) {
            return [];
        }

        $normalized = [];
        foreach ($sections as $section) {
            $section = $this->stringKeyedArray($section);
            if ([] === $section) {
                continue;
            }

            $type = $section['type'] ?? null;
            if (!\is_string($type) || !\in_array($type, self::DESTINATION_SECTION_TYPES, true)) {
                continue;
            }

            $defaults = $this->createBlock($type);
            $values = [];
            foreach (\array_keys($defaults) as $field) {
                if ('type' === $field) {
                    continue;
                }

                $values[$field] = match ($field) {
                    'blocks' => $this->normalizeFormDayBlocks($section['blocks'] ?? []),
                    'routeStops' => $this->normalizeFormRouteStops($section['routeStops'] ?? []),
                    'startOnNewPage' => $this->boolValue($section, $field),
                    'colorVariant' => $this->colorVariantValue($section[$field] ?? null),
                    default => $this->stringValue($section, $field),
                };
            }

            $normalized[] = $this->createBlock($type, $values);
        }

        return $normalized;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeFormRouteStops(mixed $routeStops): array
    {
        if (!\is_array($routeStops)) {
            return [];
        }

        $normalized = [];
        foreach ($routeStops as $routeStop) {
            $routeStop = $this->stringKeyedArray($routeStop);
            if ([] === $routeStop) {
                continue;
            }

            $type = $routeStop['type'] ?? null;
            if (!\in_array($type, [self::TYPE_ROUTE_STOP, 'route_step'], true)) {
                continue;
            }

            $normalized[] = $this->createBlock(self::TYPE_ROUTE_STOP, [
                'title' => $this->stringValue($routeStop, 'title'),
                'location' => $this->stringValue($routeStop, 'location'),
                'text' => $this->stringValue($routeStop, 'text'),
                'icon' => $this->stringValue($routeStop, 'icon') ?: SectionType::RouteOverview->defaultIcon(),
            ]);
        }

        return $normalized;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeFormDayBlocks(mixed $blocks): array
    {
        if (!\is_array($blocks)) {
            return [];
        }

        $allowedTypes = [
            self::TYPE_ACTIVITY,
            self::TYPE_ACCOMMODATION,
            self::TYPE_TRANSPORT,
            self::TYPE_MEAL,
            self::TYPE_TIP,
            self::TYPE_NOTE,
            self::TYPE_FREE_TEXT,
        ];
        $normalized = [];

        foreach ($blocks as $block) {
            $block = $this->stringKeyedArray($block);
            if ([] === $block) {
                continue;
            }

            $type = $block['type'] ?? null;
            if (!\is_string($type) || !\in_array($type, $allowedTypes, true)) {
                continue;
            }

            $defaults = $this->createBlock($type);
            $values = [];
            foreach (\array_keys($defaults) as $field) {
                if ('type' === $field) {
                    continue;
                }

                $values[$field] = match (true) {
                    'startOnNewPage' === $field => $this->boolValue($block, $field),
                    'colorVariant' === $field => $this->colorVariantValue($block[$field] ?? null),
                    \in_array($field, ['time', 'startTime', 'endTime'], true) => $this->normalizeTimeValue($block[$field] ?? null),
                    default => $this->stringValue($block, $field),
                };
            }

            $normalized[] = $this->createBlock($type, $values);
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function contentToStorageArray(TravelPlanContent $content): array
    {
        return [
            'intro' => $this->createBlock(self::TYPE_TRAVEL_PLAN_INTRO, [
                'title' => $content->introTitle,
                'text' => $content->introText,
            ]),
            'tripProfile' => $this->createBlock(self::TYPE_TRIP_PROFILE, [
                'startDate' => $content->tripProfile->startDate,
                'endDate' => $content->tripProfile->endDate,
                'period' => $content->tripProfile->period,
                'duration' => $content->tripProfile->duration,
                'travelParty' => $content->tripProfile->travelParty,
                'travelStyle' => $content->tripProfile->travelStyle,
                'packageType' => $content->tripProfile->packageType,
                'showTableOfContents' => $this->tableOfContentsValue($content->tripProfile->showTableOfContents),
            ]),
            'destinations' => $this->normalizeDestinations($content->destinations),
        ];
    }

    private function sectionIcon(Section $section): string
    {
        if ('' !== $section->icon) {
            return $section->icon;
        }

        return match ($section->type) {
            SectionType::Checklist => '',
            default => $section->type->defaultIcon(),
        };
    }

    private function mediaValue(mixed $value): mixed
    {
        if (\is_array($value)) {
            return $value;
        }

        if (\is_scalar($value) && '' !== \trim((string) $value)) {
            return $value;
        }

        return null;
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
     * @param array<string, mixed> $data
     */
    private function stringValue(array $data, string $key): string
    {
        $value = $data[$key] ?? '';

        return \is_scalar($value) ? (string) $value : '';
    }

    private function tableOfContentsValue(mixed $value): string
    {
        if (!\is_scalar($value)) {
            return 'none';
        }

        return match (\trim((string) $value)) {
            'one', 'Een laag' => 'one',
            'two', 'Twee lagen' => 'two',
            'none', '', 'Geen' => 'none',
            default => 'none',
        };
    }

    private function colorVariantValue(mixed $value): string
    {
        if (!\is_scalar($value)) {
            return ColorVariant::Auto->value;
        }

        return match (\trim((string) $value)) {
            'primary', 'Primair', 'Blauw' => ColorVariant::Primary->value,
            'secondary', 'Secundair', 'Geel' => ColorVariant::Secondary->value,
            'gold', 'Goud' => ColorVariant::Gold->value,
            'auto', '', 'Standaard' => ColorVariant::Auto->value,
            default => ColorVariant::Auto->value,
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    private function boolValue(array $data, string $key): bool
    {
        $value = $data[$key] ?? false;

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

    private function normalizeTimeValue(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        if (!\is_scalar($value)) {
            return '';
        }

        $value = \trim((string) $value);

        if ('' === $value) {
            return '';
        }

        if (1 === \preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/D', $value, $matches)) {
            return \sprintf('%02d:%s', (int) $matches[1], $matches[2]);
        }

        try {
            return (new \DateTimeImmutable($value))->format('H:i');
        } catch (\Throwable) {
            return '';
        }
    }

    private function normalizeDateValue(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (!\is_scalar($value)) {
            return '';
        }

        $value = \trim((string) $value);

        if ('' === $value) {
            return '';
        }

        if (1 === \preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
            return $value;
        }

        try {
            return (new \DateTimeImmutable($value))->format('Y-m-d');
        } catch (\Throwable) {
            return '';
        }
    }

    private function validateDateRange(string $startDate, string $endDate): void
    {
        if ('' === $startDate || '' === $endDate) {
            return;
        }

        if ($this->createDate($endDate) < $this->createDate($startDate)) {
            throw new \InvalidArgumentException('End date cannot be before start date.');
        }
    }

    private function formatPeriod(string $startDate, string $endDate): string
    {
        if ('' === $startDate && '' === $endDate) {
            return '';
        }

        if ('' === $startDate) {
            return $this->formatDateLabel($endDate);
        }

        if ('' === $endDate) {
            return $this->formatDateLabel($startDate);
        }

        return \sprintf('%s t/m %s', $this->formatDateLabel($startDate), $this->formatDateLabel($endDate));
    }

    private function formatDuration(string $startDate, string $endDate): string
    {
        if ('' === $startDate || '' === $endDate) {
            return '';
        }

        $days = $this->createDate($endDate)->diff($this->createDate($startDate))->days;

        return \sprintf('%d %s', $days + 1, 1 === $days + 1 ? 'dag' : 'dagen');
    }

    private function formatDateLabel(string $date): string
    {
        if ('' === $date) {
            return '';
        }

        $formatter = new \IntlDateFormatter('nl_NL', \IntlDateFormatter::LONG, \IntlDateFormatter::NONE);
        $formatter->setPattern('d MMMM yyyy');

        $formatted = $formatter->format($this->createDate($date));

        return false === $formatted ? $date : $formatted;
    }

    private function createDate(string $date): \DateTimeImmutable
    {
        return new \DateTimeImmutable($date . ' 00:00:00');
    }

    private function defaultIcon(string $type): string
    {
        return match ($type) {
            self::TYPE_ACTIVITY => BlockType::Activity->defaultIcon(),
            self::TYPE_ACCOMMODATION => BlockType::Accommodation->defaultIcon(),
            self::TYPE_TRANSPORT => BlockType::Transport->defaultIcon(),
            self::TYPE_MEAL => BlockType::Meal->defaultIcon(),
            self::TYPE_TIP => BlockType::Tip->defaultIcon(),
            self::TYPE_DESTINATION => SectionType::Destination->defaultIcon(),
            self::TYPE_BUDGET_NOTE => SectionType::BudgetNote->defaultIcon(),
            self::TYPE_PERSONAL_NOTE => SectionType::PersonalNote->defaultIcon(),
            self::TYPE_NOTE => BlockType::Note->defaultIcon(),
            default => SectionType::PracticalInfo->defaultIcon(),
        };
    }
}

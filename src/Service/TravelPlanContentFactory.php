<?php

declare(strict_types=1);

namespace App\Service;

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

    /** @var string[] */
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
                'colorVariant' => 'auto',
                'title' => '',
                'country' => '',
                'region' => '',
                'city' => '',
                'text' => '',
                'icon' => 'map',
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
                'colorVariant' => 'auto',
                'title' => '',
                'text' => '',
                'routeStops' => [],
            ],
            self::TYPE_ROUTE_STOP => [
                'type' => $type,
                'title' => '',
                'location' => '',
                'text' => '',
                'icon' => 'map',
            ],
            self::TYPE_DAY => [
                'type' => $type,
                'startOnNewPage' => false,
                'colorVariant' => 'auto',
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
                'colorVariant' => 'auto',
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
                'colorVariant' => 'auto',
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
                'colorVariant' => 'auto',
                'title' => '',
                'text' => '',
                'icon' => $this->defaultIcon($type),
            ],
            self::TYPE_CHECKLIST => [
                'type' => $type,
                'startOnNewPage' => false,
                'colorVariant' => 'auto',
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
     * @return string[]
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
        $content = \array_replace_recursive($this->createDefault(), $content);
        $intro = \is_array($content['intro']) ? $content['intro'] : [];
        $tripProfile = \is_array($content['tripProfile']) ? $content['tripProfile'] : [];

        return [
            'introTitle' => $this->stringValue($intro, 'title'),
            'introText' => $this->stringValue($intro, 'text'),
            'startDate' => $this->normalizeDateValue($tripProfile['startDate'] ?? null),
            'endDate' => $this->normalizeDateValue($tripProfile['endDate'] ?? null),
            'travelParty' => $this->stringValue($tripProfile, 'travelParty'),
            'travelStyle' => $this->stringValue($tripProfile, 'travelStyle'),
            'packageType' => $this->stringValue($tripProfile, 'packageType'),
            'showTableOfContents' => $this->tableOfContentsValue($tripProfile['showTableOfContents'] ?? null),
            'destinations' => $this->normalizeDestinations($content['destinations'] ?? []),
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
        $currentTripProfile = \is_array($currentContent['tripProfile'] ?? null)
            ? $currentContent['tripProfile']
            : [];
        $startDate = $this->normalizeDateValue($formData['startDate'] ?? null);
        $endDate = $this->normalizeDateValue($formData['endDate'] ?? null);

        $this->validateDateRange($startDate, $endDate);

        $derivedPeriod = $this->formatPeriod($startDate, $endDate);
        $derivedDuration = $this->formatDuration($startDate, $endDate);

        return [
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
            'destinations' => $this->normalizeDestinations($formData['destinations'] ?? []),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeDestinations(mixed $destinations): array
    {
        if (!\is_array($destinations)) {
            return [];
        }

        $normalized = [];

        foreach ($destinations as $destination) {
            if (!\is_array($destination)) {
                continue;
            }

            $type = $destination['type'] ?? self::TYPE_DESTINATION;

            if (self::TYPE_IMAGE === $type) {
                $normalized[] = $this->createBlock(self::TYPE_IMAGE, [
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

            $normalized[] = $this->createBlock(self::TYPE_DESTINATION, [
                'startOnNewPage' => $this->boolValue($destination, 'startOnNewPage'),
                'colorVariant' => $this->colorVariantValue($destination['colorVariant'] ?? null),
                'title' => $this->stringValue($destination, 'title'),
                'country' => $this->stringValue($destination, 'country'),
                'region' => $this->stringValue($destination, 'region'),
                'city' => $this->stringValue($destination, 'city'),
                'text' => $this->stringValue($destination, 'text'),
                'icon' => $this->stringValue($destination, 'icon') ?: 'map',
                'sections' => $this->normalizeDestinationSections($destination['sections'] ?? []),
            ]);
        }

        return $normalized;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeDestinationSections(mixed $sections): array
    {
        if (!\is_array($sections)) {
            return [];
        }

        $normalized = [];
        foreach ($sections as $section) {
            if (!\is_array($section)) {
                continue;
            }

            $type = $section['type'] ?? null;

            if (!\is_string($type) || !\in_array($type, self::DESTINATION_SECTION_TYPES, true)) {
                continue;
            }

            if (self::TYPE_DAY === $type) {
                $normalized[] = $this->createBlock($type, [
                    'startOnNewPage' => $this->boolValue($section, 'startOnNewPage'),
                    'colorVariant' => $this->colorVariantValue($section['colorVariant'] ?? null),
                    'dayNumber' => $this->normalizeOptionalPositiveInt($section['dayNumber'] ?? null),
                    'title' => $this->stringValue($section, 'title'),
                    'dateLabel' => $this->stringValue($section, 'dateLabel'),
                    'destinationTimezone' => $this->stringValue($section, 'destinationTimezone'),
                    'intro' => $this->stringValue($section, 'intro'),
                    'blocks' => $this->normalizeDayBlocks($section['blocks'] ?? []),
                ]);

                continue;
            }

            if (self::TYPE_ROUTE_OVERVIEW === $type) {
                $normalized[] = $this->createBlock($type, [
                    'startOnNewPage' => $this->boolValue($section, 'startOnNewPage'),
                    'colorVariant' => $this->colorVariantValue($section['colorVariant'] ?? null),
                    'title' => $this->stringValue($section, 'title'),
                    'text' => $this->stringValue($section, 'text'),
                    'routeStops' => $this->normalizeRouteStops($section['routeStops'] ?? []),
                ]);

                continue;
            }

            $defaults = $this->createBlock($type);
            $values = [];

            foreach (\array_keys($defaults) as $field) {
                if ('type' !== $field) {
                    $values[$field] = match ($field) {
                        'startOnNewPage' => $this->boolValue($section, $field),
                        'colorVariant' => $this->colorVariantValue($section[$field] ?? null),
                        default => $this->stringValue($section, $field),
                    };
                }
            }

            $normalized[] = $this->createBlock($type, $values);
        }

        return $normalized;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRouteStops(mixed $routeStops): array
    {
        if (!\is_array($routeStops)) {
            return [];
        }

        $normalized = [];

        foreach ($routeStops as $routeStop) {
            if (!\is_array($routeStop)) {
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
                'icon' => $this->stringValue($routeStop, 'icon') ?: 'map',
            ]);
        }

        return $normalized;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeDayBlocks(mixed $blocks): array
    {
        if (!\is_array($blocks)) {
            return [];
        }

        $normalized = [];
        $allowedTypes = [
            self::TYPE_ACTIVITY,
            self::TYPE_ACCOMMODATION,
            self::TYPE_TRANSPORT,
            self::TYPE_MEAL,
            self::TYPE_TIP,
            self::TYPE_NOTE,
            self::TYPE_FREE_TEXT,
        ];

        foreach ($blocks as $block) {
            if (!\is_array($block)) {
                continue;
            }

            $type = $block['type'] ?? null;

            if (!\is_string($type) || !\in_array($type, $allowedTypes, true)) {
                continue;
            }

            $defaults = $this->createBlock($type);
            $values = [];

            foreach (\array_keys($defaults) as $field) {
                if ('type' !== $field) {
                    $values[$field] = match (true) {
                        'startOnNewPage' === $field => $this->boolValue($block, $field),
                        'colorVariant' === $field => $this->colorVariantValue($block[$field] ?? null),
                        \in_array($field, ['time', 'startTime', 'endTime'], true) => $this->normalizeTimeValue($block[$field] ?? null),
                        default => $this->stringValue($block, $field),
                    };
                }
            }

            if ('' === ($values['startTime'] ?? '') && '' !== ($values['time'] ?? '')) {
                $values['startTime'] = $values['time'];
            }

            if ('' === ($values['time'] ?? '') && '' !== ($values['startTime'] ?? '')) {
                $values['time'] = $values['startTime'];
            }

            $normalized[] = $this->createBlock($type, $values);
        }

        return $normalized;
    }

    private function mediaValue(mixed $value): mixed
    {
        if (\is_array($value)) {
            return $value;
        }

        if (\is_scalar($value) && '' !== \trim((string)$value)) {
            return $value;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function stringValue(array $data, string $key): string
    {
        $value = $data[$key] ?? '';

        return \is_scalar($value) ? (string)$value : '';
    }

    private function tableOfContentsValue(mixed $value): string
    {
        if (!\is_scalar($value)) {
            return 'none';
        }

        return match (\trim((string)$value)) {
            'one', 'Een laag' => 'one',
            'two', 'Twee lagen' => 'two',
            'none', '', 'Geen' => 'none',
            default => 'none',
        };
    }

    private function colorVariantValue(mixed $value): string
    {
        if (!\is_scalar($value)) {
            return 'auto';
        }

        return match (\trim((string)$value)) {
            'primary', 'Primair', 'Blauw' => 'primary',
            'secondary', 'Secundair', 'Geel' => 'secondary',
            'auto', '', 'Standaard' => 'auto',
            default => 'auto',
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

    private function normalizeOptionalPositiveInt(mixed $value): int|string
    {
        if (!\is_scalar($value)) {
            return '';
        }

        $value = \trim((string)$value);

        if ('' === $value || 1 !== \preg_match('/^\d+$/D', $value)) {
            return '';
        }

        $number = (int)$value;

        return $number > 0 ? $number : '';
    }

    private function normalizeTimeValue(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        if (!\is_scalar($value)) {
            return '';
        }

        $value = \trim((string)$value);

        if ('' === $value) {
            return '';
        }

        if (1 === \preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/D', $value, $matches)) {
            return \sprintf('%02d:%s', (int)$matches[1], $matches[2]);
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

        $value = \trim((string)$value);

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

        $days = $this->createDate($startDate)->diff($this->createDate($endDate))->days;

        if (false === $days) {
            return '';
        }

        ++$days;

        return \sprintf('%d %s', $days, 1 === $days ? 'dag' : 'dagen');
    }

    private function formatDateLabel(string $date): string
    {
        $months = [
            1 => 'januari',
            2 => 'februari',
            3 => 'maart',
            4 => 'april',
            5 => 'mei',
            6 => 'juni',
            7 => 'juli',
            8 => 'augustus',
            9 => 'september',
            10 => 'oktober',
            11 => 'november',
            12 => 'december',
        ];
        $date = $this->createDate($date);

        return \sprintf('%d %s', (int)$date->format('j'), $months[(int)$date->format('n')]);
    }

    private function createDate(string $date): \DateTimeImmutable
    {
        return new \DateTimeImmutable($date . ' 00:00:00');
    }

    private function defaultIcon(string $type): string
    {
        return match ($type) {
            self::TYPE_ACTIVITY => 'compass',
            self::TYPE_ACCOMMODATION => 'bed',
            self::TYPE_TRANSPORT => 'car',
            self::TYPE_MEAL => 'utensils',
            self::TYPE_TIP => 'lightbulb',
            self::TYPE_DESTINATION => 'map',
            self::TYPE_BUDGET_NOTE => 'wallet',
            self::TYPE_PERSONAL_NOTE => 'heart',
            self::TYPE_NOTE => 'sticky-note',
            default => 'info',
        };
    }
}

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

    /**
     * @return array<string, mixed>
     */
    public function createDefault(): array
    {
        return [
            'intro' => $this->createBlock(self::TYPE_TRAVEL_PLAN_INTRO),
            'tripProfile' => $this->createBlock(self::TYPE_TRIP_PROFILE),
            'sections' => [],
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
                'period' => '',
                'duration' => '',
                'travelParty' => '',
                'travelStyle' => '',
                'packageType' => '',
            ],
            self::TYPE_DESTINATION => [
                'type' => $type,
                'title' => '',
                'country' => '',
                'region' => '',
                'city' => '',
                'text' => '',
                'icon' => 'map',
            ],
            self::TYPE_ROUTE_OVERVIEW => [
                'type' => $type,
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
                'dayNumber' => 1,
                'title' => '',
                'dateLabel' => '',
                'intro' => '',
                'blocks' => [],
            ],
            self::TYPE_ACTIVITY,
            self::TYPE_ACCOMMODATION,
            self::TYPE_TRANSPORT,
            self::TYPE_MEAL => [
                'type' => $type,
                'title' => '',
                'text' => '',
                'icon' => $this->defaultIcon($type),
                'location' => '',
                'timeLabel' => '',
                'bookingUrl' => '',
            ],
            self::TYPE_TIP,
            self::TYPE_NOTE,
            self::TYPE_FREE_TEXT => [
                'type' => $type,
                'title' => '',
                'text' => '',
                'icon' => $this->defaultIcon($type),
                'location' => '',
                'timeLabel' => '',
            ],
            self::TYPE_PRACTICAL_INFO,
            self::TYPE_BUDGET_NOTE,
            self::TYPE_PERSONAL_NOTE => [
                'type' => $type,
                'title' => '',
                'text' => '',
                'icon' => $this->defaultIcon($type),
            ],
            self::TYPE_CHECKLIST => [
                'type' => $type,
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
        $content = $this->upgradeLegacyContent($content);
        $content = \array_replace_recursive($this->createDefault(), $content);
        $intro = \is_array($content['intro']) ? $content['intro'] : [];
        $tripProfile = \is_array($content['tripProfile']) ? $content['tripProfile'] : [];

        return [
            'introTitle' => $this->stringValue($intro, 'title'),
            'introText' => $this->stringValue($intro, 'text'),
            'period' => $this->stringValue($tripProfile, 'period'),
            'duration' => $this->stringValue($tripProfile, 'duration'),
            'travelParty' => $this->stringValue($tripProfile, 'travelParty'),
            'travelStyle' => $this->stringValue($tripProfile, 'travelStyle'),
            'packageType' => $this->stringValue($tripProfile, 'packageType'),
            'sections' => $this->normalizeSections($content['sections'] ?? []),
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
        return [
            'intro' => $this->createBlock(self::TYPE_TRAVEL_PLAN_INTRO, [
                'title' => $this->stringValue($formData, 'introTitle'),
                'text' => $this->stringValue($formData, 'introText'),
            ]),
            'tripProfile' => $this->createBlock(self::TYPE_TRIP_PROFILE, [
                'period' => $this->stringValue($formData, 'period'),
                'duration' => $this->stringValue($formData, 'duration'),
                'travelParty' => $this->stringValue($formData, 'travelParty'),
                'travelStyle' => $this->stringValue($formData, 'travelStyle'),
                'packageType' => $this->stringValue($formData, 'packageType'),
            ]),
            'sections' => $this->normalizeSections($formData['sections'] ?? []),
        ];
    }

    /**
     * @param array<string, mixed> $content
     *
     * @return array<string, mixed>
     */
    private function upgradeLegacyContent(array $content): array
    {
        if (isset($content['tripProfile'], $content['sections'])) {
            return $content;
        }

        $overview = \is_array($content['overview'] ?? null) ? $content['overview'] : [];
        $sections = [];

        if ('' !== $this->stringValue($overview, 'destination')) {
            $sections[] = $this->createBlock(self::TYPE_DESTINATION, [
                'title' => $this->stringValue($overview, 'destination'),
                'country' => $this->stringValue($overview, 'destination'),
            ]);
        }

        if (\is_array($content['route'] ?? null) && [] !== $content['route']) {
            $sections[] = $this->createBlock(self::TYPE_ROUTE_OVERVIEW, [
                'routeStops' => $this->normalizeRouteStops($content['route']),
            ]);
        }

        foreach (['days', 'practicalInfo', 'personalNotes'] as $key) {
            if (\is_array($content[$key] ?? null)) {
                $sections = \array_merge($sections, $content[$key]);
            }
        }

        return [
            'intro' => \is_array($content['intro'] ?? null)
                ? $content['intro']
                : $this->createBlock(self::TYPE_TRAVEL_PLAN_INTRO),
            'tripProfile' => $this->createBlock(self::TYPE_TRIP_PROFILE, [
                'period' => $this->stringValue($overview, 'period'),
                'duration' => $this->stringValue($overview, 'duration'),
                'travelParty' => $this->stringValue($overview, 'travelParty'),
                'travelStyle' => $this->stringValue($overview, 'travelStyle'),
                'packageType' => $this->stringValue($overview, 'packageType'),
            ]),
            'sections' => $sections,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeSections(mixed $sections): array
    {
        if (!\is_array($sections)) {
            return [];
        }

        $normalized = [];
        $allowedTypes = [
            self::TYPE_DESTINATION,
            self::TYPE_ROUTE_OVERVIEW,
            self::TYPE_DAY,
            self::TYPE_PRACTICAL_INFO,
            self::TYPE_CHECKLIST,
            self::TYPE_BUDGET_NOTE,
            self::TYPE_PERSONAL_NOTE,
            self::TYPE_FREE_TEXT,
        ];

        foreach ($sections as $section) {
            if (!\is_array($section)) {
                continue;
            }

            $type = $section['type'] ?? null;

            if (!\is_string($type) || !\in_array($type, $allowedTypes, true)) {
                continue;
            }

            if (self::TYPE_DAY === $type) {
                $normalized[] = $this->createBlock($type, [
                    'dayNumber' => \max(1, (int) ($section['dayNumber'] ?? 1)),
                    'title' => $this->stringValue($section, 'title'),
                    'dateLabel' => $this->stringValue($section, 'dateLabel'),
                    'intro' => $this->stringValue($section, 'intro'),
                    'blocks' => $this->normalizeDayBlocks($section['blocks'] ?? []),
                ]);

                continue;
            }

            if (self::TYPE_ROUTE_OVERVIEW === $type) {
                $normalized[] = $this->createBlock($type, [
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
                    $values[$field] = $this->stringValue($section, $field);
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
                    $values[$field] = $this->stringValue($block, $field);
                }
            }

            $normalized[] = $this->createBlock($type, $values);
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function stringValue(array $data, string $key): string
    {
        $value = $data[$key] ?? '';

        return \is_scalar($value) ? (string) $value : '';
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

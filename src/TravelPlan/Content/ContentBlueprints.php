<?php

declare(strict_types=1);

namespace App\TravelPlan\Content;

/**
 * Canonieke blauwdrukken (defaults) per bloktype van het reisplan.
 * Eén bron voor de opslagstructuur: elk blok dat het systeem in gaat,
 * begint als blueprint en wordt met array_replace ingevuld.
 */
final readonly class ContentBlueprints
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
    public const DESTINATION_SECTION_TYPES = [
        self::TYPE_ROUTE_OVERVIEW,
        self::TYPE_DAY,
        self::TYPE_PRACTICAL_INFO,
        self::TYPE_CHECKLIST,
        self::TYPE_BUDGET_NOTE,
        self::TYPE_PERSONAL_NOTE,
        self::TYPE_FREE_TEXT,
    ];

    /** @var list<string> */
    public const DAY_BLOCK_TYPES = [
        self::TYPE_ACTIVITY,
        self::TYPE_ACCOMMODATION,
        self::TYPE_TRANSPORT,
        self::TYPE_MEAL,
        self::TYPE_TIP,
        self::TYPE_NOTE,
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
                'priceLabel' => '',
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

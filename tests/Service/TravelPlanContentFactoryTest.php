<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\TravelPlan\Content\TravelPlanContent;
use App\TravelPlan\Content\TravelPlanContentFactory;
use PHPUnit\Framework\TestCase;

final class TravelPlanContentFactoryTest extends TestCase
{
    public function testToFormDataLaatStorageVersieWeg(): void
    {
        $formData = (new TravelPlanContentFactory())->toFormData([
            '_version' => TravelPlanContent::VERSION,
            'destinations' => [
                [
                    '_version' => 99,
                    'type' => 'destination',
                    'title' => 'Lima',
                ],
            ],
        ]);

        self::assertArrayNotHasKey('_version', $formData);
        self::assertIsArray($formData['destinations']);
        self::assertIsArray($formData['destinations'][0]);
        self::assertArrayNotHasKey('_version', $formData['destinations'][0]);
    }

    public function testToFormDataKeepsNormalizedStructureFromTypedContent(): void
    {
        $factory = new TravelPlanContentFactory();

        self::assertSame([
            'introTitle' => 'Welkom',
            'introText' => '<p>Intro</p>',
            'startDate' => '2026-05-01',
            'endDate' => '2026-05-03',
            'travelParty' => 'Twee reizigers',
            'travelStyle' => 'Rustig',
            'packageType' => 'Compleet',
            'showTableOfContents' => 'two',
            'destinations' => [
                [
                    'type' => 'destination',
                    'startOnNewPage' => true,
                    'colorVariant' => 'primary',
                    'title' => 'Lima',
                    'country' => 'Peru',
                    'region' => 'Lima',
                    'city' => 'Lima',
                    'text' => '<p>Stad</p>',
                    'icon' => 'map',
                    'sections' => [
                        [
                            'type' => 'route_overview',
                            'startOnNewPage' => false,
                            'colorVariant' => 'auto',
                            'title' => 'Route',
                            'text' => '',
                            'routeStops' => [
                                [
                                    'type' => 'route_stop',
                                    'title' => 'Start',
                                    'location' => 'Lima',
                                    'text' => '',
                                    'icon' => 'map',
                                ],
                            ],
                        ],
                        [
                            'type' => 'day',
                            'startOnNewPage' => false,
                            'colorVariant' => 'gold',
                            'dayNumber' => '1',
                            'title' => 'Dag 1',
                            'dateLabel' => '1 mei',
                            'destinationTimezone' => 'America/Lima',
                            'intro' => '<p>Dagintro</p>',
                            'blocks' => [
                                [
                                    'type' => 'activity',
                                    'startOnNewPage' => false,
                                    'colorVariant' => 'secondary',
                                    'title' => 'Fietsen',
                                    'text' => '<p>Door de stad</p>',
                                    'icon' => 'compass',
                                    'location' => 'Centrum',
                                    'timeLabel' => '',
                                    'time' => '09:05',
                                    'startTime' => '09:00',
                                    'endTime' => '11:00',
                                    'bookingUrl' => 'https://example.test',
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'type' => 'image',
                    'startOnNewPage' => false,
                    'title' => 'Foto',
                    'image' => ['url' => '/foto.jpg'],
                    'caption' => 'Caption',
                ],
            ],
        ], $factory->toFormData([
            'intro' => ['title' => 'Welkom', 'text' => '<p>Intro</p>'],
            'tripProfile' => [
                'startDate' => '1 May 2026',
                'endDate' => new \DateTimeImmutable('2026-05-03'),
                'travelParty' => 'Twee reizigers',
                'travelStyle' => 'Rustig',
                'packageType' => 'Compleet',
                'showTableOfContents' => 'Twee lagen',
            ],
            'destinations' => [
                [
                    'type' => 'destination',
                    'startOnNewPage' => true,
                    'colorVariant' => 'primary',
                    'title' => 'Lima',
                    'country' => 'Peru',
                    'region' => 'Lima',
                    'city' => 'Lima',
                    'text' => '<p>Stad</p>',
                    'icon' => '',
                    'sections' => [
                        [
                            'type' => 'route_overview',
                            'title' => 'Route',
                            'routeStops' => [
                                ['type' => 'route_step', 'title' => 'Start', 'location' => 'Lima'],
                            ],
                        ],
                        [
                            'type' => 'day',
                            'colorVariant' => 'gold',
                            'dayNumber' => '1',
                            'title' => 'Dag 1',
                            'dateLabel' => '1 mei',
                            'destinationTimezone' => 'America/Lima',
                            'intro' => '<p>Dagintro</p>',
                            'blocks' => [
                                [
                                    'type' => 'activity',
                                    'colorVariant' => 'secondary',
                                    'title' => 'Fietsen',
                                    'text' => '<p>Door de stad</p>',
                                    'icon' => '',
                                    'location' => 'Centrum',
                                    'time' => '9:05',
                                    'startTime' => '09:00',
                                    'endTime' => '11:00',
                                    'bookingUrl' => 'https://example.test',
                                ],
                                ['type' => 'unknown'],
                            ],
                        ],
                    ],
                ],
                ['type' => 'unknown'],
                ['type' => 'image', 'title' => 'Foto', 'image' => ['url' => '/foto.jpg'], 'caption' => 'Caption'],
            ],
        ]));
    }

    public function testFromFormDataKeepsStorageShapeAfterTypedValidation(): void
    {
        $factory = new TravelPlanContentFactory();

        self::assertSame([
            '_version' => TravelPlanContent::VERSION,
            'intro' => [
                'type' => 'travel_plan_intro',
                'title' => 'Welkom',
                'text' => '<p>Intro</p>',
            ],
            'tripProfile' => [
                'type' => 'trip_profile',
                'startDate' => '2026-05-01',
                'endDate' => '2026-05-03',
                'period' => '1 mei 2026 t/m 3 mei 2026',
                'duration' => '3 dagen',
                'travelParty' => 'Twee reizigers',
                'travelStyle' => 'Rustig',
                'packageType' => 'Compleet',
                'showTableOfContents' => 'one',
            ],
            'destinations' => [
                [
                    'type' => 'destination',
                    'startOnNewPage' => true,
                    'colorVariant' => 'primary',
                    'title' => 'Lima',
                    'country' => 'Peru',
                    'region' => '',
                    'city' => 'Lima',
                    'text' => '<p>Stad</p>',
                    'icon' => 'map',
                    'sections' => [
                        [
                            'type' => 'day',
                            'startOnNewPage' => false,
                            'colorVariant' => 'secondary',
                            'dayNumber' => '1',
                            'title' => 'Dag 1',
                            'dateLabel' => '1 mei',
                            'destinationTimezone' => 'America/Lima',
                            'intro' => '<p>Dagintro</p>',
                            'blocks' => [
                                [
                                    'type' => 'meal',
                                    'startOnNewPage' => false,
                                    'colorVariant' => 'auto',
                                    'title' => 'Lunch',
                                    'text' => '',
                                    'icon' => 'utensils',
                                    'location' => 'Markt',
                                    'timeLabel' => '',
                                    'time' => '12:05',
                                    'startTime' => '',
                                    'endTime' => '',
                                    'bookingUrl' => '',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $factory->fromFormData([
            'introTitle' => 'Welkom',
            'introText' => '<p>Intro</p>',
            'startDate' => '2026-05-01',
            'endDate' => '2026-05-03',
            'travelParty' => 'Twee reizigers',
            'travelStyle' => 'Rustig',
            'packageType' => 'Compleet',
            'showTableOfContents' => 'Een laag',
            'destinations' => [
                [
                    'startOnNewPage' => 'yes',
                    'colorVariant' => 'Primair',
                    'title' => 'Lima',
                    'country' => 'Peru',
                    'city' => 'Lima',
                    'text' => '<p>Stad</p>',
                    'icon' => '',
                    'sections' => [
                        [
                            'type' => 'day',
                            'colorVariant' => 'Secundair',
                            'dayNumber' => '1',
                            'title' => 'Dag 1',
                            'dateLabel' => '1 mei',
                            'destinationTimezone' => 'America/Lima',
                            'intro' => '<p>Dagintro</p>',
                            'blocks' => [
                                [
                                    'type' => 'meal',
                                    'title' => 'Lunch',
                                    'icon' => '',
                                    'location' => 'Markt',
                                    'time' => '12:05',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]));
    }
}

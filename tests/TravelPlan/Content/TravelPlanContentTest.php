<?php

declare(strict_types=1);

namespace App\Tests\TravelPlan\Content;

use App\TravelPlan\Content\BlockType;
use App\TravelPlan\Content\ColorVariant;
use App\TravelPlan\Content\SectionType;
use App\TravelPlan\Content\StorageNormalizer;
use App\TravelPlan\Content\TravelPlanContent;
use PHPUnit\Framework\TestCase;

final class TravelPlanContentTest extends TestCase
{
    public function testFromArrayParsesFullStructure(): void
    {
        $content = TravelPlanContent::fromArray([
            'intro' => ['title' => 'Welkom!', 'text' => '<p>Fijne reis</p>'],
            'tripProfile' => [
                'period' => '26 augustus t/m 21 september',
                'duration' => '27 dagen',
                'showTableOfContents' => '1',
            ],
            'destinations' => [
                [
                    'type' => 'destination',
                    'title' => 'Even landen | LIMA',
                    'city' => 'Lima',
                    'region' => '',
                    'country' => 'Peru',
                    'colorVariant' => 'primary',
                    'startOnNewPage' => 'true',
                    'sections' => [
                        [
                            'type' => 'day',
                            'title' => 'Overnachting',
                            'destinationTimezone' => 'America/Lima',
                            'intro' => '<p>Intro</p>',
                            'blocks' => [
                                [
                                    'type' => 'accommodation',
                                    'title' => 'Hotel Antigua',
                                    'text' => '<p>Boutique hotel</p>',
                                    'colorVariant' => 'secondary',
                                    'startOnNewPage' => false,
                                ],
                                ['type' => 'onbekend-type-wordt-genegeerd'],
                                'geen-array-wordt-genegeerd',
                            ],
                        ],
                        ['type' => 'free_text', 'title' => 'Vervoer', 'text' => '<p>Taxi</p>'],
                        ['type' => 'destination', 'title' => 'genest destination-type wordt genegeerd'],
                    ],
                ],
                ['type' => 'image', 'title' => 'Sfeerbeeld', 'image' => ['id' => 12]],
                ['type' => 'volledig-onbekend'],
            ],
        ]);

        self::assertSame('Welkom!', $content->introTitle);
        self::assertSame('27 dagen', $content->tripProfile->duration);
        self::assertCount(2, $content->destinations);

        $lima = $content->destinations[0];
        self::assertSame(SectionType::Destination, $lima->type);
        self::assertSame('Lima, Peru', $lima->locationLabel());
        self::assertSame(ColorVariant::Primary, $lima->colorVariant);
        self::assertTrue($lima->startOnNewPage);
        self::assertCount(2, $lima->sections);

        $day = $lima->sections[0];
        self::assertSame(SectionType::Day, $day->type);
        self::assertSame('America/Lima', $day->destinationTimezone);
        self::assertTrue($day->hasBlocks());
        self::assertCount(1, $day->blocks);
        self::assertSame(BlockType::Accommodation, $day->blocks[0]->type);
        self::assertSame(ColorVariant::Secondary, $day->blocks[0]->colorVariant);
        self::assertSame('bed', $day->blocks[0]->iconOrDefault());

        self::assertTrue($content->destinations[1]->isImage());
    }

    public function testFromArrayIsSafeForEmptyAndMalformedContent(): void
    {
        self::assertSame([], TravelPlanContent::fromArray([])->destinations);
        self::assertSame('', TravelPlanContent::fromArray(['intro' => 'geen-array'])->introTitle);
        self::assertSame(
            [],
            TravelPlanContent::fromArray(['destinations' => 'geen-array'])->destinations,
        );
    }

    public function testFromArrayAccepteertContentMetEnZonderVersieGelijk(): void
    {
        $content = [
            'intro' => ['title' => 'Welkom!', 'text' => '<p>Fijne reis</p>'],
            'tripProfile' => ['showTableOfContents' => 'two'],
            'destinations' => [
                [
                    'type' => 'destination',
                    'title' => 'Lima',
                    'sections' => [
                        ['type' => 'free_text', 'title' => 'Route', 'text' => '<p>Rustig aan</p>'],
                    ],
                ],
            ],
        ];

        $withoutVersion = TravelPlanContent::fromArray($content);
        $withVersion = TravelPlanContent::fromArray(['_version' => TravelPlanContent::VERSION] + $content);
        $normalizer = new StorageNormalizer();

        self::assertSame($normalizer->toStorageArray($withoutVersion), $normalizer->toStorageArray($withVersion));
    }

    public function testColorVariantParsingIsForgiving(): void
    {
        self::assertSame(ColorVariant::Primary, ColorVariant::fromMixed(' PRIMARY '));
        self::assertSame(ColorVariant::Auto, ColorVariant::fromMixed('onzin'));
        self::assertSame(ColorVariant::Auto, ColorVariant::fromMixed(null));
        self::assertSame(ColorVariant::Auto, ColorVariant::fromMixed(['array']));
    }

    public function testRawArraysBlijvenBeschikbaarVoorNietGemigreerdeConsumenten(): void
    {
        $content = TravelPlanContent::fromArray([
            'destinations' => [[
                'type' => 'destination',
                'title' => 'X',
                'custom_veld' => 'blijft-bereikbaar',
                'sections' => [['type' => 'checklist', 'items' => ['a', 'b']]],
            ]],
        ]);

        self::assertSame('blijft-bereikbaar', $content->destinations[0]->raw['custom_veld']);
        self::assertSame(['a', 'b'], $content->destinations[0]->sections[0]->raw['items']);
    }
}

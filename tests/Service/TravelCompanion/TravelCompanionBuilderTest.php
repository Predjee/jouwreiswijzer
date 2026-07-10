<?php

declare(strict_types=1);

namespace App\Tests\Service\TravelCompanion;

use App\Entity\TravelPlan;
use App\Service\TravelCompanion\TravelPlanChecklistStateProvider;
use App\Service\TravelCompanion\TravelCompanionBuilder;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\ContactBundle\Entity\Contact;

final class TravelCompanionBuilderTest extends TestCase
{
    public function testBuildUsesTypedContentAndKeepsCompanionShape(): void
    {
        $repository = $this->createStub(TravelPlanChecklistStateProvider::class);
        $repository->method('checkedMapForPlan')->willReturn([]);

        $travelPlan = (new TravelPlan())
            ->setTitle('Peru')
            ->setContent([
                'tripProfile' => [
                    'startDate' => '2026-05-01',
                    'endDate' => '2026-05-02',
                    'period' => 'Fallback periode',
                    'duration' => 'Fallback duur',
                ],
                'destinations' => [
                    [
                        'type' => 'destination',
                        'title' => 'Lima',
                        'city' => 'Lima',
                        'country' => 'Peru',
                        'sections' => [
                            [
                                'type' => 'day',
                                'dayNumber' => '2',
                                'title' => 'Dag 2',
                                'dateLabel' => '2 mei',
                                'intro' => '<p>Intro</p>',
                                'blocks' => [
                                    [
                                        'type' => 'activity',
                                        'title' => 'Fietsen',
                                        'location' => 'Centrum',
                                        'startTime' => '09:00',
                                        'endTime' => '11:00',
                                    ],
                                ],
                            ],
                            [
                                'type' => 'checklist',
                                'title' => 'Paklijst',
                                'text' => '<ul><li>Paspoort</li></ul>',
                            ],
                        ],
                    ],
                ],
            ]);

        $trip = (new TravelCompanionBuilder($repository, \dirname(__DIR__, 3)))->build($travelPlan, new Contact());

        self::assertSame('Peru', $trip->title);
        self::assertSame('1 mei t/m 2 mei', $trip->periodLabel);
        self::assertCount(2, $trip->days);
        self::assertSame('Dag 2', $trip->days[1]->title);
        self::assertSame('Fietsen', $trip->days[1]->blocks[0]->title);
        self::assertSame('Paklijst', $trip->blocks[1]->title);
        self::assertTrue($trip->hasChecklist);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Service\TravelCompanion;

use App\Entity\TravelPlan;
use App\Service\TravelCompanion\ReminderPlanBuilder;
use PHPUnit\Framework\TestCase;

final class ReminderPlanBuilderTest extends TestCase
{
    public function testBuildForRangeUsesTypedContentAndKeepsReminderShape(): void
    {
        $travelPlan = (new TravelPlan())
            ->setTitle('Peru')
            ->setContent([
                'tripProfile' => ['startDate' => '2026-05-01'],
                'destinations' => [
                    [
                        'type' => 'destination',
                        'title' => 'Lima',
                        'sections' => [
                            [
                                'type' => 'day',
                                'dayNumber' => '2',
                                'title' => 'Dag 2',
                                'destinationTimezone' => 'America/Lima',
                                'blocks' => [
                                    [
                                        'type' => 'activity',
                                        'title' => 'Fietsen',
                                        'text' => '<p>Door de stad</p>',
                                        'location' => 'Centrum',
                                        'startTime' => '09:30',
                                        'icon' => '',
                                        'bookingUrl' => 'https://example.test',
                                    ],
                                    ['type' => 'meal', 'title' => 'Te vroeg', 'time' => '05:30'],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $reminders = (new ReminderPlanBuilder())->buildForRange(
            $travelPlan,
            new \DateTimeImmutable('2026-05-02 00:00:00 UTC'),
            new \DateTimeImmutable('2026-05-03 00:00:00 UTC'),
        );

        self::assertCount(1, $reminders);
        self::assertSame('2026-05-02 14:30', $reminders[0]['triggerAt']->format('Y-m-d H:i'));
        self::assertSame([
            'tripId' => null,
            'dayNumber' => 2,
            'dayTitle' => 'Dag 2',
            'destinationTitle' => 'Lima',
            'blockType' => 'activity',
            'title' => 'Fietsen',
            'text' => '<p>Door de stad</p>',
            'location' => 'Centrum',
            'timeLabel' => '',
            'icon' => 'compass',
            'bookingUrl' => 'https://example.test',
        ], \array_diff_key($reminders[0], ['triggerAt' => true]));
    }
}

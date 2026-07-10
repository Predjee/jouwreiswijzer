<?php

declare(strict_types=1);

namespace App\Tests\PushMessage;

use App\Entity\TravelPlan;
use App\PushMessage\TravelPlanPersonalizationContextBuilder;
use PHPUnit\Framework\TestCase;

final class TravelPlanPersonalizationContextBuilderTest extends TestCase
{
    public function testBuildUsesTypedContentForCurrentAndNextActivityTokens(): void
    {
        $travelPlan = (new TravelPlan())
            ->setTitle('Peru')
            ->setContent($this->content());
        $customer = new class {
            public function getFirstName(): string
            {
                return 'Mila';
            }

            public function getLastName(): string
            {
                return 'Jansen';
            }
        };

        $context = (new TravelPlanPersonalizationContextBuilder())->build($travelPlan, $customer, ['number' => 1]);

        self::assertSame('Mila', $context['values']['customer.firstName']);
        self::assertSame('Peru', $context['values']['trip.title']);
        self::assertSame('1', $context['values']['currentDay.number']);
        self::assertSame('Dag 1', $context['values']['currentDay.title']);
        self::assertSame('Dag 2', $context['values']['nextDay.title']);
        self::assertSame('Fietsen', $context['values']['nextActivity.title']);
        self::assertSame('09:00', $context['values']['nextActivity.time']);
        self::assertSame('Centrum', $context['values']['nextActivity.location']);
    }

    /**
     * @return array<string, mixed>
     */
    private function content(): array
    {
        return [
            'tripProfile' => ['startDate' => '2026-05-01', 'endDate' => '2026-05-02'],
            'destinations' => [
                [
                    'type' => 'destination',
                    'title' => 'Lima',
                    'sections' => [
                        ['type' => 'day', 'dayNumber' => '1', 'title' => 'Dag 1'],
                        [
                            'type' => 'day',
                            'dayNumber' => '2',
                            'title' => 'Dag 2',
                            'blocks' => [
                                [
                                    'type' => 'activity',
                                    'title' => 'Fietsen',
                                    'startTime' => '09:00',
                                    'location' => 'Centrum',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}

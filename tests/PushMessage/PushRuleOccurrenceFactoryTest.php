<?php

declare(strict_types=1);

namespace App\Tests\PushMessage;

use App\Entity\PushRule;
use App\Entity\TravelPlan;
use App\Entity\TravelRequest;
use App\PushMessage\PushMessageTemplateRenderer;
use App\PushMessage\PushRuleOccurrenceFactory;
use App\PushMessage\TravelPlanPersonalizationContextBuilder;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\ContactBundle\Entity\Contact;

final class PushRuleOccurrenceFactoryTest extends TestCase
{
    public function testCreateOccurrencesUsesTypedActivityBlocksAndKeepsSourceKeyShape(): void
    {
        $rule = (new PushRule())
            ->setName('Activiteit')
            ->setTriggerType(PushRule::TRIGGER_TYPE_ACTIVITY_START_OFFSET)
            ->setOffsetValue(-30)
            ->setOffsetUnit(PushRule::OFFSET_UNIT_MINUTES)
            ->setTimezoneStrategy(PushRule::TIMEZONE_STRATEGY_DAY)
            ->setTitleTemplate('Dag {{ currentDay.number }}')
            ->setBodyTemplate('{{ currentDay.title }}')
            ->setChannel(PushRule::CHANNEL_GENERAL)
            ->setActionType(PushRule::ACTION_TYPE_NONE);
        $this->setId($rule, 7);

        $contact = (new Contact())->setFirstName('Mila')->setLastName('Jansen');
        $travelPlan = (new TravelPlan())
            ->setTitle('Peru')
            ->setTravelRequest((new TravelRequest())->setContact($contact))
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
                                    ['type' => 'note', 'title' => 'Niet tellen'],
                                    ['type' => 'activity', 'title' => 'Fietsen', 'startTime' => '09:30'],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
        $this->setId($travelPlan, 42);

        $occurrences = (new PushRuleOccurrenceFactory(
            new PushMessageTemplateRenderer(new TravelPlanPersonalizationContextBuilder()),
        ))->createOccurrences($rule, $travelPlan);

        self::assertCount(1, $occurrences);
        self::assertSame('rule_7:trip_42:day_2:block_2', $occurrences[0]->sourceKey);
        self::assertSame('2026-05-02 09:00', $occurrences[0]->scheduledFor->format('Y-m-d H:i'));
        self::assertSame('Dag 2', $occurrences[0]->title);
        self::assertSame('Dag 2', $occurrences[0]->body);
    }

    private function setId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setValue($entity, $id);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\TravelPlan\Feedback;

use App\Entity\TravelPlanFeedback;
use App\TravelPlan\Feedback\FeedbackContentAnnotator;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\ContactBundle\Entity\Contact;

final class FeedbackContentAnnotatorTest extends TestCase
{
    public function testFeedbackZonderPadWordtPlanFeedback(): void
    {
        $data = $this->contentData();

        (new FeedbackContentAnnotator())->attach($data, $this->feedback(null));

        self::assertSame('Reactie van de klant', self::node($data, 'planFeedback')['message']);
    }

    public function testFeedbackOpBlokpadLandtOpHetJuisteBlok(): void
    {
        $data = $this->contentData();

        (new FeedbackContentAnnotator())->attach(
            $data,
            $this->feedback('destinations[0].sections[1].blocks[0]'),
        );

        $feedback = self::node($data, 'destinations', 0, 'sections', 1, 'blocks', 0, '_feedback');
        self::assertSame('Reactie van de klant', $feedback['message']);
        self::assertSame('Mila Jansen', $feedback['contactName']);
        self::assertArrayNotHasKey('planFeedback', $data);
    }

    public function testOngeldigPadLaatDataOngemoeid(): void
    {
        $data = $this->contentData();
        $expected = $data;

        (new FeedbackContentAnnotator())->attach($data, $this->feedback('destinations[9].sections[9]'));

        self::assertSame($expected, $data);
    }

    public function testTargetLabels(): void
    {
        $annotator = new FeedbackContentAnnotator();
        $data = $this->contentData();

        self::assertSame('Hele reisplan', $annotator->targetLabel($data, $this->feedback(null)));
        self::assertSame(
            'Bestemming 1: Even landen in Lima',
            $annotator->targetLabel($data, $this->feedback('destinations[0]')),
        );
        self::assertSame(
            'Bestemming 1, sectie 1: Route in het kort',
            $annotator->targetLabel($data, $this->feedback('destinations[0].sections[0]')),
        );
        self::assertSame(
            'Bestemming 1, dag 1: Fietsen langs de kust',
            $annotator->targetLabel($data, $this->feedback('destinations[0].sections[1].blocks[0]')),
        );
    }

    /**
     * Loopt een pad door de gemuteerde content-array en vernauwt elke
     * stap naar array, zodat de test zelf ook op PHPStan level max blijft.
     *
     * @param array<string, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private static function node(array $data, string|int ...$path): array
    {
        /** @var array<array-key, mixed> $current */
        $current = $data;

        foreach ($path as $segment) {
            self::assertArrayHasKey($segment, $current);
            $next = $current[$segment];
            self::assertIsArray($next);
            $current = $next;
        }

        return $current;
    }

    /**
     * @return array<string, mixed>
     */
    private function contentData(): array
    {
        return [
            'destinations' => [
                [
                    'type' => 'destination',
                    'title' => 'Even landen in Lima',
                    'sections' => [
                        [
                            'type' => 'route_overview',
                            'title' => 'Route in het kort',
                        ],
                        [
                            'type' => 'day',
                            'dayNumber' => '1',
                            'title' => 'Aankomst',
                            'blocks' => [
                                [
                                    'type' => 'activity',
                                    'title' => 'Fietsen langs de kust',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function feedback(?string $blockPath): TravelPlanFeedback
    {
        $contact = new Contact();
        $contact->setFirstName('Mila');
        $contact->setLastName('Jansen');

        return (new TravelPlanFeedback())
            ->setContact($contact)
            ->setBlockPath($blockPath)
            ->setBlockType('activity')
            ->setMessage('Reactie van de klant')
            ->setStatus(TravelPlanFeedback::STATUS_OPEN);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\TravelPlan\Feedback;

use App\Dto\SubmitTravelPlanFeedbackRequest;
use App\Entity\TravelPlan;
use App\Entity\TravelPlanFeedback;
use App\Event\FeedbackRoundSubmittedEvent;
use App\TravelPlan\Feedback\FeedbackAcceptanceResult;
use App\TravelPlan\Feedback\FeedbackGateway;
use App\TravelPlan\Feedback\FeedbackPathResolver;
use App\TravelPlan\Feedback\FeedbackRoundService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\ContactBundle\Entity\Contact;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class FeedbackRoundServiceTest extends TestCase
{
    public function testBlankFeedbackMessageReturnsCurrentValidationError(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $result = $this->service(entityManager: $entityManager)->submitFeedback(
            $this->travelPlan(),
            new Contact(),
            new SubmitTravelPlanFeedbackRequest('', null, 'token'),
        );

        self::assertFalse($result->success);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $result->status);
        self::assertSame('Vul een bericht in voordat je de feedback verstuurt.', $result->message);
    }

    public function testInvalidFeedbackBlockPathKeepsCurrentBadRequestMessage(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Ongeldig reisplanonderdeel.');

        $this->service()->submitFeedback(
            $this->travelPlan(),
            new Contact(),
            new SubmitTravelPlanFeedbackRequest(
                'Kan dit anders?',
                'destinations[0].sections[1].blocks[9]',
                'token',
            ),
        );
    }

    public function testActiveFeedbackForSameTargetReturnsCurrentConflict(): void
    {
        $travelPlan = $this->travelPlan();
        $contact = new Contact();
        $activeFeedback = (new TravelPlanFeedback())
            ->setTravelPlan($travelPlan)
            ->setContact($contact)
            ->setBlockPath('destinations[0].sections[1].blocks[0]')
            ->setMessage('Eerder bericht');

        $gateway = $this->createMock(FeedbackGateway::class);
        $gateway->expects(self::once())
            ->method('findActiveForTarget')
            ->with($travelPlan, $contact, 'destinations[0].sections[1].blocks[0]')
            ->willReturn($activeFeedback);

        $result = $this->service(gateway: $gateway)->submitFeedback(
            $travelPlan,
            $contact,
            new SubmitTravelPlanFeedbackRequest(
                'Kan dit anders?',
                'destinations[0].sections[1].blocks[0]',
                'token',
            ),
        );

        self::assertFalse($result->success);
        self::assertSame(Response::HTTP_CONFLICT, $result->status);
        self::assertSame('Voor dit onderdeel is al feedback ontvangen en nog in behandeling.', $result->message);
        self::assertSame($activeFeedback, $result->feedback);
        self::assertSame('block', $result->feedbackContext);
        self::assertSame('Feedback op dit dagonderdeel', $result->feedbackLabel);
    }

    public function testSuccessfulFeedbackStoresSnapshotInvalidationAndPathMetadata(): void
    {
        $travelPlan = $this->travelPlan();
        $travelPlan->setPdfReleasedAt(new \DateTimeImmutable('2026-01-01'));
        $contact = new Contact();
        $persisted = null;

        $gateway = $this->createMock(FeedbackGateway::class);
        $gateway->method('findActiveForTarget')->willReturn(null);
        $gateway->expects(self::once())
            ->method('findActiveForTravelPlan')
            ->with($travelPlan)
            ->willReturn([new TravelPlanFeedback(), new TravelPlanFeedback()]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())
            ->method('persist')
            ->willReturnCallback(static function (TravelPlanFeedback $feedback) use (&$persisted): void {
                $persisted = $feedback;
            });
        $entityManager->expects(self::once())->method('flush');

        $result = $this->service($gateway, $entityManager)->submitFeedback(
            $travelPlan,
            $contact,
            new SubmitTravelPlanFeedbackRequest(
                'Kan dit anders?',
                'destinations[0].sections[1].blocks[0]',
                'token',
            ),
        );

        self::assertTrue($result->success);
        self::assertSame('Bedankt, je feedback is ontvangen.', $result->message);
        self::assertSame(2, $result->activeFeedbackCount);
        self::assertSame($persisted, $result->feedback);
        self::assertInstanceOf(TravelPlanFeedback::class, $persisted);
        self::assertSame($travelPlan, $persisted->getTravelPlan());
        self::assertSame($contact, $persisted->getContact());
        self::assertSame('destinations[0].sections[1].blocks[0]', $persisted->getBlockPath());
        self::assertSame('activity', $persisted->getBlockType());
        self::assertSame('Kan dit anders?', $persisted->getMessage());
        self::assertNull($travelPlan->getPdfReleasedAt());
    }

    public function testTripStartedBlocksFeedbackRoundWithCurrentError(): void
    {
        $result = $this->service()->submitRound($this->travelPlan(tripProfile: ['startDate' => '2020-01-01']));

        self::assertFalse($result->success);
        self::assertSame(Response::HTTP_CONFLICT, $result->status);
        self::assertSame('trip_started', $result->errorCode);
        self::assertSame(
            'Deze reis is al begonnen, feedback op het reisplan is niet meer mogelijk. Neem voor wijzigingen tijdens je reis rechtstreeks contact op.',
            $result->message,
        );
    }

    public function testFeedbackRoundDispatchesCurrentActiveFeedbackItems(): void
    {
        $travelPlan = $this->travelPlan();
        $feedbackItems = [new TravelPlanFeedback(), new TravelPlanFeedback()];

        $gateway = $this->createMock(FeedbackGateway::class);
        $gateway->expects(self::once())
            ->method('findActiveForTravelPlan')
            ->with($travelPlan)
            ->willReturn($feedbackItems);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (FeedbackRoundSubmittedEvent $event) use ($travelPlan, $feedbackItems): bool {
                return $event->getTravelPlan() === $travelPlan && $event->getFeedbackItems() === $feedbackItems;
            }));

        $result = $this->service(gateway: $gateway, eventDispatcher: $eventDispatcher)->submitRound($travelPlan);

        self::assertTrue($result->success);
        self::assertSame(2, $result->feedbackCount);
    }

    public function testExhaustedRoundsBlockNewFeedback(): void
    {
        $travelPlan = $this->travelPlan()->setMaxFeedbackRounds(1)->incrementFeedbackRoundsUsed();

        $result = $this->service()->submitFeedback(
            $travelPlan,
            new Contact(),
            new SubmitTravelPlanFeedbackRequest('Nog een wens', null, 'token'),
        );

        self::assertFalse($result->success);
        self::assertSame(Response::HTTP_CONFLICT, $result->status);
        self::assertSame('feedback_rounds_exhausted', $result->errorCode);
    }

    public function testExhaustedRoundsBlockSubmittingARound(): void
    {
        $travelPlan = $this->travelPlan()->setMaxFeedbackRounds(1)->incrementFeedbackRoundsUsed();

        $result = $this->service()->submitRound($travelPlan);

        self::assertFalse($result->success);
        self::assertSame('feedback_rounds_exhausted', $result->errorCode);
    }

    public function testSubmittedRoundConsumesQuotaAndReportsRemaining(): void
    {
        $travelPlan = $this->travelPlan();

        $gateway = $this->createStub(FeedbackGateway::class);
        $gateway->method('findActiveForTravelPlan')->willReturn([new TravelPlanFeedback()]);

        $result = $this->service(gateway: $gateway)->submitRound($travelPlan);

        self::assertTrue($result->success);
        self::assertSame(1, $travelPlan->getFeedbackRoundsUsed());
        self::assertSame(
            'Je feedbackronde met 1 feedbackpunt is verstuurd. Je hebt nog 1 van de 2 feedbackrondes over.',
            $result->message,
        );
    }

    public function testLastRoundReportsExhaustion(): void
    {
        $travelPlan = $this->travelPlan()->incrementFeedbackRoundsUsed();

        $gateway = $this->createStub(FeedbackGateway::class);
        $gateway->method('findActiveForTravelPlan')->willReturn([new TravelPlanFeedback()]);

        $result = $this->service(gateway: $gateway)->submitRound($travelPlan);

        self::assertTrue($result->success);
        self::assertFalse($travelPlan->hasFeedbackRoundsRemaining());
        self::assertStringEndsWith('Dit was je laatste feedbackronde.', $result->message);
    }

    public function testEmptyRoundDoesNotConsumeQuota(): void
    {
        $travelPlan = $this->travelPlan();

        $gateway = $this->createStub(FeedbackGateway::class);
        $gateway->method('findActiveForTravelPlan')->willReturn([]);

        $result = $this->service(gateway: $gateway)->submitRound($travelPlan);

        self::assertTrue($result->success);
        self::assertSame(0, $travelPlan->getFeedbackRoundsUsed());
    }

    public function testAcceptFeedbackRequiresResolvedAndUnacceptedFeedback(): void
    {
        $result = $this->service()->acceptFeedback(
            $travelPlan = $this->travelPlan(),
            $contact = new Contact(),
            (new TravelPlanFeedback())
                ->setTravelPlan($travelPlan)
                ->setContact($contact)
                ->setStatus(TravelPlanFeedback::STATUS_OPEN),
        );

        self::assertInstanceOf(FeedbackAcceptanceResult::class, $result);
        self::assertFalse($result->success);
        self::assertSame(Response::HTTP_CONFLICT, $result->status);
        self::assertSame('Deze feedback kan niet meer worden bevestigd.', $result->message);
    }

    public function testAcceptFeedbackStoresAcceptedAtForResolvedFeedback(): void
    {
        $travelPlan = $this->travelPlan();
        $contact = new Contact();
        $feedback = (new TravelPlanFeedback())
            ->setTravelPlan($travelPlan)
            ->setContact($contact)
            ->setStatus(TravelPlanFeedback::STATUS_RESOLVED);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $result = $this->service(entityManager: $entityManager)->acceptFeedback($travelPlan, $contact, $feedback);

        self::assertInstanceOf(FeedbackAcceptanceResult::class, $result);
        self::assertTrue($result->success);
        self::assertSame('Bedankt, je akkoord is opgeslagen.', $result->message);
        self::assertSame($feedback, $result->feedback);
        self::assertInstanceOf(\DateTimeImmutable::class, $feedback->getAcceptedAt());
    }

    private function service(
        ?FeedbackGateway $gateway = null,
        ?EntityManagerInterface $entityManager = null,
        ?EventDispatcherInterface $eventDispatcher = null,
    ): FeedbackRoundService {
        $gateway ??= $this->createStub(FeedbackGateway::class);
        $entityManager ??= $this->createStub(EntityManagerInterface::class);
        $eventDispatcher ??= $this->createStub(EventDispatcherInterface::class);

        return new FeedbackRoundService(
            $gateway,
            $entityManager,
            $eventDispatcher,
            new FeedbackPathResolver(),
            Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator(),
        );
    }

    /**
     * @param array<string, mixed> $overrides
     * @param array<string, mixed> $tripProfile
     */
    private function travelPlan(array $overrides = [], array $tripProfile = []): TravelPlan
    {
        $content = [
            'tripProfile' => \array_replace(['startDate' => '2099-01-01'], $tripProfile),
            'destinations' => [
                [
                    'type' => 'destination',
                    'title' => 'Lima',
                    'date' => '2099-01-01',
                    'sections' => [
                        [
                            'type' => 'summary',
                            'title' => 'Route in het kort',
                        ],
                        [
                            'type' => 'day',
                            'title' => 'Dag 1',
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
        $content['destinations'][0] = \array_replace_recursive($content['destinations'][0], $overrides);

        return (new TravelPlan())
            ->setTitle('Peru op maat')
            ->setContent($content);
    }
}

<?php

declare(strict_types=1);

namespace App\TravelPlan\Feedback;

use App\Companion\CompanionContentHelper;
use App\Dto\SubmitFeedbackRoundRequest;
use App\Dto\SubmitTravelPlanFeedbackRequest;
use App\Entity\TravelPlan;
use App\Entity\TravelPlanFeedback;
use App\Event\FeedbackRoundSubmittedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\ContactBundle\Entity\Contact;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class FeedbackRoundService
{
    public function __construct(
        private FeedbackGateway $feedbackRepository,
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher,
        private FeedbackPathResolver $feedbackPathResolver,
        private ValidatorInterface $validator,
    ) {
    }

    public function isValidRoundRequest(SubmitFeedbackRoundRequest $request): bool
    {
        return 0 === \count($this->validator->validate($request));
    }

    public function submitFeedback(
        TravelPlan $travelPlan,
        Contact $contact,
        SubmitTravelPlanFeedbackRequest $request,
    ): FeedbackSubmissionResult {
        if (CompanionContentHelper::hasTripStarted($travelPlan->getContent())) {
            return new FeedbackSubmissionResult(
                false,
                'Deze reis is al begonnen, feedback op het reisplan is niet meer mogelijk. Neem voor wijzigingen tijdens je reis rechtstreeks contact op.',
                Response::HTTP_CONFLICT,
                errorCode: 'trip_started',
            );
        }

        if (!$travelPlan->hasFeedbackRoundsRemaining()) {
            return new FeedbackSubmissionResult(
                false,
                'Alle feedbackrondes voor dit reisplan zijn gebruikt. Neem contact met ons op als er toch nog iets aangepast moet worden.',
                Response::HTTP_CONFLICT,
                errorCode: 'feedback_rounds_exhausted',
            );
        }

        $blockPath = $request->blockPath;
        $blockType = $this->feedbackPathResolver->resolveBlockType($travelPlan, $blockPath);
        $feedbackContext = $this->feedbackPathResolver->context($blockPath);
        $feedbackLabel = $this->feedbackPathResolver->label($feedbackContext);

        $activeFeedback = $this->feedbackRepository->findActiveForTarget($travelPlan, $contact, $blockPath);

        if ($activeFeedback instanceof TravelPlanFeedback) {
            return new FeedbackSubmissionResult(
                false,
                'Voor dit onderdeel is al feedback ontvangen en nog in behandeling.',
                Response::HTTP_CONFLICT,
                $activeFeedback,
                $blockPath,
                $feedbackContext,
                $feedbackLabel,
            );
        }

        foreach ($this->validator->validate($request) as $violation) {
            if ('message' !== $violation->getPropertyPath()) {
                continue;
            }

            return new FeedbackSubmissionResult(
                false,
                (string) $violation->getMessage(),
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $feedback = (new TravelPlanFeedback())
            ->setTravelPlan($travelPlan)
            ->setContact($contact)
            ->setBlockPath($blockPath)
            ->setBlockType($blockType)
            ->setMessage($request->message);

        $travelPlan->setPdfReleasedAt(null);
        $this->entityManager->persist($feedback);
        $this->entityManager->flush();

        return new FeedbackSubmissionResult(
            true,
            'Bedankt, je feedback is ontvangen.',
            Response::HTTP_OK,
            $feedback,
            $blockPath,
            $feedbackContext,
            $feedbackLabel,
            activeFeedbackCount: \count($this->feedbackRepository->findActiveForTravelPlan($travelPlan)),
        );
    }

    public function submitRound(TravelPlan $travelPlan): FeedbackRoundSubmissionResult
    {
        if (CompanionContentHelper::hasTripStarted($travelPlan->getContent())) {
            return new FeedbackRoundSubmissionResult(
                false,
                'Deze reis is al begonnen, feedback op het reisplan is niet meer mogelijk. Neem voor wijzigingen tijdens je reis rechtstreeks contact op.',
                Response::HTTP_CONFLICT,
                errorCode: 'trip_started',
            );
        }

        if (!$travelPlan->hasFeedbackRoundsRemaining()) {
            return new FeedbackRoundSubmissionResult(
                false,
                'Alle feedbackrondes voor dit reisplan zijn gebruikt. Neem contact met ons op als er toch nog iets aangepast moet worden.',
                Response::HTTP_CONFLICT,
                errorCode: 'feedback_rounds_exhausted',
            );
        }

        $feedbackItems = $this->feedbackRepository->findActiveForTravelPlan($travelPlan);
        $feedbackCount = \count($feedbackItems);

        if (0 === $feedbackCount) {
            // Een lege ronde verbruikt geen tegoed.
            return new FeedbackRoundSubmissionResult(
                true,
                'Er staan nog geen feedbackpunten klaar om te versturen.',
                Response::HTTP_OK,
            );
        }

        $travelPlan->incrementFeedbackRoundsUsed();
        $this->entityManager->flush();
        $this->eventDispatcher->dispatch(new FeedbackRoundSubmittedEvent($travelPlan, $feedbackItems));

        $remaining = $travelPlan->remainingFeedbackRounds();

        return new FeedbackRoundSubmissionResult(
            true,
            \sprintf(
                'Je feedbackronde met %d %s is verstuurd. %s',
                $feedbackCount,
                1 === $feedbackCount ? 'feedbackpunt' : 'feedbackpunten',
                0 === $remaining
                    ? 'Dit was je laatste feedbackronde.'
                    : \sprintf('Je hebt nog %d van de %d feedbackrondes over.', $remaining, $travelPlan->effectiveMaxFeedbackRounds()),
            ),
            Response::HTTP_OK,
            $feedbackCount,
        );
    }

    public function acceptFeedback(
        TravelPlan $travelPlan,
        Contact $contact,
        ?TravelPlanFeedback $feedback,
    ): ?FeedbackAcceptanceResult {
        if (!$feedback instanceof TravelPlanFeedback || !$this->ownsFeedback($travelPlan, $contact, $feedback)) {
            return null;
        }

        if (
            TravelPlanFeedback::STATUS_RESOLVED !== $feedback->getStatus()
            || null !== $feedback->getAcceptedAt()
        ) {
            return new FeedbackAcceptanceResult(
                false,
                'Deze feedback kan niet meer worden bevestigd.',
                Response::HTTP_CONFLICT,
            );
        }

        $feedback->setAcceptedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return new FeedbackAcceptanceResult(
            true,
            'Bedankt, je akkoord is opgeslagen.',
            Response::HTTP_OK,
            $feedback,
        );
    }

    public function ownsFeedback(
        TravelPlan $travelPlan,
        Contact $contact,
        ?TravelPlanFeedback $feedback,
    ): bool {
        return $feedback instanceof TravelPlanFeedback
            && $this->sameTravelPlan($feedback, $travelPlan)
            && $this->sameContact($feedback, $contact);
    }

    private function sameTravelPlan(TravelPlanFeedback $feedback, TravelPlan $travelPlan): bool
    {
        if ($feedback->getTravelPlan() === $travelPlan) {
            return true;
        }

        return $feedback->getTravelPlan()->getId() === $travelPlan->getId();
    }

    private function sameContact(TravelPlanFeedback $feedback, Contact $contact): bool
    {
        if ($feedback->getContact() === $contact) {
            return true;
        }

        return $feedback->getContact()->getId() === $contact->getId();
    }
}

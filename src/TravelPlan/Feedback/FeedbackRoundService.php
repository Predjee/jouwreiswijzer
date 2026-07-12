<?php

declare(strict_types=1);

namespace App\TravelPlan\Feedback;

use App\Dto\SubmitFeedbackRoundRequest;
use App\Event\FeedbackRoundSubmittedEvent;
use App\Repository\TravelPlanFeedbackRepository;
use App\Repository\TravelPlanRepository;
use Sulu\Bundle\ContactBundle\Entity\Contact;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class FeedbackRoundService
{
    public function __construct(
        private TravelPlanRepository $travelPlanRepository,
        private TravelPlanFeedbackRepository $feedbackRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function submit(SubmitFeedbackRoundRequest $request, Contact $contact): ?int
    {
        $travelPlan = $this->travelPlanRepository->findPublishedForContact($request->travelPlanId, $contact);

        if (null === $travelPlan) {
            return null;
        }

        $feedbackItems = $this->feedbackRepository->findActiveForTravelPlan($travelPlan);
        $feedbackCount = \count($feedbackItems);

        if ($feedbackCount > 0) {
            $this->eventDispatcher->dispatch(new FeedbackRoundSubmittedEvent($travelPlan, $feedbackItems));
        }

        return $feedbackCount;
    }
}

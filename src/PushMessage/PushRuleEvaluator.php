<?php

declare(strict_types=1);

namespace App\PushMessage;

use App\Entity\PushRule;
use App\Entity\ScheduledPushMessage;
use App\Entity\TravelPlan;
use App\Repository\PushRuleRepository;
use App\Repository\ScheduledPushMessageRepository;
use App\Repository\TravelPlanRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final readonly class PushRuleEvaluator
{
    public function __construct(
        private PushRuleRepository $pushRuleRepository,
        private TravelPlanRepository $travelPlanRepository,
        private ScheduledPushMessageRepository $scheduledPushMessageRepository,
        private PushRuleOccurrenceFactory $occurrenceFactory,
        private EntityManagerInterface $entityManager,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function evaluate(): int
    {
        $created = 0;

        foreach ($this->pushRuleRepository->findActive() as $rule) {
            $created += $this->evaluateRule($rule);
        }

        if ($created > 0) {
            $this->entityManager->flush();
        }

        return $created;
    }

    private function evaluateRule(PushRule $rule): int
    {
        $created = 0;

        foreach ($this->travelPlanRepository->findPublishedForPushRuleEvaluation() as $travelPlan) {
            try {
                $created += $this->evaluateRuleForTravelPlan($rule, $travelPlan);
            } catch (\Throwable $exception) {
                $this->logger?->warning('Push rule evaluation skipped an invalid rule occurrence.', [
                    'exception' => $exception,
                    'pushRuleId' => $rule->getId(),
                    'travelPlanId' => $travelPlan->getId(),
                ]);
            }
        }

        return $created;
    }

    private function evaluateRuleForTravelPlan(PushRule $rule, TravelPlan $travelPlan): int
    {
        $created = 0;

        foreach ($this->occurrenceFactory->createOccurrences($rule, $travelPlan) as $occurrence) {
            if ($this->scheduledPushMessageRepository->existsForSourceKey($occurrence->sourceKey)) {
                continue;
            }

            $message = (new ScheduledPushMessage())
                ->setPushRule($rule)
                ->setTravelPlan($travelPlan)
                ->setSourceKey($occurrence->sourceKey)
                ->setTitle($occurrence->title)
                ->setBody($occurrence->body)
                ->setChannel($occurrence->channel)
                ->setActionType($occurrence->actionType)
                ->setActionTarget($occurrence->actionTarget)
                ->setScheduledFor($occurrence->scheduledFor);

            $this->entityManager->persist($message);
            ++$created;
        }

        return $created;
    }
}

<?php

declare(strict_types=1);

namespace App\PushMessage;

use App\Entity\PushRule;
use App\Entity\ScheduledPushMessage;
use App\Entity\TravelPlan;
use App\Repository\TravelPlanRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class ManualPushMessageManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TravelPlanRepository $travelPlanRepository,
        private PushMessageTemplateRenderer $templateRenderer,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): ScheduledPushMessage
    {
        $travelPlan = $this->travelPlan($data['travelPlanId'] ?? null);
        $sendNow = $this->boolean($data['sendNow'] ?? false, 'sendNow');
        $scheduledFor = $sendNow ? new \DateTimeImmutable() : $this->scheduledFor($data['scheduledFor'] ?? null);
        $titleTemplate = $this->requiredString($data, 'titleTemplate', 'Title template is required.');
        $bodyTemplate = $this->requiredString($data, 'bodyTemplate', 'Body template is required.');

        $message = (new ScheduledPushMessage())
            ->setPushRule(null)
            ->setTravelPlan($travelPlan)
            ->setSourceKey($this->sourceKey($travelPlan))
            ->setChannel($this->requiredChoice($data, 'channel', PushRule::channels(), 'Invalid push channel.'))
            ->setTitle($this->templateRenderer->render($titleTemplate, $travelPlan))
            ->setBody($this->templateRenderer->render($bodyTemplate, $travelPlan))
            ->setScheduledFor($scheduledFor)
            ->setStatus(ScheduledPushMessage::STATUS_PENDING)
            ->setActionType(null)
            ->setActionTarget(null);

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        return $message;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(ScheduledPushMessage $message, array $data): ScheduledPushMessage
    {
        if (null !== $message->getPushRule()) {
            throw new BadRequestHttpException('Only manual push messages can be updated here.');
        }

        if (ScheduledPushMessage::STATUS_PENDING !== $message->getStatus()) {
            throw new BadRequestHttpException('Only pending push messages can be updated.');
        }

        $travelPlan = $this->travelPlan($data['travelPlanId'] ?? $message->getTravelPlan()->getId());
        $sendNow = $this->boolean($data['sendNow'] ?? false, 'sendNow');
        $scheduledFor = $sendNow ? new \DateTimeImmutable() : $this->scheduledFor($data['scheduledFor'] ?? null);
        $titleTemplate = $this->requiredString($data, 'titleTemplate', 'Title template is required.');
        $bodyTemplate = $this->requiredString($data, 'bodyTemplate', 'Body template is required.');

        $message
            ->setTravelPlan($travelPlan)
            ->setChannel($this->requiredChoice($data, 'channel', PushRule::channels(), 'Invalid push channel.'))
            ->setTitle($this->templateRenderer->render($titleTemplate, $travelPlan))
            ->setBody($this->templateRenderer->render($bodyTemplate, $travelPlan))
            ->setScheduledFor($scheduledFor)
            ->setStatus(ScheduledPushMessage::STATUS_PENDING)
            ->setActionType(null)
            ->setActionTarget(null);

        $this->entityManager->flush();

        return $message;
    }

    private function travelPlan(mixed $value): TravelPlan
    {
        if (\is_array($value) && isset($value['id'])) {
            $value = $value['id'];
        }

        if (\is_array($value) && isset($value[0])) {
            $value = $value[0];
        }

        if (!\is_int($value) && !(\is_string($value) && 1 === \preg_match('/^\d+$/D', $value))) {
            throw new BadRequestHttpException('Travel plan is required.');
        }

        $travelPlan = $this->travelPlanRepository->find((int) $value);

        if (!$travelPlan instanceof TravelPlan) {
            throw new NotFoundHttpException(\sprintf('TravelPlan "%d" was not found.', (int) $value));
        }

        return $travelPlan;
    }

    private function scheduledFor(mixed $value): \DateTimeImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return new \DateTimeImmutable($value->format(\DateTimeInterface::ATOM));
        }

        if (!\is_scalar($value) || '' === \trim((string) $value)) {
            throw new BadRequestHttpException('Scheduled date is required when sendNow is false.');
        }

        try {
            return new \DateTimeImmutable((string) $value, new \DateTimeZone('Europe/Amsterdam'));
        } catch (\Exception $exception) {
            throw new BadRequestHttpException('Scheduled date is invalid.', $exception);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function requiredString(array $data, string $key, string $message): string
    {
        $value = $data[$key] ?? null;

        if (!\is_scalar($value) && !$value instanceof \Stringable) {
            throw new BadRequestHttpException($message);
        }

        $value = \trim((string) $value);

        if ('' === $value) {
            throw new BadRequestHttpException($message);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $choices
     */
    private function requiredChoice(array $data, string $key, array $choices, string $message): string
    {
        $value = $this->requiredString($data, $key, $message);
        $value = PushRule::normalizeChannel($value);

        if (!\in_array($value, $choices, true)) {
            throw new BadRequestHttpException($message);
        }

        return $value;
    }

    private function boolean(mixed $value, string $key): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        if (\is_int($value) && \in_array($value, [0, 1], true)) {
            return 1 === $value;
        }

        if (\is_string($value) && \in_array(\strtolower($value), ['0', '1', 'false', 'true', 'off', 'on'], true)) {
            return \in_array(\strtolower($value), ['1', 'true', 'on'], true);
        }

        throw new BadRequestHttpException(\sprintf('%s must be a boolean.', $key));
    }

    private function sourceKey(TravelPlan $travelPlan): string
    {
        return \sprintf(
            'manual:%d:%s:%s',
            $travelPlan->getId() ?? 0,
            (new \DateTimeImmutable())->format('YmdHis'),
            \bin2hex(\random_bytes(8)),
        );
    }
}

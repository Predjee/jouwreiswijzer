<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PushRule;
use App\Repository\ScheduledPushMessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class PushRuleManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ScheduledPushMessageRepository $scheduledPushMessageRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): PushRule
    {
        $pushRule = new PushRule();
        $this->apply($pushRule, $data);

        $this->entityManager->persist($pushRule);
        $this->entityManager->flush();

        return $pushRule;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(PushRule $pushRule, array $data): PushRule
    {
        $this->apply($pushRule, $data);
        $this->entityManager->flush();

        return $pushRule;
    }

    public function delete(PushRule $pushRule): void
    {
        if ($this->scheduledPushMessageRepository->existsForRule($pushRule)) {
            throw new BadRequestHttpException('Push rule cannot be deleted because scheduled push messages reference it.');
        }

        $this->entityManager->remove($pushRule);
        $this->entityManager->flush();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function apply(PushRule $pushRule, array $data): void
    {
        $name = $this->requiredString($data, 'name', 'Name is required.');
        $triggerType = $this->requiredChoice($data, 'triggerType', PushRule::triggerTypes(), 'Invalid push rule trigger type.');
        $channel = $this->requiredChoice($data, 'channel', PushRule::channels(), 'Invalid push rule channel.');
        $titleTemplate = $this->requiredString($data, 'titleTemplate', 'Title template is required.');
        $bodyTemplate = $this->requiredString($data, 'bodyTemplate', 'Body template is required.');
        $timezoneStrategy = $this->optionalChoice(
            $data,
            'timezoneStrategy',
            PushRule::timezoneStrategies(),
            PushRule::TIMEZONE_STRATEGY_FALLBACK,
            'Invalid push rule timezone strategy.',
        );
        $actionType = $this->optionalChoice(
            $data,
            'actionType',
            PushRule::actionTypes(),
            PushRule::ACTION_TYPE_NONE,
            'Invalid push rule action type.',
        );

        $localTime = $this->optionalString($data, 'localTime');
        $offsetValue = $this->optionalInt($data, 'offsetValue');
        $offsetUnit = $this->optionalChoice($data, 'offsetUnit', PushRule::offsetUnits(), null, 'Invalid push rule offset unit.');

        if (PushRule::TRIGGER_TYPE_DAY_START === $triggerType) {
            if (null === $localTime || 1 !== \preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D', $localTime)) {
                throw new BadRequestHttpException('Local time must be HH:mm for day_start push rules.');
            }
        }

        if (\in_array($triggerType, [
            PushRule::TRIGGER_TYPE_TRIP_START_OFFSET,
            PushRule::TRIGGER_TYPE_TRIP_END_OFFSET,
            PushRule::TRIGGER_TYPE_ACTIVITY_START_OFFSET,
        ], true)) {
            if (null === $offsetValue || null === $offsetUnit) {
                throw new BadRequestHttpException('Offset value and offset unit are required for offset push rules.');
            }
        }

        $pushRule
            ->setName($name)
            ->setTriggerType($triggerType)
            ->setChannel($channel)
            ->setActive($this->active($data, $pushRule->isActive()))
            ->setOffsetValue($offsetValue)
            ->setOffsetUnit($offsetUnit)
            ->setLocalTime($localTime)
            ->setTimezoneStrategy($timezoneStrategy ?? PushRule::TIMEZONE_STRATEGY_FALLBACK)
            ->setTitleTemplate($titleTemplate)
            ->setBodyTemplate($bodyTemplate)
            ->setActionType($actionType)
            ->setActionTarget($this->optionalString($data, 'actionTarget'));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function requiredString(array $data, string $key, string $message): string
    {
        $value = $this->optionalString($data, $key);

        if (null === $value) {
            throw new BadRequestHttpException($message);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function optionalString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if (null === $value) {
            return null;
        }

        if (!\is_scalar($value) && !$value instanceof \Stringable) {
            throw new BadRequestHttpException(\sprintf('%s must be a string.', $key));
        }

        $value = \trim((string) $value);

        return '' === $value ? null : $value;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $choices
     */
    private function requiredChoice(array $data, string $key, array $choices, string $message): string
    {
        $value = $this->optionalChoice($data, $key, $choices, null, $message);

        if (null === $value) {
            throw new BadRequestHttpException($message);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $choices
     */
    private function optionalChoice(array $data, string $key, array $choices, ?string $default, string $message): ?string
    {
        $value = $this->optionalString($data, $key);

        if (null === $value) {
            return $default;
        }

        if (!\in_array($value, $choices, true)) {
            throw new BadRequestHttpException($message);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function optionalInt(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        if (null === $value || '' === $value) {
            return null;
        }

        if (\is_int($value)) {
            return $value;
        }

        if (\is_string($value) && 1 === \preg_match('/^-?\d+$/D', $value)) {
            return (int) $value;
        }

        throw new BadRequestHttpException(\sprintf('%s must be an integer.', $key));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function active(array $data, bool $default): bool
    {
        $value = $data['active'] ?? $default;

        if (\is_bool($value)) {
            return $value;
        }

        if (\is_int($value) && \in_array($value, [0, 1], true)) {
            return 1 === $value;
        }

        if (\is_string($value) && \in_array(\strtolower($value), ['0', '1', 'false', 'true', 'off', 'on'], true)) {
            return \in_array(\strtolower($value), ['1', 'true', 'on'], true);
        }

        throw new BadRequestHttpException('Active must be a boolean.');
    }
}

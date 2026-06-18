<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PushRuleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PushRuleRepository::class)]
#[ORM\HasLifecycleCallbacks]
class PushRule
{
    public const TRIGGER_TYPE_ACTIVITY_START_OFFSET = 'activity_start_offset';
    public const TRIGGER_TYPE_DAY_START = 'day_start';
    public const TRIGGER_TYPE_TRIP_END_OFFSET = 'trip_end_offset';
    public const TRIGGER_TYPE_TRIP_START_OFFSET = 'trip_start_offset';

    public const OFFSET_UNIT_MINUTES = 'minutes';
    public const OFFSET_UNIT_HOURS = 'hours';
    public const OFFSET_UNIT_DAYS = 'days';

    public const TIMEZONE_STRATEGY_DAY = 'day';
    public const TIMEZONE_STRATEGY_TRIP = 'trip';
    public const TIMEZONE_STRATEGY_FALLBACK = 'fallback';

    public const CHANNEL_TRIP_REMINDERS = 'trip_reminders';
    public const CHANNEL_TRIP_PLAN_READY = 'trip_plan_ready';
    public const CHANNEL_ALBUM_READY = 'album_ready';
    public const CHANNEL_GENERAL = 'general';

    public const ACTION_TYPE_NONE = 'none';
    public const ACTION_TYPE_SCREEN = 'screen';
    public const ACTION_TYPE_EXTERNAL_URL = 'external_url';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 190)]
    private string $name;

    #[ORM\Column(length: 40)]
    private string $triggerType;

    #[ORM\Column(nullable: true)]
    private ?int $offsetValue = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $offsetUnit = null;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $localTime = null;

    #[ORM\Column(length: 20)]
    private string $timezoneStrategy = self::TIMEZONE_STRATEGY_FALLBACK;

    #[ORM\Column(length: 190)]
    private string $titleTemplate;

    #[ORM\Column(type: 'text')]
    private string $bodyTemplate;

    #[ORM\Column(length: 30)]
    private string $channel = self::CHANNEL_GENERAL;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $actionType = self::ACTION_TYPE_NONE;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $actionTarget = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTime $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getTriggerType(): string
    {
        return $this->triggerType;
    }

    public function setTriggerType(string $triggerType): self
    {
        if (!\in_array($triggerType, self::triggerTypes(), true)) {
            throw new \InvalidArgumentException(\sprintf('Unsupported push rule trigger type "%s".', $triggerType));
        }

        $this->triggerType = $triggerType;

        return $this;
    }

    public function getOffsetValue(): ?int
    {
        return $this->offsetValue;
    }

    public function setOffsetValue(?int $offsetValue): self
    {
        $this->offsetValue = $offsetValue;

        return $this;
    }

    public function getOffsetUnit(): ?string
    {
        return $this->offsetUnit;
    }

    public function setOffsetUnit(?string $offsetUnit): self
    {
        if (null !== $offsetUnit && !\in_array($offsetUnit, self::offsetUnits(), true)) {
            throw new \InvalidArgumentException(\sprintf('Unsupported push rule offset unit "%s".', $offsetUnit));
        }

        $this->offsetUnit = $offsetUnit;

        return $this;
    }

    public function getLocalTime(): ?string
    {
        return $this->localTime;
    }

    public function setLocalTime(?string $localTime): self
    {
        if (null !== $localTime && 1 !== \preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D', $localTime)) {
            throw new \InvalidArgumentException(\sprintf('Invalid push rule local time "%s".', $localTime));
        }

        $this->localTime = $localTime;

        return $this;
    }

    public function getTimezoneStrategy(): string
    {
        return $this->timezoneStrategy;
    }

    public function setTimezoneStrategy(string $timezoneStrategy): self
    {
        if (!\in_array($timezoneStrategy, self::timezoneStrategies(), true)) {
            throw new \InvalidArgumentException(\sprintf('Unsupported push rule timezone strategy "%s".', $timezoneStrategy));
        }

        $this->timezoneStrategy = $timezoneStrategy;

        return $this;
    }

    public function getOffsetDays(): int
    {
        return self::OFFSET_UNIT_DAYS === $this->offsetUnit ? (int) $this->offsetValue : 0;
    }

    public function setOffsetDays(int $offsetDays): self
    {
        $this->offsetValue = $offsetDays;
        $this->offsetUnit = self::OFFSET_UNIT_DAYS;

        return $this;
    }

    public function getTitleTemplate(): string
    {
        return $this->titleTemplate;
    }

    public function setTitleTemplate(string $titleTemplate): self
    {
        $this->titleTemplate = $titleTemplate;

        return $this;
    }

    public function getBodyTemplate(): string
    {
        return $this->bodyTemplate;
    }

    public function setBodyTemplate(string $bodyTemplate): self
    {
        $this->bodyTemplate = $bodyTemplate;

        return $this;
    }

    public function getMessageTitle(): string
    {
        return $this->titleTemplate;
    }

    public function setMessageTitle(string $messageTitle): self
    {
        $this->titleTemplate = $messageTitle;

        return $this;
    }

    public function getMessageBody(): string
    {
        return $this->bodyTemplate;
    }

    public function setMessageBody(string $messageBody): self
    {
        $this->bodyTemplate = $messageBody;

        return $this;
    }

    public function getChannel(): string
    {
        return self::normalizeChannel($this->channel);
    }

    public function setChannel(string $channel): self
    {
        $normalizedChannel = self::normalizeChannel($channel);

        if (!\in_array($normalizedChannel, self::channels(), true)) {
            throw new \InvalidArgumentException(\sprintf('Unsupported push rule channel "%s".', $channel));
        }

        $this->channel = $normalizedChannel;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function getActionType(): ?string
    {
        return $this->actionType;
    }

    public function setActionType(?string $actionType): self
    {
        if (null !== $actionType && !\in_array($actionType, self::actionTypes(), true)) {
            throw new \InvalidArgumentException(\sprintf('Unsupported push rule action type "%s".', $actionType));
        }

        $this->actionType = $actionType;

        return $this;
    }

    public function getActionTarget(): ?string
    {
        return $this->actionTarget;
    }

    public function setActionTarget(?string $actionTarget): self
    {
        $this->actionTarget = $actionTarget;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTime();
    }

    /**
     * @return list<string>
     */
    public static function triggerTypes(): array
    {
        return [
            self::TRIGGER_TYPE_TRIP_START_OFFSET,
            self::TRIGGER_TYPE_TRIP_END_OFFSET,
            self::TRIGGER_TYPE_DAY_START,
            self::TRIGGER_TYPE_ACTIVITY_START_OFFSET,
        ];
    }

    /**
     * @return list<string>
     */
    public static function offsetUnits(): array
    {
        return [
            self::OFFSET_UNIT_MINUTES,
            self::OFFSET_UNIT_HOURS,
            self::OFFSET_UNIT_DAYS,
        ];
    }

    /**
     * @return list<string>
     */
    public static function timezoneStrategies(): array
    {
        return [
            self::TIMEZONE_STRATEGY_DAY,
            self::TIMEZONE_STRATEGY_TRIP,
            self::TIMEZONE_STRATEGY_FALLBACK,
        ];
    }

    /**
     * @return list<string>
     */
    public static function channels(): array
    {
        return [
            self::CHANNEL_TRIP_REMINDERS,
            self::CHANNEL_TRIP_PLAN_READY,
            self::CHANNEL_GENERAL,
        ];
    }

    public static function normalizeChannel(string $channel): string
    {
        return self::CHANNEL_ALBUM_READY === $channel ? self::CHANNEL_TRIP_PLAN_READY : $channel;
    }

    /**
     * @return list<string>
     */
    public static function actionTypes(): array
    {
        return [
            self::ACTION_TYPE_NONE,
            self::ACTION_TYPE_SCREEN,
            self::ACTION_TYPE_EXTERNAL_URL,
        ];
    }
}

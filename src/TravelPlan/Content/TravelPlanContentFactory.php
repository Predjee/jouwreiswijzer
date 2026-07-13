<?php

declare(strict_types=1);

namespace App\TravelPlan\Content;

/**
 * Facade over de contentlaag van het reisplan.
 *
 * De daadwerkelijke logica is gesplitst naar App\TravelPlan\Content:
 * - ContentBlueprints: defaults/blauwdrukken per bloktype
 * - FormContentMapper: formulierdata <-> opslag-array (incl. datumlogica)
 * - StorageNormalizer: TravelPlanContent -> canonieke opslag-array
 */
final readonly class TravelPlanContentFactory
{
    public const TYPE_TRAVEL_PLAN_INTRO = ContentBlueprints::TYPE_TRAVEL_PLAN_INTRO;
    public const TYPE_TRIP_PROFILE = ContentBlueprints::TYPE_TRIP_PROFILE;
    public const TYPE_DESTINATION = ContentBlueprints::TYPE_DESTINATION;
    public const TYPE_ROUTE_OVERVIEW = ContentBlueprints::TYPE_ROUTE_OVERVIEW;
    public const TYPE_ROUTE_STOP = ContentBlueprints::TYPE_ROUTE_STOP;
    public const TYPE_DAY = ContentBlueprints::TYPE_DAY;
    public const TYPE_PRACTICAL_INFO = ContentBlueprints::TYPE_PRACTICAL_INFO;
    public const TYPE_CHECKLIST = ContentBlueprints::TYPE_CHECKLIST;
    public const TYPE_BUDGET_NOTE = ContentBlueprints::TYPE_BUDGET_NOTE;
    public const TYPE_PERSONAL_NOTE = ContentBlueprints::TYPE_PERSONAL_NOTE;
    public const TYPE_FREE_TEXT = ContentBlueprints::TYPE_FREE_TEXT;
    public const TYPE_ACTIVITY = ContentBlueprints::TYPE_ACTIVITY;
    public const TYPE_ACCOMMODATION = ContentBlueprints::TYPE_ACCOMMODATION;
    public const TYPE_TRANSPORT = ContentBlueprints::TYPE_TRANSPORT;
    public const TYPE_MEAL = ContentBlueprints::TYPE_MEAL;
    public const TYPE_TIP = ContentBlueprints::TYPE_TIP;
    public const TYPE_NOTE = ContentBlueprints::TYPE_NOTE;
    public const TYPE_IMAGE = ContentBlueprints::TYPE_IMAGE;

    public function __construct(
        private ContentBlueprints $blueprints = new ContentBlueprints(),
        private FormContentMapper $formMapper = new FormContentMapper(),
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function createDefault(): array
    {
        return $this->blueprints->createDefault();
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    public function createBlock(string $type, array $values = []): array
    {
        return $this->blueprints->createBlock($type, $values);
    }

    /**
     * @return list<string>
     */
    public static function supportedBlockTypes(): array
    {
        return ContentBlueprints::supportedBlockTypes();
    }

    /**
     * @param array<string, mixed> $content
     *
     * @return array<string, mixed>
     */
    public function toFormData(array $content): array
    {
        return $this->formMapper->toFormData($content);
    }

    /**
     * @param array<string, mixed> $formData
     * @param array<string, mixed> $currentContent
     *
     * @return array<string, mixed>
     */
    public function fromFormData(array $formData, array $currentContent = []): array
    {
        return $this->formMapper->fromFormData($formData, $currentContent);
    }
}

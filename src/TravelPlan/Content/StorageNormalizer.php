<?php

declare(strict_types=1);

namespace App\TravelPlan\Content;

/**
 * Zet gevalideerde, getypeerde content (TravelPlanContent) om naar de
 * canonieke opslag-array. Elke waarde loopt door de blueprints, zodat
 * ontbrekende velden defaults krijgen en onbekende velden vervallen.
 *
 * NB: veldwaarden bewust via ContentValues::rawString (zonder trim):
 * de opslag-round-trip moet byte-identiek blijven.
 */
final readonly class StorageNormalizer
{
    public function __construct(
        private ContentBlueprints $blueprints = new ContentBlueprints(),
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toStorageArray(TravelPlanContent $content): array
    {
        return [
            '_version' => TravelPlanContent::VERSION,
            'intro' => $this->blueprints->createBlock(ContentBlueprints::TYPE_TRAVEL_PLAN_INTRO, [
                'title' => $content->introTitle,
                'text' => $content->introText,
            ]),
            'tripProfile' => $this->blueprints->createBlock(ContentBlueprints::TYPE_TRIP_PROFILE, [
                'startDate' => $content->tripProfile->startDate,
                'endDate' => $content->tripProfile->endDate,
                'period' => $content->tripProfile->period,
                'duration' => $content->tripProfile->duration,
                'travelParty' => $content->tripProfile->travelParty,
                'travelStyle' => $content->tripProfile->travelStyle,
                'packageType' => $content->tripProfile->packageType,
                'showTableOfContents' => ContentValues::tableOfContents($content->tripProfile->showTableOfContents),
            ]),
            'destinations' => $this->normalizeDestinations($content->destinations),
        ];
    }

    /**
     * @param list<Destination> $destinations
     *
     * @return list<array<string, mixed>>
     */
    public function normalizeDestinations(array $destinations): array
    {
        $normalized = [];

        foreach ($destinations as $destination) {
            $normalized[] = $destination->isImage()
                ? $this->imageToArray($destination)
                : $this->destinationToArray($destination);
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function destinationToArray(Destination $destination): array
    {
        return $this->blueprints->createBlock(ContentBlueprints::TYPE_DESTINATION, [
            'startOnNewPage' => $destination->startOnNewPage,
            'colorVariant' => $destination->colorVariant->value,
            'title' => ContentValues::rawString($destination->raw, 'title'),
            'country' => ContentValues::rawString($destination->raw, 'country'),
            'region' => ContentValues::rawString($destination->raw, 'region'),
            'city' => ContentValues::rawString($destination->raw, 'city'),
            'text' => ContentValues::rawString($destination->raw, 'text'),
            'icon' => $destination->iconOrDefault(),
            'sections' => $this->normalizeDestinationSections($destination->sections),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function imageToArray(Destination $destination): array
    {
        return $this->blueprints->createBlock(ContentBlueprints::TYPE_IMAGE, [
            'startOnNewPage' => $destination->startOnNewPage,
            'title' => ContentValues::rawString($destination->raw, 'title'),
            'image' => ContentValues::media($destination->raw['image'] ?? null),
            'caption' => ContentValues::rawString($destination->raw, 'caption'),
        ]);
    }

    /**
     * @param list<Section> $sections
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeDestinationSections(array $sections): array
    {
        $normalized = [];

        foreach ($sections as $section) {
            $type = $section->type->value;

            if (!\in_array($type, ContentBlueprints::DESTINATION_SECTION_TYPES, true)) {
                continue;
            }

            $defaults = $this->blueprints->createBlock($type);
            $values = [];

            foreach (\array_keys($defaults) as $field) {
                if ('type' === $field) {
                    continue;
                }

                $values[$field] = match ($field) {
                    'blocks' => $this->normalizeDayBlocks($section->blocks),
                    'routeStops' => $this->normalizeRouteStops($section->routeStops),
                    'startOnNewPage' => $section->startOnNewPage,
                    'colorVariant' => $section->colorVariant->value,
                    'icon' => $this->sectionIcon($section),
                    default => ContentValues::rawString($section->raw, $field),
                };
            }

            $normalized[] = $this->blueprints->createBlock($type, $values);
        }

        return $normalized;
    }

    /**
     * @param list<array<string, mixed>> $routeStops
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeRouteStops(array $routeStops): array
    {
        $normalized = [];

        foreach ($routeStops as $routeStop) {
            $type = $routeStop['type'] ?? null;

            if (!\in_array($type, [ContentBlueprints::TYPE_ROUTE_STOP, 'route_step'], true)) {
                continue;
            }

            $normalized[] = $this->blueprints->createBlock(ContentBlueprints::TYPE_ROUTE_STOP, [
                'title' => ContentValues::rawString($routeStop, 'title'),
                'location' => ContentValues::rawString($routeStop, 'location'),
                'text' => ContentValues::rawString($routeStop, 'text'),
                'icon' => ContentValues::rawString($routeStop, 'icon') ?: SectionType::RouteOverview->defaultIcon(),
            ]);
        }

        return $normalized;
    }

    /**
     * @param list<DayBlock> $blocks
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeDayBlocks(array $blocks): array
    {
        $normalized = [];

        foreach ($blocks as $block) {
            $type = $block->type->value;
            $defaults = $this->blueprints->createBlock($type);
            $values = [];

            foreach (\array_keys($defaults) as $field) {
                if ('type' === $field) {
                    continue;
                }

                $values[$field] = match (true) {
                    'startOnNewPage' === $field => $block->startOnNewPage,
                    'colorVariant' === $field => $block->colorVariant->value,
                    'icon' === $field => $block->iconOrDefault(),
                    \in_array($field, ['time', 'startTime', 'endTime'], true) => ContentValues::time($block->raw[$field] ?? null),
                    default => ContentValues::rawString($block->raw, $field),
                };
            }

            $normalized[] = $this->blueprints->createBlock($type, $values);
        }

        return $normalized;
    }

    private function sectionIcon(Section $section): string
    {
        if ('' !== $section->icon) {
            return $section->icon;
        }

        return match ($section->type) {
            SectionType::Checklist => '',
            default => $section->type->defaultIcon(),
        };
    }
}

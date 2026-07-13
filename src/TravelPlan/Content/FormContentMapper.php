<?php

declare(strict_types=1);

namespace App\TravelPlan\Content;

/**
 * Vertaalt tussen de admin-formulierdata en de canonieke opslag-array.
 * Formulierinput is per definitie onbetrouwbaar (Sulu levert mixed);
 * alles loopt door blueprints + TravelPlanContent-validatie.
 */
final readonly class FormContentMapper
{
    public function __construct(
        private ContentBlueprints $blueprints = new ContentBlueprints(),
        private StorageNormalizer $normalizer = new StorageNormalizer(),
    ) {
    }

    /**
     * @param array<string, mixed> $content
     *
     * @return array<string, mixed>
     */
    public function toFormData(array $content): array
    {
        $content = TravelPlanContent::fromArray(ContentValues::stringKeyed(
            \array_replace_recursive($this->blueprints->createDefault(), $content),
        ));
        $tripProfile = $content->tripProfile->raw;

        return [
            'introTitle' => $content->introTitle,
            'introText' => $content->introText,
            'startDate' => ContentValues::date($tripProfile['startDate'] ?? null),
            'endDate' => ContentValues::date($tripProfile['endDate'] ?? null),
            'travelParty' => $content->tripProfile->travelParty,
            'travelStyle' => $content->tripProfile->travelStyle,
            'packageType' => $content->tripProfile->packageType,
            'showTableOfContents' => ContentValues::tableOfContents($content->tripProfile->showTableOfContents),
            'destinations' => $this->normalizer->normalizeDestinations($content->destinations),
        ];
    }

    /**
     * @param array<string, mixed> $formData
     * @param array<string, mixed> $currentContent
     *
     * @return array<string, mixed>
     */
    public function fromFormData(array $formData, array $currentContent = []): array
    {
        $currentContent = TravelPlanContent::fromArray($currentContent);
        $currentTripProfile = $currentContent->tripProfile->raw;
        $startDate = ContentValues::date($formData['startDate'] ?? null);
        $endDate = ContentValues::date($formData['endDate'] ?? null);

        $this->validateDateRange($startDate, $endDate);

        $derivedPeriod = $this->formatPeriod($startDate, $endDate);
        $derivedDuration = $this->formatDuration($startDate, $endDate);
        $rawContent = [
            'intro' => $this->blueprints->createBlock(ContentBlueprints::TYPE_TRAVEL_PLAN_INTRO, [
                'title' => ContentValues::rawString($formData, 'introTitle'),
                'text' => ContentValues::rawString($formData, 'introText'),
            ]),
            'tripProfile' => $this->blueprints->createBlock(ContentBlueprints::TYPE_TRIP_PROFILE, [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'period' => '' !== $derivedPeriod ? $derivedPeriod : ContentValues::rawString($currentTripProfile, 'period'),
                'duration' => '' !== $derivedDuration ? $derivedDuration : ContentValues::rawString($currentTripProfile, 'duration'),
                'travelParty' => ContentValues::rawString($formData, 'travelParty'),
                'travelStyle' => ContentValues::rawString($formData, 'travelStyle'),
                'packageType' => ContentValues::rawString($formData, 'packageType'),
                'showTableOfContents' => ContentValues::tableOfContents($formData['showTableOfContents'] ?? null),
            ]),
            'destinations' => $this->normalizeFormDestinations($formData['destinations'] ?? []),
        ];

        return $this->normalizer->toStorageArray(TravelPlanContent::fromArray($rawContent));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeFormDestinations(mixed $destinations): array
    {
        $rawDestinations = [];

        if (\is_array($destinations)) {
            foreach ($destinations as $destination) {
                $destination = ContentValues::stringKeyed($destination);

                if ([] === $destination) {
                    continue;
                }

                $type = $destination['type'] ?? ContentBlueprints::TYPE_DESTINATION;

                if (ContentBlueprints::TYPE_IMAGE === $type) {
                    $rawDestinations[] = $this->blueprints->createBlock(ContentBlueprints::TYPE_IMAGE, [
                        'startOnNewPage' => ContentValues::bool($destination, 'startOnNewPage'),
                        'title' => ContentValues::rawString($destination, 'title'),
                        'image' => ContentValues::media($destination['image'] ?? null),
                        'caption' => ContentValues::rawString($destination, 'caption'),
                    ]);
                    continue;
                }

                if (ContentBlueprints::TYPE_DESTINATION !== $type) {
                    continue;
                }

                $rawDestinations[] = $this->blueprints->createBlock(ContentBlueprints::TYPE_DESTINATION, [
                    'startOnNewPage' => ContentValues::bool($destination, 'startOnNewPage'),
                    'colorVariant' => $this->colorVariantValue($destination['colorVariant'] ?? null),
                    'title' => ContentValues::rawString($destination, 'title'),
                    'country' => ContentValues::rawString($destination, 'country'),
                    'region' => ContentValues::rawString($destination, 'region'),
                    'city' => ContentValues::rawString($destination, 'city'),
                    'text' => ContentValues::rawString($destination, 'text'),
                    'icon' => ContentValues::rawString($destination, 'icon') ?: SectionType::Destination->defaultIcon(),
                    'sections' => $this->normalizeFormDestinationSections($destination['sections'] ?? []),
                ]);
            }
        }

        return $this->normalizer->normalizeDestinations(TravelPlanContent::fromArray([
            'destinations' => $rawDestinations,
        ])->destinations);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeFormDestinationSections(mixed $sections): array
    {
        if (!\is_array($sections)) {
            return [];
        }

        $normalized = [];

        foreach ($sections as $section) {
            $section = ContentValues::stringKeyed($section);

            if ([] === $section) {
                continue;
            }

            $type = $section['type'] ?? null;

            if (!\is_string($type) || !\in_array($type, ContentBlueprints::DESTINATION_SECTION_TYPES, true)) {
                continue;
            }

            $defaults = $this->blueprints->createBlock($type);
            $values = [];

            foreach (\array_keys($defaults) as $field) {
                if ('type' === $field) {
                    continue;
                }

                $values[$field] = match ($field) {
                    'blocks' => $this->normalizeFormDayBlocks($section['blocks'] ?? []),
                    'routeStops' => $this->normalizeFormRouteStops($section['routeStops'] ?? []),
                    'startOnNewPage' => ContentValues::bool($section, $field),
                    'colorVariant' => $this->colorVariantValue($section[$field] ?? null),
                    default => ContentValues::rawString($section, $field),
                };
            }

            $normalized[] = $this->blueprints->createBlock($type, $values);
        }

        return $normalized;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeFormRouteStops(mixed $routeStops): array
    {
        if (!\is_array($routeStops)) {
            return [];
        }

        $normalized = [];

        foreach ($routeStops as $routeStop) {
            $routeStop = ContentValues::stringKeyed($routeStop);

            if ([] === $routeStop) {
                continue;
            }

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
     * @return list<array<string, mixed>>
     */
    private function normalizeFormDayBlocks(mixed $blocks): array
    {
        if (!\is_array($blocks)) {
            return [];
        }

        $normalized = [];

        foreach ($blocks as $block) {
            $block = ContentValues::stringKeyed($block);

            if ([] === $block) {
                continue;
            }

            $type = $block['type'] ?? null;

            if (!\is_string($type) || !\in_array($type, ContentBlueprints::DAY_BLOCK_TYPES, true)) {
                continue;
            }

            $defaults = $this->blueprints->createBlock($type);
            $values = [];

            foreach (\array_keys($defaults) as $field) {
                if ('type' === $field) {
                    continue;
                }

                $values[$field] = match (true) {
                    'startOnNewPage' === $field => ContentValues::bool($block, $field),
                    'colorVariant' === $field => $this->colorVariantValue($block[$field] ?? null),
                    \in_array($field, ['time', 'startTime', 'endTime'], true) => ContentValues::time($block[$field] ?? null),
                    default => ContentValues::rawString($block, $field),
                };
            }

            $normalized[] = $this->blueprints->createBlock($type, $values);
        }

        return $normalized;
    }

    /**
     * Kleurkeuze uit het formulier; accepteert ook de Nederlandse
     * CMS-labels (i.t.t. ColorVariant::fromMixed, dat alleen enum-waarden
     * kent).
     */
    private function colorVariantValue(mixed $value): string
    {
        if (!\is_scalar($value)) {
            return ColorVariant::Auto->value;
        }

        return match (\trim((string) $value)) {
            'primary', 'Primair', 'Blauw' => ColorVariant::Primary->value,
            'secondary', 'Secundair', 'Geel' => ColorVariant::Secondary->value,
            'gold', 'Goud' => ColorVariant::Gold->value,
            default => ColorVariant::Auto->value,
        };
    }

    private function validateDateRange(string $startDate, string $endDate): void
    {
        if ('' === $startDate || '' === $endDate) {
            return;
        }

        if ($this->createDate($endDate) < $this->createDate($startDate)) {
            throw new \InvalidArgumentException('End date cannot be before start date.');
        }
    }

    private function formatPeriod(string $startDate, string $endDate): string
    {
        if ('' === $startDate && '' === $endDate) {
            return '';
        }

        if ('' === $startDate) {
            return $this->formatDateLabel($endDate);
        }

        if ('' === $endDate) {
            return $this->formatDateLabel($startDate);
        }

        return \sprintf('%s t/m %s', $this->formatDateLabel($startDate), $this->formatDateLabel($endDate));
    }

    private function formatDuration(string $startDate, string $endDate): string
    {
        if ('' === $startDate || '' === $endDate) {
            return '';
        }

        $days = $this->createDate($endDate)->diff($this->createDate($startDate))->days;

        return \sprintf('%d %s', $days + 1, 1 === $days + 1 ? 'dag' : 'dagen');
    }

    private function formatDateLabel(string $date): string
    {
        if ('' === $date) {
            return '';
        }

        $formatter = new \IntlDateFormatter('nl_NL', \IntlDateFormatter::LONG, \IntlDateFormatter::NONE);
        $formatter->setPattern('d MMMM yyyy');

        $formatted = $formatter->format($this->createDate($date));

        return false === $formatted ? $date : $formatted;
    }

    private function createDate(string $date): \DateTimeImmutable
    {
        return new \DateTimeImmutable($date . ' 00:00:00');
    }
}

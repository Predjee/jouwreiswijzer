<?php

declare(strict_types=1);

namespace App\TravelPlan\Content;

/**
 * Het reisprofiel (periode, duur, gezelschap, …) van een reisplan.
 */
final readonly class TripProfile
{
    /**
     * @param array<string, mixed> $raw
     */
    private function __construct(
        public string $period,
        public string $duration,
        public string $travelParty,
        public string $travelStyle,
        public string $packageType,
        public string $startDate,
        public string $endDate,
        public mixed $showTableOfContents,
        public array $raw,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            period: ContentValues::string($data, 'period'),
            duration: ContentValues::string($data, 'duration'),
            travelParty: ContentValues::string($data, 'travelParty'),
            travelStyle: ContentValues::string($data, 'travelStyle'),
            packageType: ContentValues::string($data, 'packageType'),
            startDate: ContentValues::string($data, 'startDate'),
            endDate: ContentValues::string($data, 'endDate'),
            showTableOfContents: $data['showTableOfContents'] ?? false,
            raw: $data,
        );
    }
}

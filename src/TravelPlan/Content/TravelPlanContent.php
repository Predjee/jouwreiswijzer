<?php

declare(strict_types=1);

namespace App\TravelPlan\Content;

/**
 * Getypeerd contentmodel van een reisplan.
 *
 * Dit is de plek waar het ruwe CMS-array (TravelPlan::getContent()) één
 * keer wordt geparseerd; alle consumenten (renderers, TravelCompanion,
 * pushberichten, feedback-resolving) horen op dit model te werken in
 * plaats van op losse array-keys. Onbekende types worden genegeerd,
 * ontbrekende velden krijgen veilige defaults — precies één keer, hier.
 */
final readonly class TravelPlanContent
{
    /**
     * @param list<Destination> $destinations
     * @param array<string, mixed> $raw
     */
    private function __construct(
        public string $introTitle,
        public string $introText,
        public TripProfile $tripProfile,
        public array $destinations,
        public array $raw,
    ) {
    }

    /**
     * @param array<string, mixed> $content
     */
    public static function fromArray(array $content): self
    {
        $intro = self::stringKeyedArray($content['intro'] ?? null);
        $destinations = [];

        $rawDestinations = \is_array($content['destinations'] ?? null) ? $content['destinations'] : [];
        foreach ($rawDestinations as $destinationIndex => $destinationData) {
            if (!\is_array($destinationData)) {
                continue;
            }

            /** @var array<string, mixed> $destinationData */
            $destination = Destination::fromArray($destinationData, (int) $destinationIndex);

            if (null !== $destination) {
                $destinations[] = $destination;
            }
        }

        return new self(
            introTitle: ContentValues::string($intro, 'title'),
            introText: ContentValues::string($intro, 'text'),
            tripProfile: TripProfile::fromArray(self::stringKeyedArray($content['tripProfile'] ?? null)),
            destinations: $destinations,
            raw: $content,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function stringKeyedArray(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $key => $item) {
            if (\is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }
}

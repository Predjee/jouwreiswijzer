<?php

declare(strict_types=1);

namespace App\TravelPlan\Content;

/**
 * Een top-level onderdeel van het reisplan: een bestemming (met secties)
 * of een losse afbeelding. Het ruwe CMS-array blijft beschikbaar via $raw.
 */
final readonly class Destination
{
    /**
     * @param list<Section> $sections
     * @param array<string, mixed> $raw
     */
    private function __construct(
        public SectionType $type,
        public string $title,
        public string $text,
        public string $icon,
        public string $city,
        public string $region,
        public string $country,
        public string $caption,
        public mixed $image,
        public bool $startOnNewPage,
        public ColorVariant $colorVariant,
        public array $sections,
        public array $raw,
        public int $sourceIndex,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data, int $sourceIndex = 0): ?self
    {
        $type = SectionType::tryFromMixed($data['type'] ?? null);

        if (!\in_array($type, [SectionType::Destination, SectionType::Image], true)) {
            return null;
        }

        $sections = [];

        if (SectionType::Destination === $type) {
            $rawSections = \is_array($data['sections'] ?? null) ? $data['sections'] : [];
            foreach ($rawSections as $sectionIndex => $sectionData) {
                if (!\is_array($sectionData)) {
                    continue;
                }

                /** @var array<string, mixed> $sectionData */
                $section = Section::fromArray($sectionData, (int) $sectionIndex);

                if (null !== $section) {
                    $sections[] = $section;
                }
            }
        }

        return new self(
            type: $type,
            title: ContentValues::string($data, 'title'),
            text: ContentValues::string($data, 'text'),
            icon: ContentValues::string($data, 'icon'),
            city: ContentValues::string($data, 'city'),
            region: ContentValues::string($data, 'region'),
            country: ContentValues::string($data, 'country'),
            caption: ContentValues::string($data, 'caption'),
            image: $data['image'] ?? null,
            startOnNewPage: ContentValues::bool($data, 'startOnNewPage'),
            colorVariant: ColorVariant::fromMixed($data['colorVariant'] ?? null),
            sections: $sections,
            raw: $data,
            sourceIndex: $sourceIndex,
        );
    }

    public function isImage(): bool
    {
        return SectionType::Image === $this->type;
    }

    public function iconOrDefault(): string
    {
        return '' !== $this->icon ? $this->icon : $this->type->defaultIcon();
    }

    /** Locatieregel: "stad, regio, land" (lege delen weggelaten). */
    public function locationLabel(): string
    {
        return \implode(', ', \array_filter([$this->city, $this->region, $this->country]));
    }
}

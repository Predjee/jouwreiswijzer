<?php

declare(strict_types=1);

namespace App\TravelPlan\Content;

/**
 * Eén blok (kaart) binnen een dag-sectie van een reisplan.
 *
 * Het ruwe CMS-array blijft beschikbaar via $raw voor consumenten die nog
 * niet op het getypeerde model zijn gemigreerd.
 *
 * @phpstan-type RawBlock array<string, mixed>
 */
final readonly class DayBlock
{
    /**
     * @param RawBlock $raw
     */
    private function __construct(
        public BlockType $type,
        public string $title,
        public string $text,
        public string $icon,
        public string $location,
        public string $priceLabel,
        public string $bookingUrl,
        public string $startTime,
        public string $endTime,
        public string $time,
        public string $timeLabel,
        public bool $startOnNewPage,
        public ColorVariant $colorVariant,
        public array $raw,
        public int $sourceIndex,
    ) {
    }

    /**
     * @param RawBlock $data
     */
    public static function fromArray(array $data, int $sourceIndex = 0): ?self
    {
        $type = BlockType::tryFromMixed($data['type'] ?? null);

        if (null === $type) {
            return null;
        }

        return new self(
            type: $type,
            title: ContentValues::string($data, 'title'),
            text: ContentValues::string($data, 'text'),
            icon: ContentValues::string($data, 'icon'),
            location: ContentValues::string($data, 'location'),
            priceLabel: ContentValues::string($data, 'priceLabel'),
            bookingUrl: ContentValues::string($data, 'bookingUrl'),
            startTime: ContentValues::string($data, 'startTime'),
            endTime: ContentValues::string($data, 'endTime'),
            time: ContentValues::string($data, 'time'),
            timeLabel: ContentValues::string($data, 'timeLabel'),
            startOnNewPage: ContentValues::bool($data, 'startOnNewPage'),
            colorVariant: ColorVariant::fromMixed($data['colorVariant'] ?? null),
            raw: $data,
            sourceIndex: $sourceIndex,
        );
    }

    public function iconOrDefault(): string
    {
        return '' !== $this->icon ? $this->icon : $this->type->defaultIcon();
    }

    public function plainTextLength(): int
    {
        return \mb_strlen(\strip_tags($this->text));
    }
}

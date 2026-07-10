<?php

declare(strict_types=1);

namespace App\TravelPlan\Content;

/**
 * Eén sectie binnen een bestemming (dag, vrije tekst, checklist, …).
 *
 * Bewust één klasse met een type-enum in plaats van een klasse-hiërarchie:
 * de secties delen vrijwel alle velden en de consumenten schakelen op type.
 * $blocks is alleen gevuld voor dag-secties, $routeStops alleen voor
 * route-overzichten. Het ruwe CMS-array blijft beschikbaar via $raw.
 */
final readonly class Section
{
    /**
     * @param list<DayBlock> $blocks
     * @param list<array<string, mixed>> $routeStops
     * @param array<string, mixed> $raw
     */
    private function __construct(
        public SectionType $type,
        public string $title,
        public string $text,
        public string $intro,
        public string $icon,
        public string $dayNumber,
        public string $dateLabel,
        public bool $startOnNewPage,
        public ColorVariant $colorVariant,
        public array $blocks,
        public array $routeStops,
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

        if (null === $type || SectionType::Destination === $type) {
            return null;
        }

        $blocks = [];

        if (SectionType::Day === $type) {
            $rawBlocks = \is_array($data['blocks'] ?? null) ? $data['blocks'] : [];
            foreach ($rawBlocks as $blockIndex => $blockData) {
                if (!\is_array($blockData)) {
                    continue;
                }

                /** @var array<string, mixed> $blockData */
                $block = DayBlock::fromArray($blockData, (int) $blockIndex);

                if (null !== $block) {
                    $blocks[] = $block;
                }
            }
        }

        return new self(
            type: $type,
            title: ContentValues::string($data, 'title'),
            text: ContentValues::string($data, 'text'),
            intro: ContentValues::string($data, 'intro'),
            icon: ContentValues::string($data, 'icon'),
            dayNumber: ContentValues::string($data, 'dayNumber'),
            dateLabel: ContentValues::string($data, 'dateLabel'),
            startOnNewPage: ContentValues::bool($data, 'startOnNewPage'),
            colorVariant: ColorVariant::fromMixed($data['colorVariant'] ?? null),
            blocks: $blocks,
            routeStops: SectionType::RouteOverview === $type
                ? ContentValues::arrayItems($data['routeStops'] ?? null)
                : [],
            raw: $data,
            sourceIndex: $sourceIndex,
        );
    }

    public function iconOrDefault(): string
    {
        return '' !== $this->icon ? $this->icon : $this->type->defaultIcon();
    }

    public function hasBlocks(): bool
    {
        return [] !== $this->blocks;
    }
}

<?php

declare(strict_types=1);

namespace App\Api\App\Dto;

final readonly class ScreenEnvelope
{
    /**
     * @param list<ApiSection> $sections
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public string $screen,
        public array $sections,
        public array $extra = [],
        public int $version = 1,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'screen' => $this->screen,
            'version' => $this->version,
            ...$this->extra,
            'sections' => \array_map(
                static fn (ApiSection $section): array => $section->toArray(),
                $this->sections,
            ),
        ];
    }
}

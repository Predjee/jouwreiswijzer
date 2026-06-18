<?php

declare(strict_types=1);

namespace App\Api\App\Dto;

final readonly class ApiSection
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public string $type,
        public array $data,
    ) {
    }

    /**
     * @return array{type: string, data: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'data' => $this->data,
        ];
    }
}

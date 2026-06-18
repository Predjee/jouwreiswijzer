<?php

declare(strict_types=1);

namespace App\Api\App\ReadModel;

final readonly class NotificationReadModel
{
    /**
     * @param list<array{
     *     id: int,
     *     type: string,
     *     title: string,
     *     message: string,
     *     read: bool,
     *     createdAt: string,
     *     action?: array<string, mixed>
     * }> $items
     */
    public function __construct(
        public int $unreadCount,
        public array $items,
    ) {
    }

    public function hasItems(): bool
    {
        return [] !== $this->items;
    }
}

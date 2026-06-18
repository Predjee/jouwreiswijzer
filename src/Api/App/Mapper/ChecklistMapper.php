<?php

declare(strict_types=1);

namespace App\Api\App\Mapper;

use App\Api\App\Dto\ApiSection;
use App\Api\App\Dto\ScreenEnvelope;
use App\Api\App\ReadModel\ChecklistReadModel;

final class ChecklistMapper
{
    /**
     * @return array<string, mixed>
     */
    public function map(ChecklistReadModel $checklist): array
    {
        $sections = [
            new ApiSection('hero', [
                'title' => 'Checklist',
                'completed' => $checklist->completed,
                'total' => $checklist->total,
            ]),
            new ApiSection('progress', [
                'title' => 'Voortgang',
                'completed' => $checklist->completed,
                'total' => $checklist->total,
            ]),
        ];

        foreach ($checklist->checklists as $section) {
            $sections[] = new ApiSection('checklist', [
                'title' => $section['title'],
                'items' => $section['items'],
            ]);
        }

        if (!$checklist->hasItems()) {
            $sections[] = new ApiSection('empty_state', [
                'title' => 'Geen checklist beschikbaar',
                'text' => 'Voor deze reis staan nog geen checklist-items klaar.',
            ]);
        }

        $envelope = new ScreenEnvelope(
            screen: 'checklist',
            sections: $sections,
            extra: [
                'title' => 'Checklist',
                'trip' => [
                    'id' => $checklist->tripId,
                    'title' => $checklist->tripTitle,
                ],
            ],
        );

        return $envelope->toArray();
    }
}

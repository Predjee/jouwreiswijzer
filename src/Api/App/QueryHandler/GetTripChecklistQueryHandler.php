<?php

declare(strict_types=1);

namespace App\Api\App\QueryHandler;

use App\Api\App\Query\GetTripChecklistQuery;
use App\Api\App\ReadModel\ChecklistReadModel;
use App\Entity\TravelPlan;
use App\Repository\TravelPlanChecklistStateRepository;
use App\Repository\TravelPlanRepository;
use App\Service\TravelCompanion\CompanionContentHelper;

final readonly class GetTripChecklistQueryHandler
{
    public function __construct(
        private TravelPlanRepository $travelPlanRepository,
        private TravelPlanChecklistStateRepository $checklistStateRepository,
    ) {
    }

    public function handle(GetTripChecklistQuery $query): ?ChecklistReadModel
    {
        $travelPlan = $this->travelPlanRepository->findPublishedForContact($query->tripId, $query->contact);

        if (null === $travelPlan) {
            return null;
        }

        $checkedItems = $this->checklistStateRepository->checkedMapForPlan($query->contact, $travelPlan);
        $checklists = $this->checklists($travelPlan, $checkedItems);
        $total = 0;
        $completed = 0;

        foreach ($checklists as $checklist) {
            foreach ($checklist['items'] as $item) {
                ++$total;

                if ($item['checked']) {
                    ++$completed;
                }
            }
        }

        return new ChecklistReadModel(
            $travelPlan->getId() ?? 0,
            $travelPlan->getTitle(),
            $completed,
            $total,
            $checklists,
        );
    }

    /**
     * @param array<string, bool> $checkedItems
     *
     * @return list<array{title: string, items: list<array{id: string, label: string, checked: bool}>}>
     */
    private function checklists(TravelPlan $travelPlan, array $checkedItems): array
    {
        $content = $travelPlan->getContent();
        $checklists = [];

        foreach (CompanionContentHelper::destinationSections($content) as $sectionData) {
            $section = $sectionData['section'];

            if (!\is_array($section) || 'checklist' !== ($section['type'] ?? null)) {
                continue;
            }

            $sectionPath = \sprintf(
                'destinations[%d].sections[%d]',
                $sectionData['destinationIndex'],
                $sectionData['sectionIndex'],
            );
            $items = $this->items(
                $travelPlan->getId() ?? 0,
                $sectionPath,
                CompanionContentHelper::stringValue($section, 'text'),
                $checkedItems,
            );

            if ([] === $items) {
                continue;
            }

            $checklists[] = [
                'title' => CompanionContentHelper::stringValue($section, 'title') ?: 'Checklist',
                'items' => $items,
            ];
        }

        return $checklists;
    }

    /**
     * @param array<string, bool> $checkedItems
     *
     * @return list<array{id: string, label: string, checked: bool}>
     */
    private function items(int $travelPlanId, string $sectionPath, string $text, array $checkedItems): array
    {
        $items = [];
        $itemIndex = 0;

        foreach (\preg_split('/\R/', $text) ?: [] as $line) {
            $label = $this->normalizeLine((string) $line);

            if ('' === $label) {
                continue;
            }

            $key = $this->itemKey($travelPlanId, $sectionPath, $itemIndex, $label);
            $items[] = [
                'id' => $key,
                'label' => $label,
                'checked' => $checkedItems[$key] ?? false,
            ];
            ++$itemIndex;
        }

        return $items;
    }

    private function normalizeLine(string $line): string
    {
        $line = \trim(\strip_tags($line), " \t\n\r\0\x0B-*•");
        $line = (string) \preg_replace('/\s+/', ' ', $line);

        return \trim($line);
    }

    private function itemKey(int $travelPlanId, string $sectionPath, int $lineIndex, string $normalizedLine): string
    {
        return \sha1(\sprintf('%d|%s|%d|%s', $travelPlanId, $sectionPath, $lineIndex, $normalizedLine));
    }
}

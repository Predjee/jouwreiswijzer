<?php

declare(strict_types=1);

namespace App\Api\App\CommandHandler;

use App\Api\App\Command\ToggleChecklistItemCommand;
use App\Entity\TravelPlan;
use App\Entity\TravelPlanChecklistState;
use App\Event\ChecklistItemToggledEvent;
use App\Repository\TravelPlanChecklistStateRepository;
use App\Repository\TravelPlanRepository;
use App\Companion\CompanionContentHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class ToggleChecklistItemCommandHandler
{
    public function __construct(
        private TravelPlanRepository $travelPlanRepository,
        private TravelPlanChecklistStateRepository $checklistStateRepository,
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @return array{itemId: string, checked: bool, completed: int, total: int}
     */
    public function handle(ToggleChecklistItemCommand $command): array
    {
        $resolved = $this->resolveItem($command);

        if (null === $resolved) {
            throw new NotFoundHttpException();
        }

        $travelPlan = $resolved['travelPlan'];
        $state = $this->checklistStateRepository->findOneForItem($command->contact, $travelPlan, $command->itemId);

        if (null === $state) {
            $state = (new TravelPlanChecklistState())
                ->setContact($command->contact)
                ->setTravelPlan($travelPlan)
                ->setItemKey($command->itemId);

            $this->entityManager->persist($state);
        }

        $checked = !$state->isChecked();
        $state->setCheckedAt($checked ? new \DateTimeImmutable() : null);

        $this->entityManager->flush();
        $this->eventDispatcher->dispatch(new ChecklistItemToggledEvent(
            $command->contact,
            $travelPlan,
            $command->itemId,
            $checked,
        ));

        $checkedItems = $this->checklistStateRepository->checkedMapForPlan($command->contact, $travelPlan);
        $progress = $this->progress($resolved['itemIds'], $checkedItems);

        return [
            'itemId' => $command->itemId,
            'checked' => $checked,
            'completed' => $progress['completed'],
            'total' => $progress['total'],
        ];
    }

    /**
     * @return array{travelPlan: TravelPlan, itemIds: list<string>}|null
     */
    private function resolveItem(ToggleChecklistItemCommand $command): ?array
    {
        foreach ($this->travelPlanRepository->findPublishedByContact($command->contact) as $travelPlan) {
            $itemIds = $this->itemIds($travelPlan);

            if (\in_array($command->itemId, $itemIds, true)) {
                return [
                    'travelPlan' => $travelPlan,
                    'itemIds' => $itemIds,
                ];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function itemIds(TravelPlan $travelPlan): array
    {
        $content = $travelPlan->getContent();
        $itemIds = [];

        foreach (CompanionContentHelper::destinationSections($content) as $sectionData) {
            $section = $sectionData['section'];

            if ('checklist' !== ($section['type'] ?? null)) {
                continue;
            }

            $itemIndex = 0;
            $sectionPath = \sprintf(
                'destinations[%d].sections[%d]',
                $sectionData['destinationIndex'],
                $sectionData['sectionIndex'],
            );

            foreach (\preg_split('/\R/', CompanionContentHelper::stringValue($section, 'text')) ?: [] as $line) {
                $label = $this->normalizeLine((string) $line);

                if ('' === $label) {
                    continue;
                }

                $itemIds[] = $this->itemKey($travelPlan->getId() ?? 0, $sectionPath, $itemIndex, $label);
                ++$itemIndex;
            }
        }

        return $itemIds;
    }

    /**
     * @param list<string>         $itemIds
     * @param array<string, bool>  $checkedItems
     *
     * @return array{completed: int, total: int}
     */
    private function progress(array $itemIds, array $checkedItems): array
    {
        $completed = 0;

        foreach ($itemIds as $itemId) {
            if ($checkedItems[$itemId] ?? false) {
                ++$completed;
            }
        }

        return [
            'completed' => $completed,
            'total' => \count($itemIds),
        ];
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

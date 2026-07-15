<?php

declare(strict_types=1);

namespace App\Companion;

use App\Entity\TravelPlan;
use App\Repository\TravelPlanRepository;
use App\TravelPlan\Content\ContentValues;
use App\ViewModel\TravelCompanion\TodayContext;
use App\ViewModel\TravelCompanion\TodayTravelPlan;
use Sulu\Bundle\ContactBundle\Entity\Contact;

final readonly class TodayContextBuilder
{
    public function __construct(private TravelPlanRepository $travelPlanRepository)
    {
    }

    public function build(Contact $contact): TodayContext
    {
        $selection = $this->selectTravelPlan($this->travelPlanRepository->findPublishedByContact($contact));

        if (null === $selection) {
            return new TodayContext(null, false);
        }

        return new TodayContext($this->createViewModel($selection), true);
    }

    /**
     * @param list<TravelPlan> $travelPlans
     *
     * @return array{
     *     mode: string,
     *     travelPlan: TravelPlan,
     *     startDate: \DateTimeImmutable,
     *     endDate: \DateTimeImmutable,
     *     currentDay: ?int,
     *     totalDays: int
     * }|null
     */
    private function selectTravelPlan(array $travelPlans): ?array
    {
        $today = new \DateTimeImmutable('today');
        $active = [];
        $upcoming = [];

        foreach ($travelPlans as $travelPlan) {
            $tripProfile = $this->tripProfile($travelPlan);
            $startDate = CompanionContentHelper::createDate($tripProfile['startDate'] ?? null);
            $endDate = CompanionContentHelper::createDate($tripProfile['endDate'] ?? null);

            if (!$startDate instanceof \DateTimeImmutable || !$endDate instanceof \DateTimeImmutable) {
                continue;
            }

            if ($endDate < $startDate) {
                continue;
            }

            $totalDays = CompanionContentHelper::inclusiveDays($startDate, $endDate);

            if ($startDate <= $today && $today <= $endDate) {
                $active[] = [
                    'mode' => 'active',
                    'travelPlan' => $travelPlan,
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'currentDay' => CompanionContentHelper::inclusiveDays($startDate, $today),
                    'totalDays' => $totalDays,
                ];

                continue;
            }

            if ($startDate > $today) {
                $upcoming[] = [
                    'mode' => 'upcoming',
                    'travelPlan' => $travelPlan,
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'currentDay' => null,
                    'totalDays' => $totalDays,
                ];
            }
        }

        if ([] !== $active) {
            \usort(
                $active,
                static fn (array $left, array $right): int => $right['startDate'] <=> $left['startDate'],
            );

            return $active[0];
        }

        if ([] !== $upcoming) {
            \usort(
                $upcoming,
                static fn (array $left, array $right): int => $left['startDate'] <=> $right['startDate'],
            );

            return $upcoming[0];
        }

        return null;
    }

    /**
     * @param array{
     *     mode: string,
     *     travelPlan: TravelPlan,
     *     startDate: \DateTimeImmutable,
     *     endDate: \DateTimeImmutable,
     *     currentDay: ?int,
     *     totalDays: int
     * } $selection
     */
    private function createViewModel(array $selection): TodayTravelPlan
    {
        $travelPlan = $selection['travelPlan'];
        $daySection = 'active' === $selection['mode']
            ? $this->findDaySection($travelPlan, $selection['currentDay'])
            : null;

        return new TodayTravelPlan(
            $travelPlan->getId() ?? 0,
            $travelPlan->getTitle(),
            $selection['mode'],
            $selection['currentDay'],
            $selection['totalDays'],
            $this->periodLabel($selection['startDate'], $selection['endDate']),
            $this->durationLabel($selection['totalDays']),
            $this->timingLabel($selection),
            $travelPlan->isPdfReleased(),
            CompanionContentHelper::stringValue($daySection ?? [], 'title') ?: null,
            CompanionContentHelper::stringValue($daySection ?? [], 'dateLabel') ?: null,
            CompanionContentHelper::stringValue($daySection ?? [], 'intro') ?: null,
            'active' === $selection['mode'],
            'upcoming' === $selection['mode'],
        );
    }

    /**
     * @param array{mode: string, startDate: \DateTimeImmutable, currentDay: ?int, totalDays: int} $selection
     */
    private function timingLabel(array $selection): string
    {
        if ('active' === $selection['mode'] && null !== $selection['currentDay']) {
            return \sprintf('Vandaag is dag %d van %d', $selection['currentDay'], $selection['totalDays']);
        }

        $daysUntilDeparture = CompanionContentHelper::daysBetween(new \DateTimeImmutable('today'), $selection['startDate']);

        return \sprintf('Vertrekt over %d %s', $daysUntilDeparture, 1 === $daysUntilDeparture ? 'dag' : 'dagen');
    }

    /**
     * @return array<string, mixed>
     */
    private function tripProfile(TravelPlan $travelPlan): array
    {
        return ContentValues::stringKeyed($travelPlan->getContent()['tripProfile'] ?? null);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findDaySection(TravelPlan $travelPlan, ?int $dayNumber): ?array
    {
        if (null === $dayNumber) {
            return null;
        }

        foreach (CompanionContentHelper::destinationSections($travelPlan->getContent()) as $sectionData) {
            $section = $sectionData['section'];

            if ('day' !== ($section['type'] ?? null)) {
                continue;
            }

            $sectionDayNumber = $section['dayNumber'] ?? null;

            if (\is_scalar($sectionDayNumber) && (int) $sectionDayNumber === $dayNumber) {
                return $section;
            }
        }

        return null;
    }

    private function periodLabel(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): string
    {
        return \sprintf(
            '%s t/m %s',
            CompanionContentHelper::dateLabel($startDate),
            CompanionContentHelper::dateLabel($endDate),
        );
    }

    private function durationLabel(int $days): string
    {
        return \sprintf('%d %s', $days, 1 === $days ? 'dag' : 'dagen');
    }
}

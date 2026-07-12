<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\TravelPlan;
use App\TravelPlan\Content\ContentValues;
use App\Entity\TravelPlanFeedback;
use App\Repository\TravelPlanFeedbackRepository;
use Sulu\Bundle\ContactBundle\Entity\Contact;

final readonly class AccountDashboardBuilder
{
    public function __construct(private TravelPlanFeedbackRepository $feedbackRepository)
    {
    }

    /**
     * @param list<TravelPlan> $travelPlans
     *
     * @return list<array{
     *     travelPlan: TravelPlan,
     *     statusLabel: string,
     *     openFeedbackCount: int,
     *     processedFeedbackCount: int,
     *     pdfAvailable: bool,
     *     startDate: ?string,
     *     endDate: ?string,
     *     periodLabel: string,
     *     durationLabel: string,
     *     timingLabel: string,
     *     travelState: string
     * }>
     */
    public function buildCards(array $travelPlans, Contact $contact): array
    {
        $cards = [];
        $today = new \DateTimeImmutable('today');

        foreach ($travelPlans as $travelPlan) {
            $openFeedbackCount = 0;
            $processedFeedbackCount = 0;

            foreach ($this->feedbackRepository->findForPlanAndContact($travelPlan, $contact) as $feedback) {
                if (\in_array($feedback->getStatus(), [
                    TravelPlanFeedback::STATUS_OPEN,
                    TravelPlanFeedback::STATUS_IN_PROGRESS,
                ], true)) {
                    ++$openFeedbackCount;
                    continue;
                }

                if (TravelPlanFeedback::STATUS_RESOLVED === $feedback->getStatus()) {
                    ++$processedFeedbackCount;
                }
            }

            $pdfAvailable = $travelPlan->isPdfReleased();
            $statusLabel = match (true) {
                $openFeedbackCount > 0 => 'Feedback open',
                $pdfAvailable => 'Reisgids beschikbaar',
                default => 'In review',
            };
            $dateContext = $this->dateContext($travelPlan, $today);

            $cards[] = [
                'travelPlan' => $travelPlan,
                'statusLabel' => $statusLabel,
                'openFeedbackCount' => $openFeedbackCount,
                'processedFeedbackCount' => $processedFeedbackCount,
                'pdfAvailable' => $pdfAvailable,
                'startDate' => $dateContext['startDate'],
                'endDate' => $dateContext['endDate'],
                'periodLabel' => $dateContext['periodLabel'],
                'durationLabel' => $dateContext['durationLabel'],
                'timingLabel' => $dateContext['timingLabel'],
                'travelState' => $dateContext['travelState'],
            ];
        }

        \usort($cards, $this->compareCards(...));

        return $cards;
    }

    /**
     * @param list<TravelPlan> $travelPlans
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function buildSections(array $travelPlans, Contact $contact): array
    {
        $sections = [
            'active' => [],
            'upcoming' => [],
            'unknown' => [],
            'past' => [],
        ];

        foreach ($this->buildCards($travelPlans, $contact) as $card) {
            $sections[$card['travelState']][] = $card;
        }

        return $sections;
    }

    /**
     * @return array{
     *     startDate: ?string,
     *     endDate: ?string,
     *     periodLabel: string,
     *     durationLabel: string,
     *     timingLabel: string,
     *     travelState: string
     * }
     */
    private function dateContext(TravelPlan $travelPlan, \DateTimeImmutable $today): array
    {
        $tripProfile = ContentValues::stringKeyed($travelPlan->getContent()['tripProfile'] ?? null);
        $startDate = $this->createDate($tripProfile['startDate'] ?? null);
        $endDate = $this->createDate($tripProfile['endDate'] ?? null);

        if (!$startDate instanceof \DateTimeImmutable || !$endDate instanceof \DateTimeImmutable) {
            return [
                'startDate' => $startDate?->format('Y-m-d'),
                'endDate' => $endDate?->format('Y-m-d'),
                'periodLabel' => $this->stringValue($tripProfile, 'period'),
                'durationLabel' => $this->stringValue($tripProfile, 'duration'),
                'timingLabel' => 'Datum nog niet bekend',
                'travelState' => 'unknown',
            ];
        }

        if ($endDate < $startDate) {
            return [
                'startDate' => $startDate->format('Y-m-d'),
                'endDate' => $endDate->format('Y-m-d'),
                'periodLabel' => $this->stringValue($tripProfile, 'period'),
                'durationLabel' => $this->stringValue($tripProfile, 'duration'),
                'timingLabel' => 'Datum nog niet bekend',
                'travelState' => 'unknown',
            ];
        }

        $totalDays = $this->inclusiveDays($startDate, $endDate);

        if ($startDate > $today) {
            $daysUntilDeparture = $this->daysBetween($today, $startDate);

            return [
                'startDate' => $startDate->format('Y-m-d'),
                'endDate' => $endDate->format('Y-m-d'),
                'periodLabel' => $this->periodLabel($startDate, $endDate),
                'durationLabel' => $this->durationLabel($totalDays),
                'timingLabel' => \sprintf('Vertrekt over %d %s', $daysUntilDeparture, 1 === $daysUntilDeparture ? 'dag' : 'dagen'),
                'travelState' => 'upcoming',
            ];
        }

        if ($endDate < $today) {
            return [
                'startDate' => $startDate->format('Y-m-d'),
                'endDate' => $endDate->format('Y-m-d'),
                'periodLabel' => $this->periodLabel($startDate, $endDate),
                'durationLabel' => $this->durationLabel($totalDays),
                'timingLabel' => 'Afgerond',
                'travelState' => 'past',
            ];
        }

        $currentDay = $this->inclusiveDays($startDate, $today);

        return [
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d'),
            'periodLabel' => $this->periodLabel($startDate, $endDate),
            'durationLabel' => $this->durationLabel($totalDays),
            'timingLabel' => \sprintf('Vandaag is dag %d van %d', $currentDay, $totalDays),
            'travelState' => 'active',
        ];
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    private function compareCards(array $left, array $right): int
    {
        $order = [
            'active' => 0,
            'upcoming' => 1,
            'unknown' => 2,
            'past' => 3,
        ];
        $leftState = $this->stringValue($left, 'travelState');
        $rightState = $this->stringValue($right, 'travelState');
        $stateComparison = ($order[$leftState] ?? 4) <=> ($order[$rightState] ?? 4);

        if (0 !== $stateComparison) {
            return $stateComparison;
        }

        if ('upcoming' === $leftState) {
            return $this->stringValue($left, 'startDate') <=> $this->stringValue($right, 'startDate');
        }

        if ('past' === $leftState) {
            return $this->stringValue($right, 'endDate') <=> $this->stringValue($left, 'endDate');
        }

        if ('active' === $leftState) {
            return $this->stringValue($left, 'endDate') <=> $this->stringValue($right, 'endDate');
        }

        $leftPlan = $left['travelPlan'] ?? null;
        $rightPlan = $right['travelPlan'] ?? null;

        return ($leftPlan instanceof TravelPlan ? $leftPlan->getId() : 0)
            <=> ($rightPlan instanceof TravelPlan ? $rightPlan->getId() : 0);
    }

    private function createDate(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return new \DateTimeImmutable($value->format('Y-m-d').' 00:00:00');
        }

        if (!\is_scalar($value)) {
            return null;
        }

        $value = \trim((string) $value);

        if (1 !== \preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();

        if (
            !$date instanceof \DateTimeImmutable
            || (false !== $errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        ) {
            return null;
        }

        return $date;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function stringValue(array $data, string $key): string
    {
        $value = $data[$key] ?? '';

        return \is_scalar($value) ? (string) $value : '';
    }

    private function periodLabel(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): string
    {
        return \sprintf('%s t/m %s', $this->dateLabel($startDate), $this->dateLabel($endDate));
    }

    private function durationLabel(int $days): string
    {
        return \sprintf('%d %s', $days, 1 === $days ? 'dag' : 'dagen');
    }

    private function dateLabel(\DateTimeImmutable $date): string
    {
        $months = [
            1 => 'januari',
            2 => 'februari',
            3 => 'maart',
            4 => 'april',
            5 => 'mei',
            6 => 'juni',
            7 => 'juli',
            8 => 'augustus',
            9 => 'september',
            10 => 'oktober',
            11 => 'november',
            12 => 'december',
        ];

        return \sprintf('%d %s', (int) $date->format('j'), $months[(int) $date->format('n')]);
    }

    private function inclusiveDays(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): int
    {
        return $this->daysBetween($startDate, $endDate) + 1;
    }

    private function daysBetween(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): int
    {
        // DateInterval::$days is false bij een niet-berekenbaar verschil;
        // de int-cast maakt daar 0 van zonder dode-vergelijking-melding.
        return (int) $startDate->diff($endDate)->days;
    }
}

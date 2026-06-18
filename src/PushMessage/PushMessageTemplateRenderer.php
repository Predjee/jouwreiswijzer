<?php

declare(strict_types=1);

namespace App\PushMessage;

use App\Entity\TravelPlan;
use App\Service\TravelCompanion\CompanionContentHelper;

final readonly class PushMessageTemplateRenderer
{
    /**
     * @param array{number?: int, title?: string, date?: \DateTimeImmutable} $day
     */
    public function render(string $template, TravelPlan $travelPlan, array $day = []): string
    {
        return \strtr($template, $this->placeholders($travelPlan, $day));
    }

    /**
     * @param array{number?: int, title?: string, date?: \DateTimeImmutable} $day
     *
     * @return array<string, string>
     */
    private function placeholders(TravelPlan $travelPlan, array $day): array
    {
        $content = $travelPlan->getContent();
        $tripProfile = \is_array($content['tripProfile'] ?? null) ? $content['tripProfile'] : [];
        $startDate = CompanionContentHelper::createDate($tripProfile['startDate'] ?? null);
        $endDate = CompanionContentHelper::createDate($tripProfile['endDate'] ?? null);
        $contact = $travelPlan->getTravelRequest()->getContact();

        return [
            '{{ trip.title }}' => $travelPlan->getTitle(),
            '{{ trip.startDate }}' => $this->formatDate($startDate),
            '{{ trip.endDate }}' => $this->formatDate($endDate),
            '{{ trip.totalDays }}' => $this->totalDays($startDate, $endDate),
            '{{ day.number }}' => isset($day['number']) ? (string) $day['number'] : '',
            '{{ day.title }}' => $day['title'] ?? '',
            '{{ day.date }}' => $this->formatDate($day['date'] ?? null),
            '{{ customer.firstName }}' => $this->customerFirstName($contact),
        ];
    }

    private function formatDate(?\DateTimeImmutable $date): string
    {
        return $date instanceof \DateTimeImmutable ? $date->format('Y-m-d') : '';
    }

    private function totalDays(?\DateTimeImmutable $startDate, ?\DateTimeImmutable $endDate): string
    {
        if (!$startDate instanceof \DateTimeImmutable || !$endDate instanceof \DateTimeImmutable || $endDate < $startDate) {
            return '';
        }

        return (string) CompanionContentHelper::inclusiveDays($startDate, $endDate);
    }

    private function customerFirstName(object $contact): string
    {
        if (\method_exists($contact, 'getFirstName')) {
            $firstName = $contact->getFirstName();

            return \is_scalar($firstName) ? (string) $firstName : '';
        }

        return '';
    }
}

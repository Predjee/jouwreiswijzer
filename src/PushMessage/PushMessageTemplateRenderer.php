<?php

declare(strict_types=1);

namespace App\PushMessage;

use App\Entity\TravelPlan;

final readonly class PushMessageTemplateRenderer
{
    public function __construct(
        private TravelPlanPersonalizationContextBuilder $contextBuilder,
    ) {
    }

    /**
     * @param array{number?: int, title?: string, date?: \DateTimeImmutable} $day
     */
    public function render(string $template, TravelPlan $travelPlan, array $day = []): string
    {
        $contact = $travelPlan->getTravelRequest()->getContact();
        $context = $this->contextBuilder->build($travelPlan, $contact, $day);

        return $this->renderWithValues($template, $context['values']);
    }

    /**
     * @param array<string, string> $values
     */
    public function renderWithValues(string $template, array $values): string
    {
        return (string) \preg_replace_callback(
            '/{{\s*([a-zA-Z][a-zA-Z0-9]*(?:\.[a-zA-Z][a-zA-Z0-9]*)*)\s*}}/',
            static fn (array $matches): string => \array_key_exists($matches[1], $values)
                ? $values[$matches[1]]
                : $matches[0],
            $template,
        );
    }
}

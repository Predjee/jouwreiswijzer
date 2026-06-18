<?php

declare(strict_types=1);

namespace App\Content\Select;

class TimezoneSelect
{
    /**
     * Haalt alle IANA tijdzones op voor de Sulu select component.
     *
     * * @return array<int, array{name: string, title: string}>
     */
    public function getValues(): array
    {
        $timezones = \DateTimeZone::listIdentifiers();
        $values = [];

        foreach ($timezones as $timezone) {
            $values[] = [
                'name' => $timezone,
                'title' => $timezone,
            ];
        }

        return $values;
    }

    /**
     * Optioneel: Geef een standaard tijdzone op (bijv. Amsterdam).
     */
    public function getDefaultValue(): string
    {
        return 'Europe/Amsterdam';
    }
}

<?php

declare(strict_types=1);

namespace App\TravelPlan\Content;

/**
 * De sectietypes binnen een bestemming van een reisplan.
 */
enum SectionType: string
{
    case Destination = 'destination';
    case RouteOverview = 'route_overview';
    case Day = 'day';
    case PracticalInfo = 'practical_info';
    case Checklist = 'checklist';
    case BudgetNote = 'budget_note';
    case PersonalNote = 'personal_note';
    case FreeText = 'free_text';
    case Image = 'image';

    public static function tryFromMixed(mixed $value): ?self
    {
        return \is_string($value) ? self::tryFrom($value) : null;
    }

    public function defaultIcon(): string
    {
        return match ($this) {
            self::Destination, self::RouteOverview => 'map',
            self::Day => 'compass',
            self::Checklist => 'list-check',
            self::BudgetNote => 'wallet',
            self::PersonalNote => 'heart',
            self::Image => 'image',
            self::PracticalInfo, self::FreeText => 'info',
        };
    }
}

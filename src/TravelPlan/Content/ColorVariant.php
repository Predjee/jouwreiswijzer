<?php

declare(strict_types=1);

namespace App\TravelPlan\Content;

/**
 * De kleurkeuze uit het CMS ("Kleur"); bepaalt het palet in web én PDF
 * (zie TravelPlanStyle::variants()).
 */
enum ColorVariant: string
{
    case Auto = 'auto';
    case Primary = 'primary';
    case Secondary = 'secondary';
    case Gold = 'gold';

    public static function fromMixed(mixed $value): self
    {
        if (!\is_scalar($value)) {
            return self::Auto;
        }

        return self::tryFrom(\strtolower(\trim((string) $value))) ?? self::Auto;
    }
}

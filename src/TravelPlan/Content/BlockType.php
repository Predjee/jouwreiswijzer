<?php

declare(strict_types=1);

namespace App\TravelPlan\Content;

/**
 * De bloktypes binnen een dag-sectie.
 */
enum BlockType: string
{
    case Activity = 'activity';
    case Accommodation = 'accommodation';
    case Transport = 'transport';
    case Meal = 'meal';
    case Tip = 'tip';
    case Note = 'note';
    case FreeText = 'free_text';

    public static function tryFromMixed(mixed $value): ?self
    {
        return \is_string($value) ? self::tryFrom($value) : null;
    }

    public function defaultIcon(): string
    {
        return match ($this) {
            self::Activity => 'compass',
            self::Accommodation => 'bed',
            self::Transport => 'car',
            self::Meal => 'utensils',
            self::Tip => 'lightbulb',
            self::Note => 'sticky-note',
            self::FreeText => 'info',
        };
    }

    public function actionLabel(): string
    {
        return match ($this) {
            self::Accommodation => 'Bekijk accommodatie',
            self::Transport => 'Bekijk vervoer',
            self::Meal => 'Bekijk restaurant',
            self::Activity => 'Bekijk of reserveer',
            default => 'Bekijk link',
        };
    }
}

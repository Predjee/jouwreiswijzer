<?php

declare(strict_types=1);

namespace App\TravelPlan\Pdf;

/**
 * Design tokens voor PDF-rendering.
 *
 * Twig ontvangt deze waarden als `t`, zodat inline mPDF-styling niet overal
 * losse kleurcodes hoeft te herhalen.
 */
final class TravelPlanPdfStyle
{
    public const NAVY = '#12213d';
    public const GOLD = '#b99550';
    public const GOLD_LIGHT = '#d4af62';
    public const CREAM = '#f8f5ef';
    public const EDGE = '#e3ddcd';
    public const EDGE_CARD = '#e1e4ea';
    public const TEXT_LIGHT = '#f7f4ee';
    public const TEXT_SOFT = '#e7e0d3';
    public const TEXT_CONTENT_LIGHT = '#eef1f6';
    public const WHITE = '#ffffff';

    /**
     * mPDF kan een tabelrij niet splitsen. Blokken boven deze grens mogen
     * daarom niet in een keep-together tabel worden gezet.
     */
    public const KEEP_TOGETHER_MAX_CHARS = 3200;

    /**
     * @return array<string, string>
     */
    public static function tokens(): array
    {
        return [
            'navy' => self::NAVY,
            'gold' => self::GOLD,
            'goldLight' => self::GOLD_LIGHT,
            'cream' => self::CREAM,
            'edge' => self::EDGE,
            'edgeCard' => self::EDGE_CARD,
            'textLight' => self::TEXT_LIGHT,
            'textSoft' => self::TEXT_SOFT,
            'textContentLight' => self::TEXT_CONTENT_LIGHT,
            'white' => self::WHITE,
        ];
    }
}

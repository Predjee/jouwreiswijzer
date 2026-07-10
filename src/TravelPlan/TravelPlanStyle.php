<?php

declare(strict_types=1);

namespace App\TravelPlan;

/**
 * Gedeelde design-tokens voor de reisplan-weergaven (PDF én mijn-omgeving).
 *
 * Twig ontvangt deze waarden als `t`, zodat inline mPDF-styling niet overal
 * losse kleurcodes hoeft te herhalen.
 */
final class TravelPlanStyle
{
    public const NAVY = '#071828';
    public const GOLD = '#d4af37';
    public const GOLD_LIGHT = '#e1bd55';
    public const CREAM = '#f8f5ef';
    public const EDGE = '#e3ddcd';
    public const EDGE_CARD = '#e1e4ea';
    public const TEXT_LIGHT = '#f7f4ee';
    public const TEXT_SOFT = '#d8dde2';
    public const TEXT_CONTENT_LIGHT = '#eef1f6';
    public const WHITE = '#ffffff';
    public const CARD_RADIUS = '1.8mm';
    public const SECTION_RADIUS = '2mm';

    /**
     * mPDF kan een tabelrij niet splitsen. Blokken boven deze grens mogen
     * daarom niet in een keep-together tabel worden gezet.
     */
    public const KEEP_TOGETHER_MAX_CHARS = 3200;

    /** Rustige donkere bodytekst (zachter dan puur navy). */
    public const TEXT_BODY = '#333f52';

    /** Zone-tint voor de kaartachtergrond binnen groepen: één tint dieper
     * dan de pagina, zodat de nesting subtiel zichtbaar blijft. */
    public const ZONE = '#f3eee1';

    /**
     * Complete kleurpaletten per CMS-kleurkeuze ("PDF kleur"). Eén bron:
     * kies je een variant, dan kleuren achtergrond, randen, accent, titel,
     * tekst, meta en links consistent mee. `accent` null = geen accentbalk
     * (de standaard blijft bewust kaal: luxe zit in terughoudendheid).
     *
     * @return array<string, array<string, string|null>>
     */
    public static function variants(): array
    {
        return [
            'default' => [
                'background' => self::WHITE,
                'edge' => '#e6e2d7',
                'accent' => null,
                'bar' => self::GOLD,
                'title' => self::NAVY,
                'body' => self::TEXT_BODY,
                'meta' => '#8a6a31',
                'link' => '#86672f',
            ],
            'primary' => [
                'background' => self::NAVY,
                'edge' => self::NAVY,
                'accent' => self::GOLD,
                'bar' => self::NAVY,
                'title' => self::WHITE,
                'body' => self::TEXT_CONTENT_LIGHT,
                'meta' => self::GOLD_LIGHT,
                'link' => self::GOLD_LIGHT,
            ],
            'secondary' => [
                'background' => '#fdf9ee',
                'edge' => '#e8dcb4',
                'accent' => self::GOLD,
                'bar' => self::GOLD_LIGHT,
                'title' => self::NAVY,
                'body' => self::TEXT_BODY,
                'meta' => '#8a6a31',
                'link' => '#86672f',
            ],
            'gold' => [
                'background' => '#faf3dc',
                'edge' => '#ddc06d',
                'accent' => self::GOLD,
                'bar' => self::GOLD,
                'title' => self::NAVY,
                'body' => self::TEXT_BODY,
                'meta' => '#8f6614',
                'link' => '#8f6614',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
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
            'cardRadius' => self::CARD_RADIUS,
            'sectionRadius' => self::SECTION_RADIUS,
            'zone' => self::ZONE,
            'textBody' => self::TEXT_BODY,
            'variants' => self::variants(),
        ];
    }
}

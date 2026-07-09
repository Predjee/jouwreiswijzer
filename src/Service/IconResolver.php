<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class IconResolver
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    public function getSvgIcon(string $type): string
    {
        if (!$this->isSafeIconName($type)) {
            return '';
        }

        $path = $this->projectDir.'/assets/images/icons/'.$type.'.svg';

        if (!\is_file($path) || false === $contents = \file_get_contents($path)) {
            return '';
        }

        $contents = \str_replace(
            ['currentColor', '#000000', '#000'],
            '#d4af37',
            $contents,
        );

        return \preg_replace(
            '/<svg\b/',
            '<svg class="travel-plan-icon" color="#d4af37"',
            $contents,
            1,
        ) ?? $contents;
    }

    public function getPdfIconDataUri(string $type): string
    {
        if (!$this->isSafeIconName($type)) {
            return '';
        }

        return $this->assetDataUri(
            'assets/images/pdf/icons/'.$type.'.png',
            'image/png',
        );
    }

    /**
     * PDF-icoon met een rond badge-randje, zoals de icon-badges in de
     * account-omgeving. De ring wordt in de PNG zelf gebakken omdat mPDF
     * geen betrouwbare afgeronde divs/floats in kaartkoppen rendert.
     */
    public function getPdfIconBadgeDataUri(string $type): string
    {
        if (!$this->isSafeIconName($type) || !\function_exists('imagecreatetruecolor')) {
            return $this->getPdfIconDataUri($type);
        }

        /** @var array<string, string> $cache */
        static $cache = [];

        if (isset($cache[$type])) {
            return $cache[$type];
        }

        $iconPath = $this->projectDir.'/assets/images/pdf/icons/'.$type.'.png';

        if (!\is_file($iconPath) || false === $icon = @\imagecreatefrompng($iconPath)) {
            return $this->getPdfIconDataUri($type);
        }

        $size = 256;
        $badge = \imagecreatetruecolor($size, $size);
        \imagealphablending($badge, false);
        \imagesavealpha($badge, true);
        $transparent = \imagecolorallocatealpha($badge, 0, 0, 0, 127);
        \imagefill($badge, 0, 0, $transparent);
        \imagealphablending($badge, true);

        // Gouden ring (#b99550), ca. 5px dik op 256px canvas.
        $gold = \imagecolorallocate($badge, 0xb9, 0x95, 0x50);
        $center = (int) ($size / 2);

        for ($diameter = $size - 4; $diameter >= $size - 12; --$diameter) {
            \imageellipse($badge, $center, $center, $diameter, $diameter, $gold);
        }

        // Icoon gecentreerd op ~55% van het canvas.
        $iconTarget = (int) ($size * 0.55);
        $offset = (int) (($size - $iconTarget) / 2);
        \imagecopyresampled(
            $badge,
            $icon,
            $offset,
            $offset,
            0,
            0,
            $iconTarget,
            $iconTarget,
            \imagesx($icon),
            \imagesy($icon),
        );
        \imagedestroy($icon);

        \ob_start();
        \imagepng($badge);
        $png = (string) \ob_get_clean();
        \imagedestroy($badge);

        if ('' === $png) {
            return $this->getPdfIconDataUri($type);
        }

        return $cache[$type] = 'data:image/png;base64,'.\base64_encode($png);
    }

    /**
     * Afgeronde boven-/onderrand ("cap") voor PDF-groepen, als PNG.
     * mPDF kan geen border-radius op tabellen tekenen; een afbeelding
     * rendert wél overal betrouwbaar. De cap sluit naadloos aan op de
     * groepstabel eronder/erboven (zelfde vulkleur, zijranden in de arc).
     */
    public function getPdfGroupCapDataUri(string $position, string $fillHex, string $borderHex = '#e3ddcd'): string
    {
        if (!\function_exists('imagecreatetruecolor')) {
            return '';
        }

        /** @var array<string, string> $cache */
        static $cache = [];
        $key = $position.'|'.$fillHex.'|'.$borderHex;

        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $width = 1600;
        $height = 40;
        $radius = 40;
        $borderWidth = 3;

        $img = \imagecreatetruecolor($width, $height);
        \imagealphablending($img, false);
        \imagesavealpha($img, true);
        $transparent = \imagecolorallocatealpha($img, 0, 0, 0, 127);
        \imagefill($img, 0, 0, $transparent);
        \imagealphablending($img, true);

        $rgb = static function (string $hex): array {
            $parsed = \sscanf($hex, '#%02x%02x%02x');

            return \is_array($parsed) ? $parsed : [0, 0, 0];
        };
        [$r, $g, $b] = $rgb($borderHex);
        $border = \imagecolorallocate($img, (int) $r, (int) $g, (int) $b);
        [$r, $g, $b] = $rgb($fillHex);
        $fill = \imagecolorallocate($img, (int) $r, (int) $g, (int) $b);

        // Randlaag (afgeronde bovenkant over de volle hoogte) …
        \imagefilledrectangle($img, $radius, 0, $width - $radius, $height, $border);
        \imagefilledellipse($img, $radius, $radius, 2 * $radius, 2 * $radius, $border);
        \imagefilledellipse($img, $width - $radius, $radius, 2 * $radius, 2 * $radius, $border);
        // … met daarbinnen de vulling.
        \imagefilledrectangle($img, $radius, $borderWidth, $width - $radius, $height, $fill);
        \imagefilledellipse($img, $radius, $radius, 2 * ($radius - $borderWidth), 2 * ($radius - $borderWidth), $fill);
        \imagefilledellipse($img, $width - $radius, $radius, 2 * ($radius - $borderWidth), 2 * ($radius - $borderWidth), $fill);

        if ('bottom' === $position) {
            \imageflip($img, \IMG_FLIP_VERTICAL);
        }

        \ob_start();
        \imagepng($img);
        $png = (string) \ob_get_clean();
        \imagedestroy($img);

        if ('' === $png) {
            return $cache[$key] = '';
        }

        return $cache[$key] = 'data:image/png;base64,'.\base64_encode($png);
    }

    private function isSafeIconName(string $type): bool
    {
        return 1 === \preg_match('/^[a-z0-9][a-z0-9-]*$/', $type);
    }

    private function assetDataUri(string $relativePath, string $mimeType): string
    {
        $path = $this->projectDir.'/'.$relativePath;

        if (!\is_file($path) || false === $contents = \file_get_contents($path)) {
            return '';
        }

        return 'data:'.$mimeType.';base64,'.\base64_encode($contents);
    }
}

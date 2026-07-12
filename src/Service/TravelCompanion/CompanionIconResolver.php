<?php

declare(strict_types=1);

namespace App\Service\TravelCompanion;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Lost CMS-icoonwaarden op naar iets dat de companion-app kan tonen:
 * een absolute/relatieve afbeeldings-URL blijft staan, een veilige
 * icoonnaam wordt als base64-SVG uit assets/images/icons geladen.
 */
final class CompanionIconResolver
{
    /** @var array<string, string> */
    private array $cache = [];

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function resolve(string $icon): string
    {
        $icon = \trim($icon);

        if ('' === $icon) {
            return '';
        }

        if (isset($this->cache[$icon])) {
            return $this->cache[$icon];
        }

        if (1 === \preg_match('/^(https?:\/\/|\/).+\.(svg|png|webp|jpg|jpeg)$/i', $icon)) {
            return $this->cache[$icon] = $icon;
        }

        if (1 !== \preg_match('/^[a-z0-9][a-z0-9-]*$/', $icon)) {
            return $this->cache[$icon] = '';
        }

        $path = $this->projectDir.'/assets/images/icons/'.$icon.'.svg';

        if (!\is_file($path) || false === $contents = \file_get_contents($path)) {
            return $this->cache[$icon] = '';
        }

        return $this->cache[$icon] = 'data:image/svg+xml;base64,'.\base64_encode($contents);
    }
}

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

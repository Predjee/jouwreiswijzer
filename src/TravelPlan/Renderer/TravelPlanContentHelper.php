<?php

declare(strict_types=1);

namespace App\TravelPlan\Renderer;

use Sulu\Bundle\MediaBundle\Api\Media;
use Sulu\Bundle\MediaBundle\Media\Exception\MediaNotFoundException;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Gedeelde contentlogica voor account- en PDF-rendering.
 *
 * Deze service bevat bewust geen markup: templates en PDF-specifieke services
 * bepalen hoe de voorbereide data wordt weergegeven.
 */
final readonly class TravelPlanContentHelper
{
    public function __construct(
        private MediaManagerInterface $mediaManager,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    public function isTruthy(mixed $value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        if (\is_int($value)) {
            return 1 === $value;
        }

        if (\is_string($value)) {
            return \in_array(\strtolower(\trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }

    public function tableOfContentsTitle(mixed $title): ?string
    {
        if (!\is_scalar($title)) {
            return null;
        }

        $title = \trim((string) $title);

        return '' === $title ? null : $title;
    }

    public function tableOfContentsDepth(mixed $value): int
    {
        if (\is_bool($value)) {
            return $value ? 2 : 0;
        }

        if (\is_int($value)) {
            return match ($value) {
                1 => 1,
                2 => 2,
                default => 0,
            };
        }

        if (!\is_string($value)) {
            return 0;
        }

        return match (\strtolower(\trim($value))) {
            'one', '1', 'destination', 'destinations', 'een laag' => 1,
            'two', '2', 'true', 'yes', 'on', 'twee lagen' => 2,
            default => 0,
        };
    }

    /**
     * @param array<string, mixed> $block
     *
     * @return array<string, mixed>
     */
    public function withTimeRange(array $block): array
    {
        $startTime = $this->normalizeTime($block['startTime'] ?? $block['time'] ?? null);
        $endTime = $this->normalizeTime($block['endTime'] ?? null);
        $block['startTime'] = $startTime;
        $block['endTime'] = $endTime;
        $block['timeRangeLabel'] = match (true) {
            '' === $startTime => '',
            '' === $endTime || $endTime === $startTime => $startTime,
            default => \sprintf('%s - %s', $startTime, $endTime),
        };

        return $block;
    }

    public function normalizeTime(mixed $time): string
    {
        if ($time instanceof \DateTimeInterface) {
            return $time->format('H:i');
        }

        if (!\is_scalar($time)) {
            return '';
        }

        $time = \trim((string) $time);

        if (1 !== \preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/D', $time, $matches)) {
            return '';
        }

        return \sprintf('%02d:%s', (int) $matches[1], $matches[2]);
    }

    public function mediaImageSrc(mixed $image, bool $pdfView): ?string
    {
        $media = $this->resolveMedia($image);

        if (null !== $media) {
            $storagePath = $this->mediaStoragePath($media);

            if ($pdfView && null !== $storagePath) {
                return $storagePath;
            }

            $format = $media->getFormats()['text-media-landscape'] ?? $media->getFormats()['package-card'] ?? null;

            if (\is_scalar($format)) {
                $formatUrl = (string) $format;
                $formatPath = $this->imagePathFromUrl($formatUrl);

                if ($pdfView && null !== $formatPath) {
                    return $formatPath;
                }

                return $formatUrl;
            }

            $url = $media->getUrl();

            if ('' !== $url) {
                $path = $this->imagePathFromUrl($url);

                if ($pdfView && null !== $path) {
                    return $path;
                }

                return $url;
            }
        }

        if (\is_array($image)) {
            $thumbnail = $image['thumbnails']['text-media-landscape']
                ?? $image['thumbnails']['package-card']
                ?? $image['thumbnails']['large']
                ?? $image['thumbnails']['default']
                ?? null;
            $url = \is_scalar($thumbnail) ? (string) $thumbnail : null;

            if (null === $url && \is_scalar($image['url'] ?? null)) {
                $url = (string) $image['url'];
            }

            if (null === $url && \is_scalar($image['path'] ?? null)) {
                $url = (string) $image['path'];
            }

            if (null !== $url) {
                $path = $this->imagePathFromUrl($url);

                if ($pdfView && null !== $path) {
                    return $path;
                }

                return $url;
            }
        }

        if (\is_scalar($image)) {
            $url = (string) $image;
            $path = $this->imagePathFromUrl($url);

            if ($pdfView && null !== $path) {
                return $path;
            }

            return $url;
        }

        return null;
    }

    public function assetDataUri(string $relativePath, string $mimeType): ?string
    {
        $path = $this->projectDir . '/' . $relativePath;

        if (!\is_file($path) || false === $contents = \file_get_contents($path)) {
            return null;
        }

        return 'data:' . $mimeType . ';base64,' . \base64_encode($contents);
    }

    private function resolveMedia(mixed $image): ?Media
    {
        $id = null;

        if (\is_array($image) && \is_scalar($image['id'] ?? null)) {
            $id = (int) $image['id'];
        } elseif (\is_scalar($image) && '' !== \trim((string) $image)) {
            $id = (int) $image;
        }

        if (null === $id || $id <= 0) {
            return null;
        }

        try {
            return $this->mediaManager->getById($id, 'nl');
        } catch (MediaNotFoundException) {
            return null;
        }
    }

    private function mediaStoragePath(Media $media): ?string
    {
        $storageOptions = $media->getStorageOptions();
        $segment = $storageOptions['segment'] ?? null;
        $fileName = $storageOptions['fileName'] ?? null;

        if (!\is_scalar($segment) || !\is_scalar($fileName)) {
            return null;
        }

        $path = $this->projectDir . '/var/storage/default/' . \trim((string) $segment, '/') . '/' . \ltrim((string) $fileName, '/');

        if (!\is_file($path)) {
            return null;
        }

        return $path;
    }

    private function imagePathFromUrl(string $url): ?string
    {
        $path = \parse_url($url, \PHP_URL_PATH);

        if (!\is_string($path) || '' === $path) {
            return null;
        }

        $localPath = $this->projectDir . '/public/' . \ltrim($path, '/');

        if (!\is_file($localPath)) {
            return null;
        }

        return $localPath;
    }
}

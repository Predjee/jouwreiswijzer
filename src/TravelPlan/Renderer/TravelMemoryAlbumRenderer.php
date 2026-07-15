<?php

declare(strict_types=1);

namespace App\TravelPlan\Renderer;

use Twig\Environment;

final readonly class TravelMemoryAlbumRenderer
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    /**
     * @param list<array{path?: string, url?: string, src?: string, caption?: string|null, capturedAt?: \DateTimeInterface|string|null}> $photos
     */
    public function render(string $albumTitle, ?string $albumIntro, array $photos): string
    {
        return $this->twig->render('travel_plan/web/memory_album.html.twig', [
            'albumTitle' => $albumTitle,
            'albumIntro' => $albumIntro,
            'photos' => $this->normalizePhotos($photos),
        ]);
    }

    /**
     * @param list<array{path?: string, url?: string, src?: string, caption?: string|null, capturedAt?: \DateTimeInterface|string|null}> $photos
     *
     * @return list<array{src: string, caption: string|null, capturedAt: string|null}>
     */
    private function normalizePhotos(array $photos): array
    {
        $normalizedPhotos = [];

        foreach ($photos as $photo) {
            $src = $photo['path'] ?? $photo['url'] ?? $photo['src'] ?? null;

            if (!\is_string($src) || '' === \trim($src)) {
                continue;
            }

            $caption = $photo['caption'] ?? null;
            $capturedAt = $photo['capturedAt'] ?? null;

            $normalizedPhotos[] = [
                'src' => \trim($src),
                'caption' => \is_string($caption) && '' !== \trim($caption) ? \trim($caption) : null,
                'capturedAt' => $this->formatCapturedAt($capturedAt),
            ];
        }

        return $normalizedPhotos;
    }

    private function formatCapturedAt(mixed $capturedAt): ?string
    {
        if ($capturedAt instanceof \DateTimeInterface) {
            return $capturedAt->format('d-m-Y');
        }

        if (!\is_string($capturedAt)) {
            return null;
        }

        $capturedAt = \trim($capturedAt);

        return '' !== $capturedAt ? $capturedAt : null;
    }
}

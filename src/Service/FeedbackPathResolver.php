<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\TravelPlan;
use App\TravelPlan\BlockPath;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class FeedbackPathResolver
{
    public function resolveBlockType(TravelPlan $travelPlan, ?string $blockPath): ?string
    {
        if (null === $blockPath) {
            return null;
        }

        $destinations = $travelPlan->getContent()['destinations'] ?? null;

        if (!\is_array($destinations)) {
            throw new BadRequestHttpException('Ongeldig reisplanonderdeel.');
        }

        $path = BlockPath::parse($blockPath);

        if (null === $path) {
            throw new BadRequestHttpException('Ongeldig reisplanonderdeel.');
        }

        $destination = $destinations[$path->destinationIndex] ?? null;

        if ($path->isDestination()) {
            if (\is_array($destination) && 'destination' === ($destination['type'] ?? null)) {
                return 'destination';
            }

            throw new BadRequestHttpException('Ongeldig reisplanonderdeel.');
        }

        $sections = \is_array($destination) && \is_array($destination['sections'] ?? null)
            ? $destination['sections']
            : [];
        $section = null !== $path->sectionIndex ? ($sections[$path->sectionIndex] ?? null) : null;

        if ($path->isSection()) {
            if (\is_array($section) && \is_string($section['type'] ?? null)) {
                return $section['type'];
            }

            throw new BadRequestHttpException('Ongeldig reisplanonderdeel.');
        }

        $blocks = \is_array($section) && \is_array($section['blocks'] ?? null)
            ? $section['blocks']
            : [];
        $block = null !== $path->blockIndex ? ($blocks[$path->blockIndex] ?? null) : null;

        if (
            \is_array($section)
            && 'day' === ($section['type'] ?? null)
            && \is_array($block)
            && \is_string($block['type'] ?? null)
        ) {
            return $block['type'];
        }

        throw new BadRequestHttpException('Ongeldig reisplanonderdeel.');
    }

    public function context(?string $blockPath): string
    {
        if (null === $blockPath) {
            return 'plan';
        }

        $path = BlockPath::parse($blockPath);

        return null !== $path && $path->isBlock() ? 'block' : 'section';
    }

    public function label(string $context): string
    {
        return match ($context) {
            'plan' => 'Feedback op dit reisplan',
            'block' => 'Feedback op dit dagonderdeel',
            default => 'Feedback op dit onderdeel',
        };
    }
}

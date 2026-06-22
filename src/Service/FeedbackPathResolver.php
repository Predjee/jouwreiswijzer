<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\TravelPlan;
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

        if (1 === \preg_match('/^destinations\[(\d+)]$/D', $blockPath, $matches)) {
            $destination = $destinations[(int) $matches[1]] ?? null;

            if (\is_array($destination) && 'destination' === ($destination['type'] ?? null)) {
                return 'destination';
            }
        }

        if (1 === \preg_match('/^destinations\[(\d+)]\.sections\[(\d+)]$/D', $blockPath, $matches)) {
            $destination = $destinations[(int) $matches[1]] ?? null;
            $section = \is_array($destination)
                ? ($destination['sections'][(int) $matches[2]] ?? null)
                : null;

            if (\is_array($section) && \is_string($section['type'] ?? null)) {
                return $section['type'];
            }
        }

        if (1 === \preg_match('/^destinations\[(\d+)]\.sections\[(\d+)]\.blocks\[(\d+)]$/D', $blockPath, $matches)) {
            $destination = $destinations[(int) $matches[1]] ?? null;
            $section = \is_array($destination)
                ? ($destination['sections'][(int) $matches[2]] ?? null)
                : null;
            $block = \is_array($section)
                ? ($section['blocks'][(int) $matches[3]] ?? null)
                : null;

            if (
                \is_array($section)
                && 'day' === ($section['type'] ?? null)
                && \is_array($block)
                && \is_string($block['type'] ?? null)
            ) {
                return $block['type'];
            }
        }

        throw new BadRequestHttpException('Ongeldig reisplanonderdeel.');
    }

    public function context(?string $blockPath): string
    {
        if (null === $blockPath) {
            return 'plan';
        }

        return \str_contains($blockPath, '.blocks[') ? 'block' : 'section';
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

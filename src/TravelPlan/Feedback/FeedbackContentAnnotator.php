<?php

declare(strict_types=1);

namespace App\TravelPlan\Feedback;

use App\Entity\TravelPlanFeedback;
use App\TravelPlan\BlockPath;

/**
 * Koppelt klantfeedback aan de content-array voor de admin-weergave:
 * hangt feedback via BlockPath aan het juiste blok (_feedback) en bouwt
 * leesbare doellabels ("Bestemming 2, dag 3: ...").
 */
final readonly class FeedbackContentAnnotator
{
    /**
     * Hangt de feedback als '_feedback' aan het blok waar het pad naar
     * wijst; feedback zonder pad wordt 'planFeedback' op topniveau.
     *
     * @param array<string, mixed> $data
     */
    public function attach(array &$data, TravelPlanFeedback $feedback): void
    {
        $blockPath = $feedback->getBlockPath();
        $serialized = [
            'id' => $feedback->getId(),
            'status' => $feedback->getStatus(),
            'message' => $feedback->getMessage(),
            'blockPath' => $blockPath,
            'blockType' => $feedback->getBlockType(),
            'contactName' => $feedback->getContact()->getFullName(),
            'createdAt' => $feedback->getCreatedAt()->format('d-m-Y H:i'),
        ];

        if (null === $blockPath) {
            $data['planFeedback'] ??= $serialized;

            return;
        }

        $path = BlockPath::parse($blockPath);

        if (null === $path || !\is_array($data['destinations'] ?? null)) {
            return;
        }

        $destinations = &$data['destinations'];

        if (!isset($destinations[$path->destinationIndex]) || !\is_array($destinations[$path->destinationIndex])) {
            return;
        }

        $destination = &$destinations[$path->destinationIndex];

        if ($path->isDestination()) {
            $destination['_feedback'] ??= $serialized;

            return;
        }

        if (null === $path->sectionIndex || !\is_array($destination['sections'] ?? null)) {
            return;
        }

        $sections = &$destination['sections'];

        if (!isset($sections[$path->sectionIndex]) || !\is_array($sections[$path->sectionIndex])) {
            return;
        }

        $section = &$sections[$path->sectionIndex];

        if ($path->isSection()) {
            $section['_feedback'] ??= $serialized;

            return;
        }

        if (null === $path->blockIndex || !\is_array($section['blocks'] ?? null)) {
            return;
        }

        $blocks = &$section['blocks'];

        if (isset($blocks[$path->blockIndex]) && \is_array($blocks[$path->blockIndex])) {
            $blocks[$path->blockIndex]['_feedback'] ??= $serialized;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function targetLabel(array $data, TravelPlanFeedback $feedback): string
    {
        $blockPath = $feedback->getBlockPath();

        if (null === $blockPath) {
            return 'Hele reisplan';
        }

        $path = BlockPath::parse($blockPath);
        $destinations = \is_array($data['destinations'] ?? null) ? $data['destinations'] : [];
        $destination = null !== $path ? ($destinations[$path->destinationIndex] ?? []) : [];

        if (null === $path || !\is_array($destination)) {
            return $feedback->getBlockType() ?? 'Reisplanonderdeel';
        }

        if ($path->isDestination()) {
            $title = $this->labelValue($destination['title'] ?? null);

            return \sprintf(
                'Bestemming %d: %s',
                $path->destinationIndex + 1,
                '' !== $title ? $title : 'Bestemming',
            );
        }

        $sections = \is_array($destination['sections'] ?? null) ? $destination['sections'] : [];
        $section = null !== $path->sectionIndex ? ($sections[$path->sectionIndex] ?? []) : [];

        if (!\is_array($section)) {
            return $feedback->getBlockType() ?? 'Reisplanonderdeel';
        }

        if ($path->isSection()) {
            $title = $this->labelValue($section['title'] ?? null);

            return \sprintf(
                'Bestemming %d, sectie %d: %s',
                $path->destinationIndex + 1,
                $path->sectionIndex + 1,
                '' !== $title ? $title : ($feedback->getBlockType() ?? 'Onderdeel'),
            );
        }

        $blocks = \is_array($section['blocks'] ?? null) ? $section['blocks'] : [];
        $block = null !== $path->blockIndex ? ($blocks[$path->blockIndex] ?? []) : [];
        $dayNumber = $this->labelValue($section['dayNumber'] ?? null);
        $dayLabel = '' !== $dayNumber
            ? \sprintf('dag %d', (int) $dayNumber)
            : \sprintf('sectie %d', $path->sectionIndex + 1);
        $title = \is_array($block) ? $this->labelValue($block['title'] ?? null) : '';

        return \sprintf(
            'Bestemming %d, %s: %s',
            $path->destinationIndex + 1,
            $dayLabel,
            '' !== $title ? $title : ($feedback->getBlockType() ?? 'Dagonderdeel'),
        );
    }

    private function labelValue(mixed $value): string
    {
        return \is_scalar($value) ? \trim((string) $value) : '';
    }
}

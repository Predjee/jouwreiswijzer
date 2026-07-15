<?php

declare(strict_types=1);

namespace App\TravelPlan;

final readonly class BlockPath
{
    private function __construct(
        public int $destinationIndex,
        public ?int $sectionIndex = null,
        public ?int $blockIndex = null,
    ) {
    }

    public static function destination(int $destinationIndex): self
    {
        return new self($destinationIndex);
    }

    public static function parse(string $path): ?self
    {
        if (1 === \preg_match('/^destinations\[(\d+)](?:\.sections\[(\d+)])?(?:\.blocks\[(\d+)])?$/D', $path, $matches)) {
            $sectionIndex = \array_key_exists(2, $matches) ? (int) $matches[2] : null;
            $blockIndex = \array_key_exists(3, $matches) ? (int) $matches[3] : null;

            if (null !== $blockIndex && null === $sectionIndex) {
                return null;
            }

            return new self((int) $matches[1], $sectionIndex, $blockIndex);
        }

        return null;
    }

    public function section(int $sectionIndex): self
    {
        return new self($this->destinationIndex, $sectionIndex);
    }

    public function block(int $blockIndex): self
    {
        if (null === $this->sectionIndex) {
            throw new \LogicException('A block path requires a section path.');
        }

        return new self($this->destinationIndex, $this->sectionIndex, $blockIndex);
    }

    public function isDestination(): bool
    {
        return null === $this->sectionIndex;
    }

    public function isSection(): bool
    {
        return null !== $this->sectionIndex && null === $this->blockIndex;
    }

    public function isBlock(): bool
    {
        return null !== $this->sectionIndex && null !== $this->blockIndex;
    }

    public function __toString(): string
    {
        $path = \sprintf('destinations[%d]', $this->destinationIndex);

        if (null !== $this->sectionIndex) {
            $path .= \sprintf('.sections[%d]', $this->sectionIndex);
        }

        if (null !== $this->blockIndex) {
            $path .= \sprintf('.blocks[%d]', $this->blockIndex);
        }

        return $path;
    }
}

<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\I18n;

/**
 * A hardcoded text together with where it would go: its language file and key,
 * or the reason it cannot be moved automatically.
 */
final class PlannedText
{
    public function __construct(
        public readonly HardcodedText $text,
        public readonly ?Target $target = null,
        public readonly ?string $key = null,
        public readonly ?string $reason = null,
        public readonly bool $reused = false,
    ) {
    }

    public function isExtractable(): bool
    {
        return $this->key !== null && $this->target !== null;
    }

    /**
     * Whether the key was already in the language file with the same text.
     */
    public function reusesKey(): bool
    {
        return $this->reused;
    }

    /**
     * Why it stays as it is: the finder's reason, or one found while planning.
     */
    public function blocker(): ?string
    {
        return $this->isExtractable() ? null : ($this->reason ?? $this->text->reason);
    }

    public function withReason(string $reason): self
    {
        return new self($this->text, $this->target, null, $reason);
    }
}

<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;
use YellowTwins\FluidLens\Support\Elements;

/**
 * A `<video>` should offer captions via a `<track kind="captions">` (or
 * subtitles) so it is usable without sound.
 *
 * WCAG 1.2.2 Captions (Prerecorded) (Level A).
 */
final class VideoCaptionsRule extends AbstractElementRule
{
    public function name(): string
    {
        return 'wcag.video-captions';
    }

    protected function inspect(Node $element, string $file): array
    {
        if ($element->name !== 'video' || $this->hasCaptionTrack($element)) {
            return [];
        }

        return [
            $this->finding(
                $element,
                Severity::Warning,
                '<video> has no <track kind="captions">.',
                $file,
                'WCAG 1.2.2 (A)',
            ),
        ];
    }

    private function hasCaptionTrack(Node $element): bool
    {
        foreach (Elements::all($element) as $descendant) {
            if (
                $descendant->name === 'track'
                && in_array($descendant->attribute('kind'), ['captions', 'subtitles'], true)
            ) {
                return true;
            }
        }

        return false;
    }
}

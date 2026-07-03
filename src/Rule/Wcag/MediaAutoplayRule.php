<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;

/**
 * Media that autoplays sound gives the user no chance to stop it. Audio that
 * autoplays, or video that autoplays without being muted, is flagged.
 *
 * WCAG 1.4.2 Audio Control (Level A).
 */
final class MediaAutoplayRule extends AbstractElementRule
{
    public function name(): string
    {
        return 'wcag.media-autoplay';
    }

    protected function inspect(Node $element, string $file): array
    {
        if (!$this->autoplaysSound($element)) {
            return [];
        }

        return [
            $this->finding(
                $element,
                Severity::Warning,
                sprintf('<%s> autoplays sound; provide controls or mute it.', $element->name),
                $file,
                'WCAG 1.4.2 (A)',
            ),
        ];
    }

    private function autoplaysSound(Node $element): bool
    {
        if ($element->attribute('autoplay') === null) {
            return false;
        }

        if ($element->name === 'audio') {
            return true;
        }

        return $element->name === 'video' && $element->attribute('muted') === null;
    }
}

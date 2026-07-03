<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;
use YellowTwins\FluidLens\Support\Attributes;

/**
 * Alt text that is just a file name ("hero.jpg") describes the file, not the
 * image, and helps nobody.
 *
 * WCAG 1.1.1 Non-text Content (Level A).
 */
final class AltFilenameRule extends AbstractElementRule
{
    private const FILENAME = '/\.(jpe?g|png|gif|svg|webp|avif|bmp|ico)$/i';

    public function name(): string
    {
        return 'wcag.alt-filename';
    }

    protected function inspect(Node $element, string $file): array
    {
        if ($element->name !== 'img' && $element->name !== 'f:image') {
            return [];
        }

        $alt = $element->attribute('alt');
        if ($alt === null || Attributes::isDynamic($alt) || preg_match(self::FILENAME, trim($alt)) !== 1) {
            return [];
        }

        return [
            $this->finding(
                $element,
                Severity::Warning,
                sprintf('alt="%s" looks like a file name; describe the image instead.', trim($alt)),
                $file,
                'WCAG 1.1.1 (A)',
            ),
        ];
    }
}

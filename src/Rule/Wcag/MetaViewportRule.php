<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;

/**
 * The viewport meta tag must not disable zoom: users with low vision rely on
 * pinch-zoom, which `user-scalable=no` or `maximum-scale=1` takes away.
 *
 * WCAG 1.4.4 Resize Text (Level AA).
 */
final class MetaViewportRule extends AbstractElementRule
{
    /**
     * @var list<string>
     */
    private const ZOOM_BLOCKERS = ['user-scalable=no', 'user-scalable=0', 'maximum-scale=1'];

    public function name(): string
    {
        return 'wcag.meta-viewport';
    }

    protected function inspect(Node $element, string $file): array
    {
        if ($element->name !== 'meta' || $element->attribute('name') !== 'viewport') {
            return [];
        }

        $content = str_replace(' ', '', strtolower($element->attribute('content') ?? ''));
        foreach (self::ZOOM_BLOCKERS as $blocker) {
            if (str_contains($content, $blocker)) {
                return [
                    $this->finding(
                        $element,
                        Severity::Warning,
                        'Viewport meta tag prevents zoom; drop user-scalable=no / maximum-scale=1.',
                        $file,
                        'WCAG 1.4.4 (AA)',
                    ),
                ];
            }
        }

        return [];
    }
}

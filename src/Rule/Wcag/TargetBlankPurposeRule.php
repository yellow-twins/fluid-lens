<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;
use YellowTwins\FluidLens\Support\Attributes;
use YellowTwins\FluidLens\Support\Text;

/**
 * Opening a link in a new tab is a change of context that should be announced,
 * so users (especially screen-reader users) are not disoriented. Advisory,
 * because the hint may live in an icon or CSS: reported only when there is no
 * title, no aria-label/labelledby, and no textual "new window/tab" cue.
 *
 * WCAG 3.2.5 Change on Request (Level AAA).
 */
final class TargetBlankPurposeRule extends AbstractElementRule
{
    /**
     * @var list<string>
     */
    private const CUES = ['new window', 'new tab', 'neues fenster', 'neuer tab', 'external', 'opens in'];

    public function name(): string
    {
        return 'wcag.target-blank-purpose';
    }

    protected function inspect(Node $element, string $file): array
    {
        if (!$this->isLink($element) || $element->attribute('target') !== '_blank') {
            return [];
        }

        if ($this->announcesNewWindow($element)) {
            return [];
        }

        return [
            $this->finding(
                $element,
                Severity::Notice,
                'Link opens a new tab without telling the user (target="_blank").',
                $file,
                'WCAG 3.2.5 (AAA)',
            ),
        ];
    }

    private function announcesNewWindow(Node $element): bool
    {
        if (
            Attributes::present($element, 'title')
            || Attributes::present($element, 'aria-label')
            || Attributes::present($element, 'aria-labelledby')
            || Attributes::present($element, 'aria-describedby')
        ) {
            return true;
        }

        $text = strtolower(Text::content($element));
        foreach (self::CUES as $cue) {
            if (str_contains($text, $cue)) {
                return true;
            }
        }

        return false;
    }

    private function isLink(Node $element): bool
    {
        return $element->name === 'a' || str_contains($element->name, 'link.');
    }
}

<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component\Draft;

/**
 * Renders the component call that replaces one occurrence.
 */
final class ExampleCallRenderer
{
    /**
     * @param array<string, string> $arguments argument name => value as written in the call
     */
    public function render(string $tag, array $arguments, string $slot = ''): string
    {
        $attributes = '';
        foreach ($arguments as $name => $value) {
            $attributes .= sprintf(' %s="%s"', $name, str_replace('"', '&quot;', $value));
        }

        if (trim($slot) === '') {
            return sprintf('<%s%s />', $tag, $attributes);
        }

        $body = implode("\n", array_map(
            static fn (string $line): string => '    ' . $line,
            explode("\n", trim($slot)),
        ));

        return sprintf("<%s%s>\n%s\n</%s>", $tag, $attributes, $body, $tag);
    }
}

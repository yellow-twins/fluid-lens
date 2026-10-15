<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component\Analysis;

/**
 * The local variables each copy names differently from the first copy, so their
 * references can be compared: a copy looping "as resident" reads
 * {resident.name} where the first copy, looping "as artist", reads {artist.name}.
 */
final class LocalRenames
{
    /** @var array<int, array<string, string>> copy => its name => the first copy's name */
    private array $renames = [];

    /**
     * @param array<int, string|null> $names the name each copy binds, copy 0 first
     */
    public function record(array $names): void
    {
        $first = $names[0] ?? null;
        foreach ($names as $copy => $name) {
            if ($first !== null && $name !== null && $name !== $first) {
                $this->renames[$copy][$name] = $first;
            }
        }
    }

    /**
     * The value as the first copy would write it.
     */
    public function apply(int $copy, string $value): string
    {
        foreach ($this->renames[$copy] ?? [] as $from => $to) {
            $value = (string) preg_replace(
                '/(?<![\w.$])' . preg_quote((string) $from, '/') . '(?=[.}\s,)\]]|$)/',
                $to,
                $value,
            );
        }

        return $value;
    }
}

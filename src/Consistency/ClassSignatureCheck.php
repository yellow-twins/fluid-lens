<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Consistency;

use YellowTwins\FluidLens\Parser\Node;

/**
 * A {@see SignatureCheck} that recognises libraries by their CSS classes only
 * (ignoring attribute names). Used where the signal is purely class-based —
 * slider libraries, icon sets, CSS frameworks.
 */
abstract class ClassSignatureCheck extends SignatureCheck
{
    /**
     * @return list<string>
     */
    protected function tokens(Node $element): array
    {
        return $this->classTokens($element);
    }
}

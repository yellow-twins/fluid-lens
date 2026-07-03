<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule;

use YellowTwins\FluidLens\Template\ParsedTemplate;

/**
 * A single lint rule (sniff). Each rule inspects one template and returns the
 * findings it detected.
 */
interface Rule
{
    /**
     * The stable identifier of the rule, for example {@code wcag.img-alt}.
     */
    public function name(): string;

    /**
     * @return list<Finding>
     */
    public function check(ParsedTemplate $template): array;
}

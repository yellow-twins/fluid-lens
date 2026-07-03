<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Consistency;

use YellowTwins\FluidLens\Template\ParsedTemplate;

/**
 * A single project-wide consistency check (sliders, icon sets, …).
 */
interface ConsistencyCheck
{
    /**
     * The stable identifier, for example {@code sliders}.
     */
    public function name(): string;

    /**
     * A human-readable heading, for example "Slider libraries".
     */
    public function title(): string;

    /**
     * @param list<ParsedTemplate> $templates
     */
    public function analyze(array $templates): ConsistencyResult;
}

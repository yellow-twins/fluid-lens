<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component\Model;

/**
 * Where a proposed argument comes from — explains the suggestion to the reader.
 */
enum ArgumentOrigin: string
{
    /** Declared with f:argument in the Partial already. */
    case Declared = 'declared';
    /** A variable the markup reads from its caller. */
    case Variable = 'variable';
    /** A TypoScript setting the markup reads implicitly ({settings.*}). */
    case Setting = 'setting';
    /** An attribute value that differs between the copies. */
    case Attribute = 'attribute';
    /** Class names that differ between the copies. */
    case ClassTokens = 'class';
    /** Text that differs between the copies. */
    case Text = 'text';
    /** An element only some copies contain. */
    case Presence = 'presence';
}

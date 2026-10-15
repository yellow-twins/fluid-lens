<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component\Model;

/**
 * A caveat attached to a suggestion: the conversion works, but needs this
 * change or check. The code is stable for tools, the message is for people.
 */
final class CandidateNote
{
    public const IMPLICIT_SETTINGS = 'implicit-settings';
    public const CALLERS_PASS_ALL = 'callers-pass-all';
    public const CALLERS_DISAGREE = 'callers-disagree';
    public const FORWARDS_ALL = 'forwards-all';
    public const PARTIAL_SECTIONS = 'partial-sections';
    public const TOO_CONFIGURABLE = 'too-configurable';
    public const DEFAULT_UNKNOWN = 'default-unknown';
    public const NO_COLLECTION = 'no-collection';

    public function __construct(
        public readonly string $code,
        public readonly string $message,
    ) {
    }
}

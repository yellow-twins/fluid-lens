<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Consistency\Check;

use YellowTwins\FluidLens\Consistency\SignatureCheck;

/**
 * Detects which cookie-consent / CMP solution the project embeds. Two of them
 * fighting over the same banner is a common copy-paste accident.
 */
final class CookieConsentCheck extends SignatureCheck
{
    public function name(): string
    {
        return 'cookie-consent';
    }

    public function title(): string
    {
        return 'Cookie consent';
    }

    protected function catalog(): array
    {
        return [
            'Cookiebot' => ['CybotCookiebotDialog', 'data-cookieconsent'],
            'OneTrust' => ['onetrust', 'optanon'],
            'Osano cookieconsent' => ['cc-window', 'cookieconsent'],
            'Klaro' => ['klaro'],
            'Borlabs Cookie' => ['BorlabsCookie', '_brlbs'],
        ];
    }
}

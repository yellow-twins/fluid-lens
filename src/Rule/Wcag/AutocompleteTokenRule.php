<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;
use YellowTwins\FluidLens\Support\Attributes;

/**
 * An `autocomplete` attribute has to use one of the field names from the HTML
 * autofill spec (optionally prefixed with `section-*`, an address type or a
 * contact type). A typo means the browser cannot help the user fill the field.
 *
 * WCAG 1.3.5 Identify Input Purpose (Level AA).
 */
final class AutocompleteTokenRule extends AbstractElementRule
{
    /**
     * @var list<string>
     */
    private const FIELD_NAMES = [
        'name', 'honorific-prefix', 'given-name', 'additional-name', 'family-name',
        'honorific-suffix', 'nickname', 'username', 'new-password', 'current-password',
        'one-time-code', 'organization-title', 'organization', 'street-address',
        'address-line1', 'address-line2', 'address-line3', 'address-level4',
        'address-level3', 'address-level2', 'address-level1', 'country', 'country-name',
        'postal-code', 'cc-name', 'cc-given-name', 'cc-additional-name', 'cc-family-name',
        'cc-number', 'cc-exp', 'cc-exp-month', 'cc-exp-year', 'cc-csc', 'cc-type',
        'transaction-currency', 'transaction-amount', 'language', 'bday', 'bday-day',
        'bday-month', 'bday-year', 'sex', 'url', 'photo', 'tel', 'tel-country-code',
        'tel-national', 'tel-area-code', 'tel-local', 'tel-extension', 'email', 'impp',
    ];

    /**
     * @var list<string>
     */
    private const MODIFIERS = ['shipping', 'billing', 'home', 'work', 'mobile', 'fax', 'pager'];

    public function name(): string
    {
        return 'wcag.autocomplete-token';
    }

    protected function inspect(Node $element, string $file): array
    {
        $value = $element->attribute('autocomplete');
        if ($value === null || Attributes::isDynamic($value)) {
            return [];
        }

        $normalized = strtolower(trim($value));
        if ($normalized === '' || $normalized === 'on' || $normalized === 'off') {
            return [];
        }

        if ($this->isValid($normalized)) {
            return [];
        }

        return [
            $this->finding(
                $element,
                Severity::Warning,
                sprintf('autocomplete="%s" is not a valid autofill token.', $value),
                $file,
                'WCAG 1.3.5 (AA)',
            ),
        ];
    }

    private function isValid(string $value): bool
    {
        $tokens = preg_split('/\s+/', $value) ?: [];
        // A trailing "webauthn" credential hint is allowed after the field name.
        if (end($tokens) === 'webauthn') {
            array_pop($tokens);
        }

        $field = array_pop($tokens);
        if ($field === null || !in_array($field, self::FIELD_NAMES, true)) {
            return false;
        }

        foreach ($tokens as $modifier) {
            if (!str_starts_with($modifier, 'section-') && !in_array($modifier, self::MODIFIERS, true)) {
                return false;
            }
        }

        return true;
    }
}

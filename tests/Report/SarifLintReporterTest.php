<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Report;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Report\SarifLintReporter;
use YellowTwins\FluidLens\Rule\Finding;
use YellowTwins\FluidLens\Rule\Severity;

final class SarifLintReporterTest extends TestCase
{
    public function testProducesValidSarifStructure(): void
    {
        $findings = [
            new Finding('wcag.img-alt', Severity::Error, 'No alt', 'a.html', 3, 'WCAG 1.1.1 (A)'),
            new Finding('style.inline', Severity::Notice, 'Inline style', 'a.html', 0),
        ];

        $sarif = json_decode((new SarifLintReporter())->render($findings), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('2.1.0', $sarif['version']);
        self::assertSame('fluid-lens', $sarif['runs'][0]['tool']['driver']['name']);

        $result = $sarif['runs'][0]['results'][0];
        self::assertSame('wcag.img-alt', $result['ruleId']);
        self::assertSame('error', $result['level']);
        self::assertSame('a.html', $result['locations'][0]['physicalLocation']['artifactLocation']['uri']);
        self::assertSame(3, $result['locations'][0]['physicalLocation']['region']['startLine']);

        // A notice maps to SARIF "note"; an unknown line is clamped to 1.
        self::assertSame('note', $sarif['runs'][0]['results'][1]['level']);
        self::assertSame(1, $sarif['runs'][0]['results'][1]['locations'][0]['physicalLocation']['region']['startLine']);
    }
}

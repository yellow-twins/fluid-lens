<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Rule;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Parser\TemplateParser;
use YellowTwins\FluidLens\Rule\Severity;
use YellowTwins\FluidLens\Rule\Wcag\LabelForRule;
use YellowTwins\FluidLens\Template\ParsedTemplate;

final class LabelForRuleTest extends TestCase
{
    public function testFlagsLabelPointingAtMissingId(): void
    {
        $findings = $this->check('<label for="email">Email</label><input type="text" id="name"/>');

        self::assertCount(1, $findings);
        self::assertSame(Severity::Notice, $findings[0]->severity);
    }

    public function testAcceptsLabelWithMatchingId(): void
    {
        self::assertSame([], $this->check('<label for="email">Email</label><input type="text" id="email"/>'));
    }

    public function testIgnoresDynamicForValues(): void
    {
        self::assertSame([], $this->check('<label for="{field.id}">Email</label><input type="text"/>'));
    }

    public function testSkipsTemplatesUsingFluidFormViewHelpers(): void
    {
        // f:form.* generate ids automatically, so the check would be unreliable.
        $source = '<f:form.textfield property="email" id="x"/><label for="email">Email</label>';

        self::assertSame([], $this->check($source));
    }

    /**
     * @return list<\YellowTwins\FluidLens\Rule\Finding>
     */
    private function check(string $source): array
    {
        $tree = (new TemplateParser())->parse($source);

        return (new LabelForRule())->check(new ParsedTemplate('form.html', $tree));
    }
}

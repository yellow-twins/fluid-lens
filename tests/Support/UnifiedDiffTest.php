<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Support;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Support\UnifiedDiff;

final class UnifiedDiffTest extends TestCase
{
    public function testRendersChangedLinesWithContext(): void
    {
        $before = "a\nb\nc\nd\ne\nf\ng\n";
        $after = "a\nb\nc\nD\ne\nf\ng\n";

        self::assertSame(
            "--- a/x.html\n+++ b/x.html\n@@ -2,5 +2,5 @@\n b\n c\n-d\n+D\n e\n f\n",
            (new UnifiedDiff())->render('x.html', $before, $after),
        );
    }

    public function testRendersANewFile(): void
    {
        self::assertSame(
            "--- /dev/null\n+++ b/new.xlf\n@@ -0,0 +1,2 @@\n+one\n+two\n",
            (new UnifiedDiff())->render('new.xlf', null, "one\ntwo\n"),
        );
    }

    public function testRendersAnInsertion(): void
    {
        self::assertSame(
            "--- a/l.xlf\n+++ b/l.xlf\n@@ -1,2 +1,3 @@\n a\n+b\n c\n",
            (new UnifiedDiff())->render('l.xlf', "a\nc\n", "a\nb\nc\n"),
        );
    }
}

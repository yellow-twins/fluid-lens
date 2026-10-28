<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\I18n;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\I18n\KeyGenerator;
use YellowTwins\FluidLens\I18n\Target;

final class KeyGeneratorTest extends TestCase
{
    public function testSlugIsLowerCamelCaseOfTheFirstWords(): void
    {
        $keys = new KeyGenerator();

        self::assertSame('keineBevorstehendenEventsMit', $keys->slug('Keine bevorstehenden Events mit Tickets.'));
        self::assertSame('groesseAendern', $keys->slug('Größe ändern'));
        self::assertSame('helloYou', $keys->slug('Hello %1$s, you'));
        self::assertSame('text2026Tickets', $keys->slug('2026 Tickets'));
        self::assertSame('text', $keys->slug('🎉'));
    }

    public function testReusesKeysAndResolvesCollisions(): void
    {
        $keys = new KeyGenerator();
        $target = new Target('/ext/locallang.xlf', 'ext', null, 'event.list');
        $keys->know('/ext/locallang.xlf', ['common.close' => 'Close', 'event.list.save' => 'Speichern']);

        self::assertSame(['common.close', false], $keys->keyFor($target, 'Close'));
        self::assertSame(['event.list.save.2', true], $keys->keyFor($target, 'Save'));
        self::assertSame(['event.list.save.2', false], $keys->keyFor($target, 'Save'));
        self::assertSame(['event.list.saveNow', true], $keys->keyFor($target, 'Save now'));
    }
}

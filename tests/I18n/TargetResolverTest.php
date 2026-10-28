<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\I18n;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\I18n\TargetResolver;

final class TargetResolverTest extends TestCase
{
    private TemporaryProject $project;

    protected function setUp(): void
    {
        $this->project = new TemporaryProject();
        $this->project->addExtension('packages/events', 'events');
    }

    protected function tearDown(): void
    {
        $this->project->remove();
    }

    public function testExtensionTemplatesUseTheExtensionsLocallang(): void
    {
        $template = $this->project->write('packages/events/Resources/Private/Partials/Event/List/Item.html', '');
        $target = (new TargetResolver())->resolve($template);

        self::assertNotNull($target);
        self::assertSame(
            $this->project->path('packages/events/Resources/Private/Language/locallang.xlf'),
            $target->file,
        );
        self::assertSame('event.list.item', $target->keyPrefix);
        self::assertSame(['key' => 'k', 'extensionName' => 'events'], $target->translateArguments('k'));
        self::assertSame(
            $this->project->path('packages/events/Resources/Private/Language/de.locallang.xlf'),
            $target->languageFile('de'),
        );
    }

    public function testContentBlocksUseTheirOwnLabels(): void
    {
        $template = $this->project->write(
            'packages/events/ContentBlocks/ContentElements/teaser/templates/frontend.html',
            '',
        );
        $target = (new TargetResolver())->resolve($template);

        self::assertNotNull($target);
        self::assertSame(
            ['key' => 'LLL:EXT:events/ContentBlocks/ContentElements/teaser/language/labels.xlf:frontend.x'],
            $target->translateArguments('frontend.x'),
        );
    }

    public function testOtherTemplatesNeedAConfiguredFile(): void
    {
        $template = $this->project->write('templates/Page.html', '');

        self::assertNull((new TargetResolver())->resolve($template));

        $fallback = $this->project->path('packages/events/Resources/Private/Language/site.xlf');
        $target = (new TargetResolver($fallback))->resolve($template);
        self::assertSame(
            ['key' => 'LLL:EXT:events/Resources/Private/Language/site.xlf:k'],
            $target?->translateArguments('k'),
        );
        self::assertSame('page', $target->keyPrefix);
    }

    public function testExtensionKeyFallsBackToTheDirectoryName(): void
    {
        $this->project->write('packages/my_site/ext_emconf.php', '<?php');
        $template = $this->project->write('packages/my_site/Resources/Private/Layouts/Default.html', '');

        self::assertSame('my_site', (new TargetResolver())->resolve($template)?->extensionName);
    }
}

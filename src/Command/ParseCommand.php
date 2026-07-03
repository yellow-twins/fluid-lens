<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Parser\NodeType;
use YellowTwins\FluidLens\Parser\TemplateParser;

/**
 * Debug command that parses a single Fluid template and prints its structural
 * node tree, either as a human-readable tree or as JSON for tooling.
 *
 * This is the foundation milestone: it proves the parser turns real templates
 * into the tree the clone detector will later operate on.
 */
#[AsCommand(
    name: 'parse',
    description: 'Parse a Fluid template and dump its structural node tree.',
)]
final class ParseCommand extends Command
{
    private const INDENT = '  ';

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Path to the Fluid template (.html).')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output the tree as JSON instead of a text tree.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = (string) $input->getArgument('file');

        if (!is_file($file)) {
            $io->error(sprintf('File not found: %s', $file));

            return Command::FAILURE;
        }

        $tree = (new TemplateParser())->parseFile($file);

        if ($input->getOption('json') === true) {
            $output->writeln($this->encodeJson($this->toArray($tree)));

            return Command::SUCCESS;
        }

        $io->title(sprintf('Fluid tree: %s', $file));
        foreach ($tree->children() as $child) {
            $this->renderNode($child, $output, '');
        }

        return Command::SUCCESS;
    }

    private function renderNode(Node $node, OutputInterface $output, string $prefix): void
    {
        $output->writeln($prefix . $this->label($node));
        foreach ($node->children() as $child) {
            $this->renderNode($child, $output, $prefix . self::INDENT);
        }
    }

    private function label(Node $node): string
    {
        return match ($node->type) {
            NodeType::Element => $this->elementLabel($node),
            NodeType::Text => sprintf('<fg=gray>"%s"</>', $this->preview($node->text)),
            NodeType::Comment => '<fg=gray># comment</>',
            NodeType::Root => 'root',
        };
    }

    private function elementLabel(Node $node): string
    {
        $classes = $node->attribute('class');
        $classSuffix = $classes !== null && $classes !== ''
            ? sprintf(' <fg=cyan>.%s</>', implode('.', preg_split('/\s+/', trim($classes)) ?: []))
            : '';

        $lineSuffix = $node->sourceRange !== null
            ? sprintf(' <fg=gray>:%d</>', $node->sourceRange->startLine)
            : '';

        return sprintf('<fg=yellow>&lt;%s&gt;</>%s%s', $node->name, $classSuffix, $lineSuffix);
    }

    private function preview(string $text): string
    {
        $collapsed = trim((string) preg_replace('/\s+/', ' ', $text));

        return mb_strlen($collapsed) > 50 ? mb_substr($collapsed, 0, 47) . '...' : $collapsed;
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(Node $node): array
    {
        $data = ['type' => $node->type->value];

        if ($node->type === NodeType::Element) {
            $data['name'] = $node->name;
            if ($node->attributes !== []) {
                $data['attributes'] = $node->attributes;
            }
        }

        if ($node->text !== '' && trim($node->text) !== '') {
            $data['text'] = trim((string) preg_replace('/\s+/', ' ', $node->text));
        }

        if ($node->sourceRange !== null) {
            $data['line'] = $node->sourceRange->startLine;
        }

        $children = array_map(fn (Node $child): array => $this->toArray($child), $node->children());
        if ($children !== []) {
            $data['children'] = $children;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function encodeJson(array $data): string
    {
        return json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }
}

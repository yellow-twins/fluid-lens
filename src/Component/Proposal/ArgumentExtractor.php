<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component\Proposal;

use YellowTwins\FluidLens\Component\Analysis\DiffPoint;
use YellowTwins\FluidLens\Component\Draft\Substitutions;
use YellowTwins\FluidLens\Component\Model\ArgumentOrigin;
use YellowTwins\FluidLens\Component\Model\ArgumentProposal;
use YellowTwins\FluidLens\Component\Model\ArgumentType;
use YellowTwins\FluidLens\Component\Model\CandidateNote;
use YellowTwins\FluidLens\Fluid\SourceCasing;
use YellowTwins\FluidLens\Parser\Node;

/**
 * Turns the differences between copies of the same markup into arguments.
 *
 * - An attribute value that differs becomes an argument named after the attribute.
 * - Class names that differ become a "variant" argument; the shared ones stay.
 * - Text that differs becomes "title", "label" or "text", by where it sits.
 * - When the copies read the same properties of differently named variables
 *   ({event.title} here, {artist.title} there), those collapse into a single
 *   object argument instead of one argument per property.
 */
final class ArgumentExtractor
{
    private const OBJECT_ARGUMENT = 'item';

    private const SINGLE_PATH = '/^\{([A-Za-z_]\w*)((?:\.[\w-]+)+)\}$/';

    private const HEADINGS = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];

    private const LABELLED = ['a', 'button', 'label', 'f:link.action', 'f:link.page', 'f:link.typolink'];

    public function __construct(
        private readonly ArgumentTypeInferrer $types = new ArgumentTypeInferrer(),
    ) {
    }

    /**
     * @param list<DiffPoint> $points
     * @param int             $copies   number of copies the values belong to
     * @param list<string>    $reserved names the markup already uses for its own variables
     */
    public function extract(array $points, int $copies, SourceCasing $casing, array $reserved = []): MarkupProposal
    {
        $namer = new ArgumentNamer();
        foreach ($reserved as $name) {
            $namer->claim($name);
        }
        $substitutions = new Substitutions();
        $objectName = null;
        $arguments = [];
        $callValues = array_fill(0, $copies, []);
        $caveats = [];

        $objectRoots = $this->sharedObjectRoots($points, $copies);
        if ($objectRoots !== null) {
            $objectName = $namer->claim(self::OBJECT_ARGUMENT);
            $arguments[] = new ArgumentProposal(
                $objectName,
                ArgumentType::Object,
                false,
                null,
                ArgumentOrigin::Variable,
                array_values(array_unique(array_map(static fn (string $r): string => '{' . $r . '}', $objectRoots))),
            );
            foreach ($objectRoots as $copy => $root) {
                $callValues[$copy][$objectName] = '{' . $root . '}';
            }
        }

        foreach ($points as $point) {
            if ($objectName !== null && $this->isObjectAccess($point)) {
                $this->renameRoot($point, $objectName, $substitutions);
                continue;
            }

            $argument = $this->argumentFor($point, $namer, $casing, $substitutions);
            $arguments[] = $argument;
            foreach ($this->callValuesFor($point) as $copy => $value) {
                if ($value !== null) {
                    $callValues[$copy][$argument->name] = $value;
                }
            }

            if ($point->isMissingSomewhere() && $point->kind === DiffPoint::ATTRIBUTE) {
                $caveats[] = new CandidateNote(
                    CandidateNote::DEFAULT_UNKNOWN,
                    sprintf(
                        'some copies have no %s on <%s>; check that an empty "%s" behaves like a missing attribute',
                        $argument->name,
                        $casing->restoreTag($point->node->name),
                        $argument->name,
                    ),
                );
            }
        }

        return new MarkupProposal($arguments, $substitutions, array_values($callValues), $caveats);
    }

    private function argumentFor(
        DiffPoint $point,
        ArgumentNamer $namer,
        SourceCasing $casing,
        Substitutions $substitutions,
    ): ArgumentProposal {
        $values = array_values(array_filter($this->callValuesFor($point), static fn (?string $v): bool => $v !== null));
        $optional = $point->isMissingSomewhere() || in_array('', $values, true);
        $name = $namer->claim($this->nameFor($point, $casing));

        $origin = match ($point->kind) {
            DiffPoint::CLASS_TOKENS => ArgumentOrigin::ClassTokens,
            DiffPoint::TEXT => ArgumentOrigin::Text,
            default => ArgumentOrigin::Attribute,
        };
        $type = match (true) {
            $origin === ArgumentOrigin::ClassTokens => ArgumentType::String,
            $point->attribute === 'each' => ArgumentType::Array,
            $point->attribute === 'condition' => ArgumentType::Bool,
            default => $this->types->infer($values),
        };

        $this->substitute($point, $name, $substitutions);

        return new ArgumentProposal(
            $name,
            $type,
            $optional,
            $optional && $type === ArgumentType::String ? '' : null,
            $origin,
            array_slice(array_values(array_unique($values)), 0, 4),
        );
    }

    private function substitute(DiffPoint $point, string $name, Substitutions $substitutions): void
    {
        match ($point->kind) {
            DiffPoint::TEXT => $this->substituteText($point->node, $name, $substitutions),
            DiffPoint::CLASS_TOKENS => $substitutions->setAttribute(
                $point->node,
                'class',
                trim(implode(' ', $this->sharedClasses($point)) . ' {' . $name . '}'),
            ),
            default => $substitutions->setAttribute($point->node, (string) $point->attribute, '{' . $name . '}'),
        };
    }

    private function substituteText(Node $text, string $name, Substitutions $substitutions): void
    {
        // Keep the spacing around the text, which may separate it from its siblings.
        $leading = preg_match('/^\s/', $text->text) === 1 ? ' ' : '';
        $trailing = preg_match('/\s$/', $text->text) === 1 ? ' ' : '';
        $substitutions->setText($text, $leading . '{' . $name . '}' . $trailing);
    }

    /**
     * What each copy passes for the point: the differing class names only, or
     * the whole value.
     *
     * @return list<string|null>
     */
    private function callValuesFor(DiffPoint $point): array
    {
        if ($point->kind !== DiffPoint::CLASS_TOKENS) {
            return $point->values;
        }

        $shared = $this->sharedClasses($point);

        return array_map(
            fn (?string $value): string => implode(' ', array_values(array_diff($this->tokens($value), $shared))),
            $point->values,
        );
    }

    private function nameFor(DiffPoint $point, SourceCasing $casing): string
    {
        if ($point->kind === DiffPoint::CLASS_TOKENS) {
            return $this->sharedClasses($point) === [] ? 'class' : 'variant';
        }

        if ($point->kind === DiffPoint::ATTRIBUTE) {
            return $point->attribute === 'each'
                ? 'items'
                : self::camel($casing->restoreAttribute((string) $point->attribute));
        }

        $sharedLeaf = $this->sharedLeaf($point->values);
        if ($sharedLeaf !== null) {
            return $sharedLeaf;
        }

        $parent = $point->node->parent()?->name ?? '';

        return match (true) {
            in_array($parent, self::HEADINGS, true) => 'title',
            in_array($parent, self::LABELLED, true) => 'label',
            default => 'text',
        };
    }

    /**
     * When every copy outputs a property with the same name ({a.title},
     * {b.title}), that name describes the value best.
     *
     * @param list<string|null> $values
     */
    private function sharedLeaf(array $values): ?string
    {
        $leaves = [];
        foreach ($values as $value) {
            if ($value === null || preg_match('/^\{[\w.]+\.(\w+)\}$/', trim($value), $match) !== 1) {
                return null;
            }
            $leaves[$match[1]] = true;
        }

        return count($leaves) === 1 ? (string) array_key_first($leaves) : null;
    }

    /**
     * The variable each copy reads its properties from, when all differences
     * that read a property path do so from one variable per copy and the same
     * properties in every copy. Null when there is no such pattern.
     *
     * @param list<DiffPoint> $points
     *
     * @return array<int, string>|null copy index => root variable
     */
    private function sharedObjectRoots(array $points, int $copies): ?array
    {
        $roots = null;
        foreach ($points as $point) {
            if (!$this->isObjectAccess($point)) {
                continue;
            }

            $paths = [];
            $current = [];
            foreach ($point->values as $copy => $value) {
                preg_match(self::SINGLE_PATH, trim((string) $value), $match);
                $current[$copy] = $match[1] ?? '';
                $paths[$match[2] ?? ''] = true;
            }

            if (count($paths) !== 1 || ($roots !== null && $roots !== $current)) {
                return null;
            }
            $roots = $current;
        }

        if ($roots === null || count($roots) !== $copies || count(array_unique($roots)) < 2) {
            return null;
        }

        return $roots;
    }

    private function isObjectAccess(DiffPoint $point): bool
    {
        if ($point->kind === DiffPoint::CLASS_TOKENS) {
            return false;
        }

        foreach ($point->values as $value) {
            if ($value === null || preg_match(self::SINGLE_PATH, trim($value)) !== 1) {
                return false;
            }
        }

        return true;
    }

    private function renameRoot(DiffPoint $point, string $name, Substitutions $substitutions): void
    {
        $value = (string) preg_replace('/^\{[A-Za-z_]\w*/', '{' . $name, trim((string) $point->values[0]));
        if ($point->kind === DiffPoint::TEXT) {
            $substitutions->setText($point->node, $value);
        } else {
            $substitutions->setAttribute($point->node, (string) $point->attribute, $value);
        }
    }

    /**
     * Class names every copy has, in the order of the first copy.
     *
     * @return list<string>
     */
    private function sharedClasses(DiffPoint $point): array
    {
        $shared = $this->tokens($point->values[0] ?? null);
        foreach ($point->values as $value) {
            $shared = array_values(array_intersect($shared, $this->tokens($value)));
        }

        return $shared;
    }

    /**
     * @return list<string>
     */
    private function tokens(?string $class): array
    {
        if ($class === null || trim($class) === '') {
            return [];
        }

        return preg_split('/\s+/', trim($class)) ?: [];
    }

    private static function camel(string $attribute): string
    {
        $parts = preg_split('/[^A-Za-z0-9]+/', $attribute) ?: [$attribute];
        $first = (string) array_shift($parts);

        return $first . implode('', array_map(static fn (string $part): string => ucfirst($part), $parts));
    }
}

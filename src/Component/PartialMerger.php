<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component;

use YellowTwins\FluidLens\Component\Index\PartialIndex;
use YellowTwins\FluidLens\Component\Model\CandidateScore;
use YellowTwins\FluidLens\Component\Model\ComponentCandidate;
use YellowTwins\FluidLens\Component\Model\PartialUsage;
use YellowTwins\FluidLens\Detector\CloneOccurrence;
use YellowTwins\FluidLens\Fluid\RenderCall;

/**
 * Recognises copied markup that is the entire content of several Partials —
 * the same frame kept in separate files, such as one teaser header per
 * extension. Such a suggestion merges those Partials into one component, and
 * its weight is how often they are rendered, not how many files they are.
 */
final class PartialMerger
{
    /**
     * @param array<string, non-empty-list<RenderCall>> $callsByFile render calls by Partial file
     */
    public function __construct(
        private readonly PartialIndex $partials,
        private readonly array $callsByFile,
    ) {
    }

    /**
     * @param non-empty-list<CloneOccurrence> $occurrences
     */
    public function merge(
        ComponentCandidate $candidate,
        array $occurrences,
        CandidateScorer $scorer,
    ): ComponentCandidate {
        $names = [];
        $files = [];
        foreach ($occurrences as $occurrence) {
            $partial = $this->partials->byFile($occurrence->file);
            $body = $partial === null ? [] : PartialCandidateFactory::bodyElements($partial->template->tree);
            if ($partial === null || count($body) !== 1 || $body[0] !== $occurrence->node) {
                return $candidate;
            }

            $names[$partial->name] = true;
            $files[] = $partial->file();
        }

        $files = array_values(array_unique($files));
        if (count($files) < 2) {
            return $candidate;
        }

        $names = array_map('strval', array_keys($names));
        sort($names);
        sort($files);

        $calls = [];
        foreach ($files as $file) {
            array_push($calls, ...($this->callsByFile[$file] ?? []));
        }
        $callers = count(array_unique(array_map(static fn (RenderCall $call): string => $call->file, $calls)));

        $reasons = [
            sprintf(
                'the Partials %s hold the same frame — merge them into one component',
                implode(', ', array_map(static fn (string $file): string => self::shortName($file), $files)),
            ),
            sprintf(
                'they are rendered %d× from %d file(s); replace each Partial\'s content with its call below,'
                    . ' or inline the call at the render calls',
                count($calls),
                $callers,
            ),
            ...$candidate->reasons,
        ];

        return $candidate->rebrand(
            'merge:' . implode(',', $files),
            $reasons,
            new PartialUsage($names, $files),
            $this->score($candidate, count($calls), $callers, $scorer),
        );
    }

    private function score(
        ComponentCandidate $candidate,
        int $calls,
        int $callers,
        CandidateScorer $scorer,
    ): CandidateScore {
        if ($calls <= $candidate->callSites()) {
            return $candidate->score;
        }

        $rescored = $scorer->score(
            $calls,
            $candidate->elementCount,
            max($callers, $candidate->fileCount()),
            count($candidate->arguments),
            array_diff_key($candidate->score->factors, array_flip(['call sites', 'size', 'spread', 'arguments'])),
        );

        return $rescored->value > $candidate->score->value ? $rescored : $candidate->score;
    }

    /**
     * "events/…/Partials/Event/Teaser/Header.html" as "Event/Teaser/Header (events)".
     */
    private static function shortName(string $file): string
    {
        $path = str_replace('\\', '/', $file);
        $position = strrpos($path, '/Partials/');
        if ($position === false) {
            return $path;
        }

        $name = (string) preg_replace('/(\.fluid)?\.html$/', '', substr($path, $position + strlen('/Partials/')));
        $extension = preg_match('#([^/]+)/Resources/Private/Partials/#', $path, $match) === 1 ? $match[1] : '';

        return $extension === '' ? $name : sprintf('%s (%s)', $name, $extension);
    }
}

<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\I18n;

/**
 * Writes the file changes of an {@see ExtractionPlan} to disk.
 */
final class ExtractionWriter
{
    /**
     * @param list<FileChange> $changes
     *
     * @return list<string> the paths that could not be written
     */
    public function write(array $changes): array
    {
        $failed = [];
        foreach ($changes as $change) {
            $directory = dirname($change->path);
            if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
                $failed[] = $change->path;
                continue;
            }
            if (@file_put_contents($change->path, $change->after) === false) {
                $failed[] = $change->path;
            }
        }

        return $failed;
    }
}

<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component\Proposal;

use YellowTwins\FluidLens\Component\Draft\Substitutions;
use YellowTwins\FluidLens\Component\Model\ArgumentOrigin;
use YellowTwins\FluidLens\Component\Model\ArgumentProposal;
use YellowTwins\FluidLens\Component\Model\ArgumentType;
use YellowTwins\FluidLens\Fluid\VariableUsage;

/**
 * Turns the TypoScript settings markup reads implicitly into explicit
 * arguments, because a component only sees what is passed to it.
 *
 * When the whole {settings} array is used, it is passed on as one argument;
 * otherwise every setting read becomes an argument of its own and is renamed in
 * the markup ({settings.listPageUid} becomes {listPageUid}).
 */
final class SettingsArguments
{
    /**
     * @return array<string, ArgumentProposal> argument name => proposal; the first observed
     *                                         value is what a caller passes
     */
    public function propose(VariableUsage $usage, ArgumentNamer $namer, Substitutions $substitutions): array
    {
        if (in_array('settings', $usage->paths, true)) {
            $name = $namer->claim('settings');

            return [$name => new ArgumentProposal(
                $name,
                ArgumentType::Array,
                false,
                null,
                ArgumentOrigin::Setting,
                ['{settings}'],
            )];
        }

        $arguments = [];
        foreach ($usage->paths as $path) {
            $name = $namer->forPath($path);
            $substitutions->renameVariable($path, $name);
            $arguments[$name] = new ArgumentProposal(
                $name,
                ArgumentType::Mixed,
                false,
                null,
                ArgumentOrigin::Setting,
                ['{' . $path . '}'],
            );
        }

        return $arguments;
    }
}

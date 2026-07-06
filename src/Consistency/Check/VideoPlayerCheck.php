<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Consistency\Check;

use YellowTwins\FluidLens\Consistency\SignatureCheck;

/**
 * Detects which video-player library skins the project's videos. Each brings its
 * own CSS and controls, so mixing them fragments the player UI.
 */
final class VideoPlayerCheck extends SignatureCheck
{
    public function name(): string
    {
        return 'video-player';
    }

    public function title(): string
    {
        return 'Video players';
    }

    protected function catalog(): array
    {
        return [
            'Plyr' => ['plyr'],
            'Video.js' => ['video-js', 'vjs'],
            'MediaElement.js' => ['mejs', 'mediaelementplayer'],
            'JW Player' => ['jwplayer'],
            'Flowplayer' => ['flowplayer'],
        ];
    }
}

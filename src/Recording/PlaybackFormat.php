<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Recording;

/** One playback format (presentation, video, transcript, ...) of a recording. */
final readonly class PlaybackFormat
{
    public function __construct(public string $type, public string $url)
    {
    }

    public function withUrl(string $url): self
    {
        return new self($this->type, $url);
    }
}

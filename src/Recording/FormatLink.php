<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Recording;

/**
 * One playback format button. A click opens $viewerUrl (Gibbon page with the playback iframe);
 * $href is what "Save link as" downloads: the media file when there is one, otherwise the viewer page.
 */
final readonly class FormatLink
{
    public function __construct(
        public string $type,
        public string $label,
        public string $href,
        public string $viewerUrl,
        public string $playbackUrl,
        public bool $downloadable,
    ) {
    }
}

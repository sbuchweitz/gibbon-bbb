<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Recording;

/** Derives a direct download URL from a playback format, when one exists. */
final class DownloadUrlResolver
{
    public function downloadUrl(PlaybackFormat $format): ?string
    {
        if (PlaybackUrlRewriter::origin($format->url) === null) {
            return null;
        }
        if ($format->type === 'video') {
            $base = substr($format->url, 0, strcspn($format->url, '?#'));

            return rtrim($base, '/') . '/video-0.m4v';
        }
        if ($format->type === 'transcript' || $this->isTextFile($format->url)) {
            return $format->url;
        }

        return null;
    }

    private function isTextFile(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && strtolower(substr($path, -4)) === '.txt';
    }
}

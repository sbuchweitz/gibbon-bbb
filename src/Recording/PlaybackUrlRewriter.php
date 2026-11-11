<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Recording;

/** Replaces the scheme/host/port of playback URLs with a configured origin and validates them. */
final class PlaybackUrlRewriter
{
    private ?string $origin;

    /** @param ?string $overrideOrigin normalised scheme://host[:port] without trailing slash, or null */
    public function __construct(?string $overrideOrigin)
    {
        if ($overrideOrigin === null) {
            $this->origin = null;

            return;
        }
        $normalised = self::origin($overrideOrigin);
        if ($normalised === null || strlen($normalised) !== strlen($overrideOrigin)) {
            throw new \InvalidArgumentException('Override origin must be scheme://host[:port] without a path');
        }
        $this->origin = $normalised;
    }

    public function rewrite(string $url): ?string
    {
        if (self::origin($url) === null) {
            return null;
        }
        if ($this->origin === null) {
            return $url;
        }

        return $this->origin . self::afterAuthority($url);
    }

    /** scheme://host[:port] of a valid absolute http(s) URL, otherwise null. */
    public static function origin(string $url): ?string
    {
        if ($url === '' || preg_match('/[\x00-\x20\x7f\\\\]/', $url) === 1) {
            return null;
        }
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host']) || $parts['host'] === '') {
            return null;
        }
        $scheme = strtolower($parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return null;
        }
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return $scheme . '://' . strtolower($parts['host']) . $port;
    }

    /** Path, query and fragment exactly as written (everything after the authority). */
    private static function afterAuthority(string $url): string
    {
        $rest = substr($url, (int) strpos($url, '://') + 3);
        $cut = strcspn($rest, '/?#');

        return substr($rest, $cut);
    }
}

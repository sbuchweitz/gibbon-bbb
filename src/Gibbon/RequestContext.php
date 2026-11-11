<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Gibbon;

/**
 * What the module needs to know about the current Gibbon request, extracted once from Gibbon's globals
 * so the rest of the code never touches the session directly.
 */
final readonly class RequestContext
{
    public function __construct(
        public int $personID,
        public string $displayName,
        /** getHighestGroupedAction() for /modules/Planner/planner_view_full.php, or '' */
        public string $highestAction,
        public string $absoluteURL,
        public string $guid,
        public \DateTimeZone $timezone,
        public bool $canManageSettings,
    ) {
    }

    public function moduleUrl(string $path = ''): string
    {
        return rtrim($this->absoluteURL, '/').'/modules/BigBlueButton/'.ltrim($path, '/');
    }

    public function serverName(): string
    {
        return (string) (parse_url($this->absoluteURL, PHP_URL_HOST) ?: 'gibbon');
    }

    /** Parses ?search= (a child's gibbonPersonID, zero-padded) as used by planner_view_full.php. */
    public static function childID(mixed $search): ?int
    {
        return is_string($search) && ctype_digit($search) && (int) $search > 0 ? (int) $search : null;
    }

    /** Parses a positive integer ID from a request parameter. */
    public static function id(mixed $value): int
    {
        return is_string($value) && ctype_digit($value) ? (int) $value : (is_int($value) && $value > 0 ? $value : 0);
    }
}

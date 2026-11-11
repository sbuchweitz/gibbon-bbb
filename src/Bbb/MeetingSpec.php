<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Bbb;

/**
 * What the module asks BigBlueButton to create for a lesson.
 */
final readonly class MeetingSpec
{
    /**
     * @param array<string, string> $metadata sent as meta_<key>; BBB lowercases the keys
     */
    public function __construct(
        public string $meetingID,
        public string $name,
        public int $durationMinutes,
        public string $logoutUrl,
        public bool $allowRequestsWithoutSession,
        public array $metadata,
    ) {
    }
}

<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Recording;

final readonly class RecordingView
{
    /** @param list<FormatLink> $links */
    public function __construct(
        public string $recordID,
        public int $number,
        public \DateTimeImmutable $startedAt,
        public int $durationMinutes,
        public array $links,
    ) {
    }
}

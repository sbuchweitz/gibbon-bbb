<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Recording;

/** How long a getRecordings result may be cached. */
final class CacheTtlPolicy
{
    public const FAILED = 30;
    public const HAS_RECORDINGS = 300;
    public const PENDING = 60;
    public const IDLE = 900;

    private const PENDING_WINDOW = '+6 hours';

    public function ttlSeconds(
        bool $hasRecordings,
        bool $failed,
        \DateTimeImmutable $lessonEnd,
        \DateTimeImmutable $now
    ): int {
        if ($failed) {
            return self::FAILED;
        }
        if ($hasRecordings) {
            return self::HAS_RECORDINGS;
        }

        return $now <= $lessonEnd->modify(self::PENDING_WINDOW) ? self::PENDING : self::IDLE;
    }
}

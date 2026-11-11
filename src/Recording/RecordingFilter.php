<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Recording;

/** Selects the recordings that belong to a lesson. */
final class RecordingFilter
{
    /**
     * @param list<Recording> $recordings
     * @param list<string> $meetingIDs
     * @return list<Recording>
     */
    public function forLesson(array $recordings, array $meetingIDs, string $instanceHash): array
    {
        $ids = array_map('strval', $meetingIDs);
        $kept = array_values(array_filter(
            $recordings,
            static function (Recording $r) use ($ids, $instanceHash): bool {
                $instance = $r->meta('gibbon-instance');

                return $r->published
                    && in_array($r->meetingID, $ids, true)
                    && ($instance === null || $instance === $instanceHash);
            }
        ));
        usort($kept, static fn (Recording $a, Recording $b): int => $a->startTime <=> $b->startTime);

        return $kept;
    }
}

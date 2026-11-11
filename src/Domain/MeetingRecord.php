<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Domain;

/**
 * A lesson's permanent link to its BigBlueButton meeting (table bigBlueButtonMeeting).
 */
final readonly class MeetingRecord
{
    public function __construct(
        public int $plannerEntryID,
        public string $meetingID,
        public ?string $legacyMeetingID,
        public \DateTimeImmutable $createdAt,
    ) {
    }

    /** @return list<string> every meeting ID whose recordings belong to this lesson */
    public function meetingIDs(): array
    {
        return $this->legacyMeetingID === null ? [$this->meetingID] : [$this->meetingID, $this->legacyMeetingID];
    }
}

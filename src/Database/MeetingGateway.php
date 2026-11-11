<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Database;

use Gibbon\Module\BigBlueButton\Domain\MeetingRecord;

final class MeetingGateway
{
    private const DATETIME = 'Y-m-d H:i:s';

    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function findByLesson(int $plannerEntryID): ?MeetingRecord
    {
        $statement = $this->pdo->prepare(
            'SELECT gibbonPlannerEntryID, meetingID, legacyMeetingID, timestampCreated
             FROM bigBlueButtonMeeting WHERE gibbonPlannerEntryID=:id'
        );
        $statement->execute(['id' => $plannerEntryID]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? self::hydrate($row) : null;
    }

    /**
     * Stores the lesson's meeting ID unless one exists already, and returns whichever row won.
     * Safe when two moderators open the class at the same moment (unique key on gibbonPlannerEntryID).
     */
    public function claim(int $plannerEntryID, string $meetingID, ?string $legacyMeetingID, int $creatorID, \DateTimeImmutable $now): MeetingRecord
    {
        $statement = $this->pdo->prepare(
            'INSERT IGNORE INTO bigBlueButtonMeeting (gibbonPlannerEntryID, meetingID, legacyMeetingID, gibbonPersonIDCreator, timestampCreated)
             VALUES (:id, :meetingID, :legacyMeetingID, :creator, :created)'
        );
        $statement->execute([
            'id' => $plannerEntryID,
            'meetingID' => $meetingID,
            'legacyMeetingID' => $legacyMeetingID,
            'creator' => $creatorID > 0 ? $creatorID : null,
            'created' => $now->setTimezone(new \DateTimeZone('UTC'))->format(self::DATETIME),
        ]);

        return $this->findByLesson($plannerEntryID)
            ?? throw new \RuntimeException('Could not store the meeting for lesson '.$plannerEntryID);
    }

    /** @param array<string, mixed> $row */
    private static function hydrate(array $row): MeetingRecord
    {
        $created = \DateTimeImmutable::createFromFormat(self::DATETIME, (string) $row['timestampCreated'], new \DateTimeZone('UTC'));

        return new MeetingRecord(
            (int) $row['gibbonPlannerEntryID'],
            (string) $row['meetingID'],
            $row['legacyMeetingID'] === null ? null : (string) $row['legacyMeetingID'],
            $created === false ? new \DateTimeImmutable('@0') : $created,
        );
    }
}

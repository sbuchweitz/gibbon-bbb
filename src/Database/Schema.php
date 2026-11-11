<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 *
 * Loaded with require_once by manifest.php and CHANGEDB.php (no autoloader there), so keep it
 * dependency-free and parseable by every PHP version Gibbon supports.
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Database;

final class Schema
{
    /** One row per lesson that has had a meeting: keeps the BigBlueButton meeting ID stable forever. */
    public const MEETING_TABLE = "CREATE TABLE `bigBlueButtonMeeting` (
        `bigBlueButtonMeetingID` INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
        `gibbonPlannerEntryID` INT(14) UNSIGNED ZEROFILL NOT NULL,
        `meetingID` VARCHAR(64) NOT NULL,
        `legacyMeetingID` VARCHAR(32) NULL DEFAULT NULL,
        `gibbonPersonIDCreator` INT(10) UNSIGNED ZEROFILL NULL DEFAULT NULL,
        `timestampCreated` DATETIME NOT NULL,
        PRIMARY KEY (`bigBlueButtonMeetingID`),
        UNIQUE KEY `plannerEntry` (`gibbonPlannerEntryID`),
        UNIQUE KEY `meetingID` (`meetingID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    /** Short-lived cache of getRecordings results per lesson, so lesson views don't call BBB every time. */
    public const RECORDING_CACHE_TABLE = "CREATE TABLE `bigBlueButtonRecordingCache` (
        `gibbonPlannerEntryID` INT(14) UNSIGNED ZEROFILL NOT NULL,
        `payload` MEDIUMTEXT NOT NULL,
        `timestampFetched` DATETIME NOT NULL,
        PRIMARY KEY (`gibbonPlannerEntryID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    /**
     * Plain "CREATE TABLE `name`" for manifest.php: core's uninstaller reads the table name as the third word.
     *
     * @return list<string>
     */
    public static function tables(): array
    {
        return [self::MEETING_TABLE, self::RECORDING_CACHE_TABLE];
    }

    /**
     * Re-runnable variants for CHANGEDB.php and tests.
     *
     * @return list<string>
     */
    public static function tablesIfNotExists(): array
    {
        return array_map(static function (string $sql): string {
            return str_replace('CREATE TABLE `', 'CREATE TABLE IF NOT EXISTS `', $sql);
        }, self::tables());
    }
}

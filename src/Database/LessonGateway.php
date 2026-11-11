<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Database;

use Gibbon\Module\BigBlueButton\Domain\Lesson;

/**
 * Read-only queries against Gibbon core tables (lessons, class enrolment, planner guests, families).
 */
final class LessonGateway
{
    public const VIDEO_CHAT_FIELD = 'Video Chat';
    public const PRESENTATION_ONLY_FIELD = 'Presentation Only';

    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function lesson(int $plannerEntryID): ?Lesson
    {
        $row = $this->one(
            'SELECT gibbonPlannerEntry.gibbonPlannerEntryID, gibbonPlannerEntry.gibbonCourseClassID, gibbonPlannerEntry.name,
                    gibbonCourse.nameShort AS course, gibbonCourseClass.nameShort AS class, date, timeStart, timeEnd,
                    viewableStudents, viewableParents, gibbonPlannerEntry.fields
             FROM gibbonPlannerEntry
             JOIN gibbonCourseClass ON (gibbonCourseClass.gibbonCourseClassID=gibbonPlannerEntry.gibbonCourseClassID)
             JOIN gibbonCourse ON (gibbonCourse.gibbonCourseID=gibbonCourseClass.gibbonCourseID)
             WHERE gibbonPlannerEntry.gibbonPlannerEntryID=:id',
            ['id' => $plannerEntryID],
        );
        if ($row === null) {
            return null;
        }
        $fields = json_decode((string) ($row['fields'] ?? ''), true);

        return new Lesson(
            (int) $row['gibbonPlannerEntryID'],
            (int) $row['gibbonCourseClassID'],
            (string) $row['name'],
            (string) $row['course'],
            (string) $row['class'],
            (string) $row['date'],
            (string) $row['timeStart'],
            (string) $row['timeEnd'],
            $row['viewableStudents'] === 'Y',
            $row['viewableParents'] === 'Y',
            is_array($fields) ? $fields : [],
        );
    }

    /** @return array<string, int> custom field name => gibbonCustomFieldID, for the module's lesson fields */
    public function lessonCustomFieldIDs(): array
    {
        $statement = $this->pdo->prepare(
            "SELECT name, gibbonCustomFieldID FROM gibbonCustomField
             WHERE context='Lesson Plan' AND name IN (:videoChat, :presentationOnly) ORDER BY gibbonCustomFieldID"
        );
        $statement->execute(['videoChat' => self::VIDEO_CHAT_FIELD, 'presentationOnly' => self::PRESENTATION_ONLY_FIELD]);
        $ids = [];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $ids[(string) $row['name']] ??= (int) $row['gibbonCustomFieldID'];
        }

        return $ids;
    }

    /** The person's current role in the class; roles ending in " - Left" count as no role (as in core). */
    public function classRole(int $classID, int $personID): ?string
    {
        $row = $this->one(
            "SELECT role FROM gibbonCourseClassPerson
             WHERE gibbonCourseClassID=:classID AND gibbonPersonID=:personID AND role NOT IN ('Student - Left', 'Teacher - Left')
             ORDER BY FIELD(role, 'Teacher', 'Assistant', 'Technician', 'Student', 'Parent') LIMIT 1",
            ['classID' => $classID, 'personID' => $personID],
        );

        return $row === null ? null : (string) $row['role'];
    }

    public function guestRole(int $plannerEntryID, int $personID): ?string
    {
        $row = $this->one(
            'SELECT role FROM gibbonPlannerEntryGuest WHERE gibbonPlannerEntryID=:id AND gibbonPersonID=:personID LIMIT 1',
            ['id' => $plannerEntryID, 'personID' => $personID],
        );

        return $row === null ? null : (string) $row['role'];
    }

    /** Same rule as core's StudentGateway::getStudentByFamilyAdult(). */
    public function isParentOf(int $adultID, int $childID, string $today): bool
    {
        return $this->one(
            "SELECT gibbonFamilyChild.gibbonPersonID
             FROM gibbonFamilyChild
             JOIN gibbonFamilyAdult ON (gibbonFamilyAdult.gibbonFamilyID=gibbonFamilyChild.gibbonFamilyID)
             JOIN gibbonPerson ON (gibbonPerson.gibbonPersonID=gibbonFamilyChild.gibbonPersonID)
             WHERE gibbonPerson.status='Full' AND (dateStart IS NULL OR dateStart<=:today) AND (dateEnd IS NULL OR dateEnd>=:today2)
               AND gibbonFamilyChild.gibbonPersonID=:childID AND gibbonFamilyAdult.gibbonPersonID=:adultID AND childDataAccess='Y'
             LIMIT 1",
            ['today' => $today, 'today2' => $today, 'childID' => $childID, 'adultID' => $adultID],
        ) !== null;
    }


    /**
     * @param array<string, scalar> $params
     * @return array<string, mixed>|null
     */
    private function one(string $sql, array $params): ?array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }
}

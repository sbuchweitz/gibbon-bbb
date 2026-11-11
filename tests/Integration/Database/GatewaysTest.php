<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Tests\Integration\Database;

use Gibbon\Module\BigBlueButton\Database\LessonGateway;
use Gibbon\Module\BigBlueButton\Database\MeetingGateway;
use Gibbon\Module\BigBlueButton\Database\RecordingCacheGateway;
use Gibbon\Module\BigBlueButton\Domain\Lesson;
use Gibbon\Module\BigBlueButton\Domain\MeetingRecord;
use Gibbon\Module\BigBlueButton\Tests\Support\TestDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MeetingGateway::class)]
#[CoversClass(MeetingRecord::class)]
#[CoversClass(RecordingCacheGateway::class)]
#[CoversClass(LessonGateway::class)]
#[CoversClass(Lesson::class)]
final class GatewaysTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = TestDatabase::connect();
        $this->pdo->exec("INSERT INTO gibbonCourse (gibbonCourseID, nameShort) VALUES (1, 'MATH7')");
        $this->pdo->exec("INSERT INTO gibbonCourseClass (gibbonCourseClassID, gibbonCourseID, nameShort) VALUES (10, 1, '7A')");
        $this->pdo->exec("INSERT INTO gibbonPerson (gibbonPersonID) VALUES (1), (2), (3), (4), (5), (6)");
        $this->pdo->exec("INSERT INTO gibbonCourseClassPerson (gibbonCourseClassID, gibbonPersonID, role) VALUES (10, 1, 'Teacher'), (10, 2, 'Student'), (10, 3, 'Student - Left')");
        $this->pdo->exec("INSERT INTO gibbonCustomField (gibbonCustomFieldID, context, name, active) VALUES (7, 'Lesson Plan', 'Video Chat', 'Y'), (8, 'Lesson Plan', 'Presentation Only', 'Y'), (9, 'User', 'Video Chat', 'Y')");
        $this->pdo->exec("INSERT INTO gibbonPlannerEntry (gibbonPlannerEntryID, gibbonCourseClassID, date, timeStart, timeEnd, name, viewableStudents, viewableParents, fields)
            VALUES (42, 10, '2026-11-11', '09:00:00', '09:45:00', 'Fractions', 'Y', 'N', '{\"0007\":\"Include\",\"0008\":\"Yes\",\"videoLink\":\"\"}')");
        $this->pdo->exec("INSERT INTO gibbonPlannerEntryGuest (gibbonPlannerEntryID, gibbonPersonID, role) VALUES (42, 4, 'Guest Teacher')");
        $this->pdo->exec("INSERT INTO gibbonFamily (gibbonFamilyID) VALUES (1)");
        $this->pdo->exec("INSERT INTO gibbonFamilyAdult (gibbonFamilyID, gibbonPersonID, childDataAccess) VALUES (1, 5, 'Y'), (1, 6, 'N')");
        $this->pdo->exec("INSERT INTO gibbonFamilyChild (gibbonFamilyID, gibbonPersonID) VALUES (1, 2)");
    }

    public function testMeetingClaimIsIdempotentAndKeepsTheFirstId(): void
    {
        $gateway = new MeetingGateway($this->pdo);
        $now = new \DateTimeImmutable('2026-11-11 08:51:00', new \DateTimeZone('UTC'));

        self::assertNull($gateway->findByLesson(42));
        $first = $gateway->claim(42, 'gibbon-0123456789ab-42-aaaaaaaa', 'planner42', 1, $now);
        $second = $gateway->claim(42, 'gibbon-0123456789ab-42-bbbbbbbb', null, 4, $now);

        self::assertSame('gibbon-0123456789ab-42-aaaaaaaa', $first->meetingID);
        self::assertEquals($first, $second, 'a concurrent second claim returns the stored row');
        self::assertSame(['gibbon-0123456789ab-42-aaaaaaaa', 'planner42'], $second->meetingIDs());
        self::assertEquals($first, $gateway->findByLesson(42));
    }

    public function testRecordingCacheRoundTrip(): void
    {
        $gateway = new RecordingCacheGateway($this->pdo);
        $fetched = new \DateTimeImmutable('2026-11-11 10:00:00', new \DateTimeZone('UTC'));

        self::assertNull($gateway->get(42));
        $gateway->put(42, '{"recordings":[]}', $fetched);
        $gateway->put(42, '{"recordings":[1]}', $fetched->modify('+5 minutes'));

        $entry = $gateway->get(42);
        self::assertNotNull($entry);
        self::assertSame('{"recordings":[1]}', $entry->payload);
        self::assertSame('2026-11-11 10:05:00', $entry->fetchedAt->format('Y-m-d H:i:s'));
    }

    public function testLoadsLessonWithCustomFields(): void
    {
        $gateway = new LessonGateway($this->pdo);
        $lesson = $gateway->lesson(42);

        self::assertNotNull($lesson);
        self::assertSame(42, $lesson->id);
        self::assertSame(10, $lesson->classID);
        self::assertSame('MATH7.7A', $lesson->classLabel());
        self::assertSame('Fractions', $lesson->name);
        self::assertSame(['2026-11-11', '09:00:00', '09:45:00'], [$lesson->date, $lesson->timeStart, $lesson->timeEnd]);
        self::assertTrue($lesson->viewableStudents);
        self::assertFalse($lesson->viewableParents);
        self::assertSame('Include', $lesson->fieldValue(7));
        self::assertNull($lesson->fieldValue(99));
        self::assertNull($gateway->lesson(999));
    }

    public function testFindsLessonCustomFieldIdsByNameInTheLessonContextOnly(): void
    {
        self::assertSame(['Video Chat' => 7, 'Presentation Only' => 8], (new LessonGateway($this->pdo))->lessonCustomFieldIDs());
    }

    public function testRolesIgnoreLeftEnrolments(): void
    {
        $gateway = new LessonGateway($this->pdo);

        self::assertSame('Teacher', $gateway->classRole(10, 1));
        self::assertSame('Student', $gateway->classRole(10, 2));
        self::assertNull($gateway->classRole(10, 3), 'Student - Left');
        self::assertNull($gateway->classRole(10, 4));
        self::assertSame('Guest Teacher', $gateway->guestRole(42, 4));
        self::assertNull($gateway->guestRole(42, 1));
    }

    public function testFamilyLinkRequiresChildDataAccessAndCurrentEnrolment(): void
    {
        $gateway = new LessonGateway($this->pdo);
        $today = '2026-11-11';

        self::assertTrue($gateway->isParentOf(5, 2, $today));
        self::assertFalse($gateway->isParentOf(6, 2, $today), 'childDataAccess=N');
        self::assertFalse($gateway->isParentOf(5, 1, $today), 'not their child');

        $this->pdo->exec("UPDATE gibbonPerson SET dateEnd='2026-01-01' WHERE gibbonPersonID=2");
        self::assertFalse($gateway->isParentOf(5, 2, $today), 'child has left');
    }

}

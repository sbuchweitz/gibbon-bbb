<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Tests\Integration\Service;

use Gibbon\Module\BigBlueButton\Config\Settings;
use Gibbon\Module\BigBlueButton\Database\LessonGateway;
use Gibbon\Module\BigBlueButton\Database\MeetingGateway;
use Gibbon\Module\BigBlueButton\Database\RecordingCacheGateway;
use Gibbon\Module\BigBlueButton\Domain\JoinState;
use Gibbon\Module\BigBlueButton\Domain\Lesson;
use Gibbon\Module\BigBlueButton\Domain\MeetingIdFactory;
use Gibbon\Module\BigBlueButton\Recording\PlaybackFormat;
use Gibbon\Module\BigBlueButton\Recording\Recording;
use Gibbon\Module\BigBlueButton\Service\LessonAccess;
use Gibbon\Module\BigBlueButton\Service\MeetingService;
use Gibbon\Module\BigBlueButton\Service\Participant;
use Gibbon\Module\BigBlueButton\Service\RecordingCatalog;
use Gibbon\Module\BigBlueButton\Service\Viewer;
use Gibbon\Module\BigBlueButton\Tests\Support\FakeBbbApi;
use Gibbon\Module\BigBlueButton\Tests\Support\FixedClock;
use Gibbon\Module\BigBlueButton\Tests\Support\TestDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LessonAccess::class)]
#[CoversClass(MeetingService::class)]
#[CoversClass(RecordingCatalog::class)]
final class ServicesTest extends TestCase
{
    private const HASH = '0123456789ab';
    private \PDO $pdo;
    private FakeBbbApi $bbb;
    private FixedClock $clock;
    private \DateTimeZone $zone;

    protected function setUp(): void
    {
        $this->pdo = TestDatabase::connect();
        $this->pdo->exec("INSERT INTO gibbonCourse VALUES (1, 'MATH7')");
        $this->pdo->exec("INSERT INTO gibbonCourseClass VALUES (10, 1, '7A')");
        $this->pdo->exec("INSERT INTO gibbonPerson (gibbonPersonID) VALUES (1), (2), (5), (8)");
        $this->pdo->exec("INSERT INTO gibbonCourseClassPerson (gibbonCourseClassID, gibbonPersonID, role) VALUES (10, 1, 'Teacher'), (10, 2, 'Student')");
        $this->pdo->exec("INSERT INTO gibbonFamily VALUES (1)");
        $this->pdo->exec("INSERT INTO gibbonFamilyAdult (gibbonFamilyID, gibbonPersonID, childDataAccess) VALUES (1, 5, 'Y')");
        $this->pdo->exec("INSERT INTO gibbonFamilyChild (gibbonFamilyID, gibbonPersonID) VALUES (1, 2)");
        $this->pdo->exec("INSERT INTO gibbonPlannerEntry (gibbonPlannerEntryID, gibbonCourseClassID, date, timeStart, timeEnd, name, viewableStudents, viewableParents, fields)
            VALUES (42, 10, '2026-11-11', '09:00:00', '09:45:00', 'Fractions', 'Y', 'Y', '{}'), (7, 10, '2026-10-01', '09:00:00', '09:45:00', 'Old lesson', 'Y', 'Y', '{}')");
        $this->bbb = new FakeBbbApi();
        $this->zone = new \DateTimeZone('Etc/GMT-2');
        $this->clock = FixedClock::at('2026-11-11 08:55:00', 'Etc/GMT-2');
    }

    private function lesson(int $id = 42): Lesson
    {
        $lesson = (new LessonGateway($this->pdo))->lesson($id);
        self::assertNotNull($lesson);

        return $lesson;
    }

    private function meetings(int $legacyMax = 40): MeetingService
    {
        $settings = Settings::fromStore(['enableBigBlueButton' => 'Y', 'bigBlueButtonURL' => 'https://bbb.test/bigbluebutton/', 'bigBlueButtonCredentials' => 'secret-value-1', 'legacyMaxPlannerEntryID' => (string) $legacyMax]);

        return new MeetingService(new MeetingGateway($this->pdo), $this->bbb, new MeetingIdFactory(self::HASH), $settings, $this->clock, $this->zone, 'https://gibbon.test/modules/BigBlueButton/bbb_ended.php', 'gibbon.test');
    }

    public function testLessonAccessCombinesPolicyAndLessonRoles(): void
    {
        $access = new LessonAccess(new LessonGateway($this->pdo));

        $teacher = $access->check(42, new Viewer(1, 'Lesson Planner_viewAllEditMyClasses'), null, '2026-11-11');
        $student = $access->check(42, new Viewer(2, 'Lesson Planner_viewMyClasses'), null, '2026-11-11');
        $parent = $access->check(42, new Viewer(5, 'Lesson Planner_viewMyChildrensClasses'), 2, '2026-11-11');
        $stranger = $access->check(42, new Viewer(8, 'Lesson Planner_viewMyClasses'), null, '2026-11-11');
        $missing = $access->check(999, new Viewer(1, 'Lesson Planner_viewEditAllClasses'), null, '2026-11-11');

        self::assertTrue($teacher->allowed && $teacher->moderator);
        self::assertTrue($student->allowed && !$student->moderator);
        self::assertTrue($parent->allowed && !$parent->moderator);
        self::assertFalse($stranger->allowed);
        self::assertFalse($missing->allowed);
        self::assertNull($missing->lesson);
    }

    public function testModeratorCreatesTheMeetingOnceWithStableIds(): void
    {
        $service = $this->meetings();
        $first = $service->join($this->lesson(), new Participant(1, 'Ms Teacher', true));
        $again = $service->join($this->lesson(), new Participant(1, 'Ms Teacher', true));

        self::assertSame(JoinState::Ready, $first->state);
        self::assertNotNull($first->url);
        self::assertStringContainsString('role=MODERATOR', $first->url);
        self::assertStringContainsString('userID='.self::HASH.'-1', $first->url);
        self::assertCount(1, $this->bbb->meetings);
        $spec = array_values($this->bbb->meetings)[0];
        self::assertMatchesRegularExpression('/^gibbon-'.self::HASH.'-42-[0-9a-f]{8}$/', $spec->meetingID);
        self::assertSame('MATH7.7A – Fractions', $spec->name);
        self::assertSame('https://gibbon.test/modules/BigBlueButton/bbb_ended.php', $spec->logoutUrl);
        self::assertSame(['bbb-origin' => 'Gibbon', 'bbb-origin-server-name' => 'gibbon.test', 'gibbon-instance' => self::HASH, 'gibbon-planner-entry' => '42'], $spec->metadata);
        self::assertSame(90, $spec->durationMinutes, '08:55 → 09:55 close + 30 min buffer');
        self::assertSame($spec->meetingID, $service->recordingMeetingIDs($this->lesson(), false)[0], 'kept even if Video Chat is later unticked');
        self::assertTrue($service->hasMeeting($this->lesson()));
        self::assertSame(JoinState::Ready, $again->state);
    }

    public function testViewerWaitsUntilTheTeacherHasOpenedTheClass(): void
    {
        $service = $this->meetings();
        $student = new Participant(2, 'Sam Student', false);

        self::assertSame(JoinState::Waiting, $service->join($this->lesson(), $student)->state);
        self::assertSame(JoinState::Waiting, $service->status($this->lesson(), false));
        self::assertSame([], $this->bbb->meetings, 'viewers never create meetings');

        $service->join($this->lesson(), new Participant(1, 'Ms Teacher', true));
        $joined = $service->join($this->lesson(), $student);

        self::assertSame(JoinState::Ready, $service->status($this->lesson(), false));
        self::assertSame(JoinState::Ready, $joined->state);
        self::assertStringContainsString('role=VIEWER', (string) $joined->url);
    }

    public function testOutsideTheLiveWindow(): void
    {
        $this->clock = FixedClock::at('2026-11-11 08:00:00', 'Etc/GMT-2');
        $early = $this->meetings()->join($this->lesson(), new Participant(1, 'Ms Teacher', true));
        $this->clock = FixedClock::at('2026-11-11 10:30:00', 'Etc/GMT-2');
        $late = $this->meetings()->join($this->lesson(), new Participant(1, 'Ms Teacher', true));

        self::assertSame(JoinState::Upcoming, $early->state);
        self::assertSame('08:50', $early->opensAt?->format('H:i'));
        self::assertSame(JoinState::Closed, $late->state);
        self::assertSame([], $this->bbb->meetings);
    }

    public function testRecordingMeetingIdsFallBackToTheLegacyIdOnlyForPreUpgradeVideoLessons(): void
    {
        $service = $this->meetings(legacyMax: 40);

        self::assertSame(['planner7'], $service->recordingMeetingIDs($this->lesson(7), true));
        self::assertSame([], $service->recordingMeetingIDs($this->lesson(7), false), 'old lessons without video never query BBB');
        self::assertSame([], $service->recordingMeetingIDs($this->lesson(42), true), 'created after the upgrade, never held');
        self::assertFalse($service->hasMeeting($this->lesson(7)));
    }

    public function testLegacyRecordingsCanBeHiddenOnSharedServers(): void
    {
        $settings = Settings::fromStore(['enableBigBlueButton' => 'Y', 'bigBlueButtonURL' => 'https://bbb.test/bigbluebutton/', 'bigBlueButtonCredentials' => 'secret-value-1', 'legacyMaxPlannerEntryID' => '40', 'showLegacyRecordings' => 'N']);
        $service = new MeetingService(new MeetingGateway($this->pdo), $this->bbb, new MeetingIdFactory(self::HASH), $settings, $this->clock, $this->zone, 'https://gibbon.test/ended', 'gibbon.test');

        self::assertSame([], $service->recordingMeetingIDs($this->lesson(7), true));
    }

    public function testPreUpgradeLessonKeepsItsLegacyIdAlongsideTheNewOne(): void
    {
        $this->clock = FixedClock::at('2026-10-01 08:55:00', 'Etc/GMT-2');
        $service = $this->meetings(legacyMax: 40);
        $service->join($this->lesson(7), new Participant(1, 'Ms Teacher', true));

        $ids = $service->recordingMeetingIDs($this->lesson(7), false);
        self::assertCount(2, $ids);
        self::assertSame('planner7', $ids[1]);
    }

    public function testCatalogCachesAndFiltersRecordings(): void
    {
        $catalog = new RecordingCatalog(new RecordingCacheGateway($this->pdo), $this->bbb, $this->clock);
        $this->bbb->recordings = [
            self::recording('rec-b', 'planner42', 2000),
            self::recording('rec-a', 'planner42', 1000),
            self::recording('foreign', 'planner42', 3000, ['gibbon-instance' => 'ffffffffffff']),
        ];

        $first = $catalog->forLesson($this->lesson(), ['planner42'], self::HASH, $this->zone);
        $cached = $catalog->forLesson($this->lesson(), ['planner42'], self::HASH, $this->zone);

        self::assertSame(['rec-a', 'rec-b'], array_map(static fn (Recording $r) => $r->recordID, $first->recordings));
        self::assertFalse($first->failed);
        self::assertEquals($first, $cached);
        self::assertCount(1, $this->bbb->recordingCalls, 'second view is served from the cache');

        $this->clock->advance('+301 seconds');
        $catalog->forLesson($this->lesson(), ['planner42'], self::HASH, $this->zone);
        self::assertCount(2, $this->bbb->recordingCalls, 'cache expires');
    }

    public function testCatalogRefetchesWhenTheLessonGainsAMeetingId(): void
    {
        $catalog = new RecordingCatalog(new RecordingCacheGateway($this->pdo), $this->bbb, $this->clock);
        $catalog->forLesson($this->lesson(), ['planner42'], self::HASH, $this->zone);
        $catalog->forLesson($this->lesson(), ['gibbon-x', 'planner42'], self::HASH, $this->zone);

        self::assertCount(2, $this->bbb->recordingCalls);
    }

    public function testCatalogKeepsTheLastGoodListWhenBbbIsDown(): void
    {
        $catalog = new RecordingCatalog(new RecordingCacheGateway($this->pdo), $this->bbb, $this->clock);
        $this->bbb->recordings = [self::recording('rec-a', 'planner42', 1000)];
        $catalog->forLesson($this->lesson(), ['planner42'], self::HASH, $this->zone);

        $this->bbb->failing = true;
        $this->clock->advance('+1 hour');
        $result = $catalog->forLesson($this->lesson(), ['planner42'], self::HASH, $this->zone);
        $catalog->forLesson($this->lesson(), ['planner42'], self::HASH, $this->zone);

        self::assertTrue($result->failed);
        self::assertSame(['rec-a'], array_map(static fn (Recording $r) => $r->recordID, $result->recordings));
        self::assertCount(2, $this->bbb->recordingCalls, 'a failure is cached briefly (circuit breaker)');
    }

    public function testForcedRefreshIsThrottled(): void
    {
        $catalog = new RecordingCatalog(new RecordingCacheGateway($this->pdo), $this->bbb, $this->clock);
        $catalog->forLesson($this->lesson(), ['planner42'], self::HASH, $this->zone);
        $catalog->forLesson($this->lesson(), ['planner42'], self::HASH, $this->zone, forceRefresh: true);
        self::assertCount(1, $this->bbb->recordingCalls, 'a just-fetched list is not refetched');

        $this->clock->advance('+31 seconds');
        $catalog->forLesson($this->lesson(), ['planner42'], self::HASH, $this->zone, forceRefresh: true);
        self::assertCount(2, $this->bbb->recordingCalls);
    }

    /** @param array<string, string> $metadata */
    private static function recording(string $id, string $meetingID, int $start, array $metadata = []): Recording
    {
        return new Recording($id, $meetingID, 'Fractions', true, $start, $start + 60000, $metadata, [new PlaybackFormat('presentation', 'https://bbb.test/playback/presentation/2.3/'.$id)]);
    }
}

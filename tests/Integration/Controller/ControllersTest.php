<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Tests\Integration\Controller;

use Gibbon\Module\BigBlueButton\Config\JoinMode;
use Gibbon\Module\BigBlueButton\Controller\LessonHookController;
use Gibbon\Module\BigBlueButton\Controller\LiveController;
use Gibbon\Module\BigBlueButton\Controller\RecordingController;
use Gibbon\Module\BigBlueButton\Gibbon\ModuleServices;
use Gibbon\Module\BigBlueButton\Gibbon\RequestContext;
use Gibbon\Module\BigBlueButton\Http\View;
use Gibbon\Module\BigBlueButton\Recording\PlaybackFormat;
use Gibbon\Module\BigBlueButton\Recording\Recording;
use Gibbon\Module\BigBlueButton\Tests\Support\FakeBbbApi;
use Gibbon\Module\BigBlueButton\Tests\Support\FixedClock;
use Gibbon\Module\BigBlueButton\Tests\Support\TestDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LessonHookController::class)]
#[CoversClass(LiveController::class)]
#[CoversClass(RecordingController::class)]
#[CoversClass(ModuleServices::class)]
#[CoversClass(View::class)]
final class ControllersTest extends TestCase
{
    private const TEACHER = 1;
    private const STUDENT = 2;
    private const STRANGER = 9;
    private const LIVE = 50;          // today 09:00-09:45, Video Chat
    private const LEGACY_VIDEO = 10;  // before the upgrade, Video Chat ticked
    private const LEGACY_PLAIN = 11;  // before the upgrade, no Video Chat
    private const NEW_PLAIN = 60;     // after the upgrade, no Video Chat

    private \PDO $pdo;
    private FakeBbbApi $bbb;
    private FixedClock $clock;

    protected function setUp(): void
    {
        $this->pdo = TestDatabase::connect();
        TestDatabase::seedSettings($this->pdo, [
            'enableBigBlueButton' => 'Y', 'bigBlueButtonURL' => 'https://bbb.test/bigbluebutton/', 'bigBlueButtonCredentials' => 'secret-value-1234',
            'joinMode' => 'embedded', 'embeddedAllowWithoutSession' => 'Y', 'recordingHostOverride' => '', 'showLegacyRecordings' => 'Y',
            'secretRotationRequired' => 'N', 'legacyMaxPlannerEntryID' => '40',
        ]);
        $this->pdo->exec("INSERT INTO gibbonCustomField (gibbonCustomFieldID, context, name, active) VALUES (1, 'Lesson Plan', 'Video Chat', 'Y'), (2, 'Lesson Plan', 'Presentation Only', 'Y')");
        $this->pdo->exec("INSERT INTO gibbonCourse VALUES (1, 'SCI8')");
        $this->pdo->exec("INSERT INTO gibbonCourseClass VALUES (10, 1, '8B')");
        $this->pdo->exec('INSERT INTO gibbonPerson (gibbonPersonID) VALUES (1), (2), (9)');
        $this->pdo->exec("INSERT INTO gibbonCourseClassPerson (gibbonCourseClassID, gibbonPersonID, role) VALUES (10, 1, 'Teacher'), (10, 2, 'Student')");
        $video = '{"0001":"Include"}';
        foreach ([[self::LIVE, '2026-11-11', $video], [self::LEGACY_VIDEO, '2026-10-01', $video], [self::LEGACY_PLAIN, '2026-10-02', '{}'], [self::NEW_PLAIN, '2026-11-10', '{}']] as [$id, $date, $fields]) {
            $this->pdo->prepare("INSERT INTO gibbonPlannerEntry (gibbonPlannerEntryID, gibbonCourseClassID, date, timeStart, timeEnd, name, fields) VALUES (?, 10, ?, '09:00:00', '09:45:00', 'Cells', ?)")
                ->execute([$id, $date, $fields]);
        }
        $this->bbb = new FakeBbbApi();
        $this->clock = FixedClock::at('2026-11-11 09:05:00');
    }

    private function services(int $personID, string $highestAction = 'Lesson Planner_viewMyClasses', bool $admin = false): ModuleServices
    {
        $context = new RequestContext($personID, 'Person '.$personID, $highestAction, 'https://gibbon.test', 'install-guid', new \DateTimeZone('UTC'), $admin);

        return new ModuleServices($this->pdo, $context, $this->bbb, $this->clock);
    }

    private function hook(int $personID, int $lessonID, string $hook, bool $admin = false): string
    {
        return (new LessonHookController($this->services($personID, admin: $admin), self::view()))->render($lessonID, $hook, null);
    }

    private static function view(): View
    {
        return new View(dirname(__DIR__, 3).'/views');
    }

    private function recording(string $meetingID, int $n, array $formats = ['presentation', 'video']): Recording
    {
        $id = str_repeat((string) $n, 40).'-17943807200'.str_pad((string) $n, 2, '0', STR_PAD_LEFT);

        return new Recording($id, $meetingID, 'Cells', true, 1_794_380_000_000 + $n * 60_000, 1_794_380_000_000 + $n * 60_000 + 2_400_000, [],
            array_map(static fn (string $type) => new PlaybackFormat($type, "https://bbb.test/playback/{$type}/{$id}/"), $formats));
    }

    public function testLiveSectionOnlyForVideoLessonsAndOnlyAsANewTabLink(): void
    {
        $html = $this->hook(self::STUDENT, self::LIVE, LessonHookController::LIVE_HOOK);

        self::assertStringContainsString('Join Class (New Tab)', $html);
        self::assertStringContainsString('href="https://gibbon.test/modules/BigBlueButton/bbb_live.php?gibbonPlannerEntryID=50"', $html);
        self::assertStringContainsString('target="_blank"', $html);
        self::assertStringNotContainsString('<iframe', $html);
        self::assertSame('', $this->hook(self::STUDENT, self::NEW_PLAIN, LessonHookController::LIVE_HOOK));
        self::assertSame([], $this->bbb->recordingCalls);
    }

    public function testDirectModeLinksToTheRedirect(): void
    {
        $this->pdo->exec("UPDATE gibbonSetting SET value='direct' WHERE name='joinMode'");

        self::assertStringContainsString('bbb_join.php?gibbonPlannerEntryID=50', $this->hook(self::STUDENT, self::LIVE, LessonHookController::LIVE_HOOK));
    }

    public function testOldLessonsWithoutVideoNeverQueryBigBlueButton(): void
    {
        self::assertSame('', $this->hook(self::STUDENT, self::LEGACY_PLAIN, LessonHookController::RECORDED_HOOK));
        self::assertSame('', $this->hook(self::STUDENT, self::NEW_PLAIN, LessonHookController::RECORDED_HOOK));
        self::assertSame([], $this->bbb->recordingCalls);
    }

    public function testOldVideoLessonsShowTheirVersionOneRecordings(): void
    {
        $this->bbb->recordings = [$this->recording('planner10', 1)];

        $html = $this->hook(self::STUDENT, self::LEGACY_VIDEO, LessonHookController::RECORDED_HOOK);

        self::assertStringContainsString('Recording 1', $html);
        self::assertStringContainsString('data-bbb-viewer=', $html);
        self::assertSame([['planner10']], $this->bbb->recordingCalls);
    }

    public function testOldVideoLessonWithoutRecordingsShowsNothing(): void
    {
        self::assertSame('', $this->hook(self::STUDENT, self::LEGACY_VIDEO, LessonHookController::RECORDED_HOOK));
    }

    public function testALessonThatHeldAMeetingKeepsItsRecordingsWhenVideoChatIsUnticked(): void
    {
        $this->services(self::TEACHER)->meetings()->join($this->services(self::TEACHER)->lessons->lesson(self::LIVE) ?? self::fail(), new \Gibbon\Module\BigBlueButton\Service\Participant(self::TEACHER, 'T', true));
        $meetingID = array_key_first($this->bbb->meetings);
        $this->pdo->exec('UPDATE gibbonPlannerEntry SET fields=\'{}\' WHERE gibbonPlannerEntryID='.self::LIVE);
        $this->clock->advance('+2 hours');

        self::assertStringContainsString('No recordings yet', $this->hook(self::STUDENT, self::LIVE, LessonHookController::RECORDED_HOOK));

        $this->bbb->recordings = [$this->recording((string) $meetingID, 1)];
        $this->clock->advance('+16 minutes'); // past the cache lifetime
        self::assertStringContainsString('Recording 1', $this->hook(self::STUDENT, self::LIVE, LessonHookController::RECORDED_HOOK));
    }

    public function testAdminsAreRemindedToReplaceTheSecretAfterUpgrading(): void
    {
        $this->pdo->exec("UPDATE gibbonSetting SET value='Y' WHERE name='secretRotationRequired'");

        self::assertStringContainsString('bbb-conf --setsecret', $this->hook(self::TEACHER, self::LIVE, LessonHookController::LIVE_HOOK, admin: true));
        self::assertStringNotContainsString('bbb-conf --setsecret', $this->hook(self::STUDENT, self::LIVE, LessonHookController::LIVE_HOOK));
    }

    public function testViewerKeepsTheLessonPageNumberingAndRefusesStrangers(): void
    {
        $this->bbb->recordings = [$this->recording('planner10', 1), $this->recording('planner10', 2)];
        $second = $this->bbb->recordings[1]->recordID;
        $controller = new RecordingController($this->services(self::STUDENT), self::view());

        $page = $controller->page(self::LEGACY_VIDEO, null, $second, 'video');
        self::assertSame(200, $page->status);
        self::assertStringContainsString('Recording 2', $page->body);
        self::assertStringContainsString('src="https://bbb.test/playback/video/'.$second.'/"', $page->body);
        self::assertSame('no-store', $page->headers['Cache-Control']);

        self::assertSame(404, $controller->page(self::LEGACY_VIDEO, null, $second, 'summary')->status);
        self::assertSame(404, $controller->page(self::LEGACY_VIDEO, null, 'not-a-record-id', 'video')->status);
        self::assertSame(403, (new RecordingController($this->services(self::STRANGER), self::view()))->page(self::LEGACY_VIDEO, null, $second, 'video')->status);
    }

    public function testLivePagesAndStatus(): void
    {
        $student = new LiveController($this->services(self::STUDENT), self::view());
        self::assertStringContainsString('Waiting for the teacher', $student->page(self::LIVE, null, JoinMode::Embedded)->body);
        self::assertSame('{"state":"waiting"}', $student->status(self::LIVE, null)->body);

        $teacher = (new LiveController($this->services(self::TEACHER, 'Lesson Planner_viewAllEditMyClasses'), self::view()))->page(self::LIVE, null, JoinMode::Embedded);
        self::assertStringContainsString('<iframe class="bbb-frame"', $teacher->body);
        self::assertStringContainsString('allow-popups', $teacher->body);
        self::assertStringContainsString("frame-src 'self' https: https://bbb.test", $teacher->headers['Content-Security-Policy']);

        $direct = $student->page(self::LIVE, null, JoinMode::Direct);
        self::assertSame(302, $direct->status);
        self::assertStringContainsString('role=VIEWER', $direct->headers['Location']);

        $stranger = new LiveController($this->services(self::STRANGER), self::view());
        self::assertSame(403, $stranger->page(self::LIVE, null, JoinMode::Embedded)->status);
        self::assertSame('{"state":"denied"}', $stranger->status(self::LIVE, null)->body);
        self::assertSame(404, $student->page(self::NEW_PLAIN, null, JoinMode::Embedded)->status);
    }

    public function testBbbOutageShowsAFriendlyErrorNotAStackTrace(): void
    {
        $this->bbb->failing = true;
        $page = (new LiveController($this->services(self::TEACHER), self::view()))->page(self::LIVE, null, JoinMode::Embedded);

        self::assertSame(503, $page->status);
        self::assertStringContainsString('cannot be reached right now', $page->body);
        self::assertStringNotContainsString('secret-value-1234', $page->body);
    }
}

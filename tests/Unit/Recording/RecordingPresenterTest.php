<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Tests\Unit\Recording;

use Gibbon\Module\BigBlueButton\Recording\PlaybackFormat;
use Gibbon\Module\BigBlueButton\Recording\PlaybackUrlRewriter;
use Gibbon\Module\BigBlueButton\Recording\Recording;
use Gibbon\Module\BigBlueButton\Recording\RecordingPresenter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RecordingPresenter::class)]
final class RecordingPresenterTest extends TestCase
{
    private const VIEWER = 'https://gibbon.test/modules/BigBlueButton/bbb_recording.php';
    private const ID_A = '183f0bf3a0982a127bdb8161e0c44eb696b3e75c-1794380520000';
    private const ID_B = '283f0bf3a0982a127bdb8161e0c44eb696b3e75c-1794384120000';

    private static function recording(string $id, int $startMs, string $host = 'https://bigbluebutton.example.org'): Recording
    {
        return new Recording($id, 'planner42', 'Fractions', true, $startMs, $startMs + 2_400_000, [], [
            new PlaybackFormat('summary', "{$host}/presentation/{$id}/summary/summary.html"),
            new PlaybackFormat('video', "{$host}/playback/video/{$id}/"),
            new PlaybackFormat('presentation', "{$host}/playback/presentation/2.3/{$id}"),
            new PlaybackFormat('transcript', "{$host}/presentation/{$id}/transcript/transcript.txt"),
            new PlaybackFormat('podcast', 'javascript:alert(1)'),
        ]);
    }

    private function presenter(?string $override = 'https://bbb.school.org'): RecordingPresenter
    {
        return new RecordingPresenter(new PlaybackUrlRewriter($override), new \DateTimeZone('Etc/GMT-2'), self::VIEWER);
    }

    public function testOrdersFormatsAndRewritesTheBrokenPlaybackHost(): void
    {
        [$view] = $this->presenter()->present([self::recording(self::ID_A, 1794380520000)], 42, false, null);

        self::assertSame(['presentation', 'video', 'transcript', 'summary'], array_map(static fn ($l) => $l->type, $view->links));
        self::assertSame(['Presentation', 'Video', 'Transcript', 'Summary'], array_map(static fn ($l) => $l->label, $view->links));
        foreach ($view->links as $link) {
            self::assertStringStartsWith('https://bbb.school.org/', $link->playbackUrl);
        }
    }

    public function testDropsFormatsWithUnsafeUrls(): void
    {
        [$view] = $this->presenter()->present([self::recording(self::ID_A, 1794380520000)], 42, false, null);

        self::assertNotContains('podcast', array_map(static fn ($l) => $l->type, $view->links));
    }

    public function testHrefIsTheDownloadableFileWhereThereIsOne(): void
    {
        [$view] = $this->presenter()->present([self::recording(self::ID_A, 1794380520000)], 42, false, null);
        $byType = array_column(array_map(static fn ($l) => ['type' => $l->type, 'link' => $l], $view->links), 'link', 'type');

        self::assertSame('https://bbb.school.org/playback/video/'.self::ID_A.'/video-0.m4v', $byType['video']->href);
        self::assertTrue($byType['video']->downloadable);
        self::assertSame('https://bbb.school.org/presentation/'.self::ID_A.'/transcript/transcript.txt', $byType['transcript']->href);
        self::assertFalse($byType['presentation']->downloadable);
        self::assertSame($byType['presentation']->viewerUrl, $byType['presentation']->href);
    }

    public function testViewerUrlCarriesOnlyIdsNeverThePlaybackUrl(): void
    {
        [$view] = $this->presenter()->present([self::recording(self::ID_A, 1794380520000)], 42, false, 77);
        $query = [];
        parse_str((string) parse_url($view->links[0]->viewerUrl, PHP_URL_QUERY), $query);

        self::assertStringStartsWith(self::VIEWER.'?', $view->links[0]->viewerUrl);
        self::assertSame(['gibbonPlannerEntryID' => '42', 'recordID' => self::ID_A, 'format' => 'presentation', 'search' => '77'], $query);
    }

    public function testPresentationOnlyHidesTheOtherFormats(): void
    {
        [$view] = $this->presenter()->present([self::recording(self::ID_A, 1794380520000)], 42, true, null);

        self::assertSame(['presentation'], array_map(static fn ($l) => $l->type, $view->links));
    }

    public function testLabelsEachRecordingWithItsLocalStartTime(): void
    {
        // The first recording starts at 07:02 UTC, which is 09:02 at UTC+2.
        $views = $this->presenter()->present([self::recording(self::ID_B, 1794384120000), self::recording(self::ID_A, 1794380520000)], 42, false, null);

        self::assertSame([self::ID_A, self::ID_B], array_map(static fn ($v) => $v->recordID, $views));
        self::assertSame('09:02', $views[0]->startedAt->format('H:i'));
        self::assertSame(1, $views[0]->number);
        self::assertSame(2, $views[1]->number);
        self::assertSame(40, $views[0]->durationMinutes);
    }

    public function testRecordingWithNoUsableFormatIsOmitted(): void
    {
        $broken = new Recording(self::ID_A, 'planner42', 'x', true, 1, 2, [], [new PlaybackFormat('presentation', 'ftp://nope')]);

        self::assertSame([], $this->presenter()->present([$broken], 42, false, null));
    }
}

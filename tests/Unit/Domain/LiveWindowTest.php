<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Tests\Unit\Domain;

use Gibbon\Module\BigBlueButton\Domain\LiveState;
use Gibbon\Module\BigBlueButton\Domain\LiveWindow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(LiveWindow::class)]
final class LiveWindowTest extends TestCase
{
    private static function at(string $time, string $zone = 'Etc/GMT-2'): \DateTimeImmutable
    {
        return new \DateTimeImmutable($time, new \DateTimeZone($zone));
    }

    private function lesson(): LiveWindow
    {
        return LiveWindow::fromLesson('2026-11-11', '09:00:00', '09:45:00', new \DateTimeZone('Etc/GMT-2'));
    }

    /** @return iterable<string, array{string, LiveState}> */
    public static function moments(): iterable
    {
        yield 'day before' => ['2026-11-10 09:00:00', LiveState::Upcoming];
        yield 'just before lead time' => ['2026-11-11 08:49:59', LiveState::Upcoming];
        yield 'lead time starts' => ['2026-11-11 08:50:00', LiveState::Open];
        yield 'during lesson' => ['2026-11-11 09:20:00', LiveState::Open];
        yield 'tail time ends' => ['2026-11-11 09:55:00', LiveState::Open];
        yield 'after tail time' => ['2026-11-11 09:55:01', LiveState::Closed];
        yield 'next day' => ['2026-11-12 09:00:00', LiveState::Closed];
    }

    #[DataProvider('moments')]
    public function testStateAroundTheLesson(string $now, LiveState $expected): void
    {
        self::assertSame($expected, $this->lesson()->state(self::at($now)));
    }

    public function testComparesInstantsAcrossTimezones(): void
    {
        // 07:20 UTC is 09:20 at UTC+2.
        self::assertSame(LiveState::Open, $this->lesson()->state(self::at('2026-11-11 07:20:00', 'UTC')));
    }

    public function testOpensAndClosesAtTheConfiguredMargins(): void
    {
        $window = $this->lesson();

        self::assertSame('2026-11-11 08:50:00', $window->opensAt()->format('Y-m-d H:i:s'));
        self::assertSame('2026-11-11 09:55:00', $window->closesAt()->format('Y-m-d H:i:s'));
    }

    public function testLessonEndingAfterMidnightRollsOver(): void
    {
        $window = LiveWindow::fromLesson('2026-11-11', '23:30:00', '00:15:00', new \DateTimeZone('UTC'));

        self::assertSame('2026-11-12 00:15:00', $window->lessonEnd->format('Y-m-d H:i:s'));
        self::assertSame(LiveState::Open, $window->state(self::at('2026-11-12 00:05:00', 'UTC')));
    }

    public function testMeetingDurationCoversTheRestOfTheWindowPlusBuffer(): void
    {
        $window = $this->lesson();

        // From 08:50 to 09:55 is 65 minutes, plus a 30-minute buffer.
        self::assertSame(95, $window->meetingDurationMinutes(self::at('2026-11-11 08:50:00')));
        self::assertSame(LiveWindow::MIN_MEETING_MINUTES, $window->meetingDurationMinutes(self::at('2026-11-11 12:00:00')));
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function invalidLessons(): iterable
    {
        yield 'bad date' => ['2026-13-40', '09:00:00', '09:45:00'];
        yield 'bad start' => ['2026-11-11', 'nine', '09:45:00'];
        yield 'empty end' => ['2026-11-11', '09:00:00', ''];
    }

    #[DataProvider('invalidLessons')]
    public function testRejectsInvalidLessonTimes(string $date, string $start, string $end): void
    {
        $this->expectException(\InvalidArgumentException::class);
        LiveWindow::fromLesson($date, $start, $end, new \DateTimeZone('UTC'));
    }
}

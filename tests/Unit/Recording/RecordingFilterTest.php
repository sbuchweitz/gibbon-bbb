<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Tests\Unit\Recording;

use Gibbon\Module\BigBlueButton\Recording\Recording;
use Gibbon\Module\BigBlueButton\Recording\RecordingFilter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RecordingFilter::class)]
final class RecordingFilterTest extends TestCase
{
    /** @param array<string,string> $meta */
    private static function rec(string $id, string $meeting, bool $pub, int $start, array $meta = []): Recording
    {
        return new Recording($id, $meeting, $id, $pub, $start, $start + 1, $meta, []);
    }

    /** @param list<Recording> $l @return list<string> */
    private static function ids(array $l): array
    {
        return array_map(static fn (Recording $r): string => $r->recordID, $l);
    }

    public function testFiltersAndSorts(): void
    {
        $in = [
            self::rec('late', 'm1', true, 300),
            self::rec('unpub', 'm1', false, 100),
            self::rec('other-meeting', 'm9', true, 100),
            self::rec('wrong-instance', 'm1', true, 100, ['gibbon-instance' => 'zzz']),
            self::rec('mine', 'm1', true, 200, ['gibbon-instance' => 'abc']),
            self::rec('no-meta', 'm2', true, 50),
        ];

        self::assertSame(['no-meta', 'mine', 'late'], self::ids((new RecordingFilter())->forLesson($in, ['m1', 'm2'], 'abc')));
    }

    public function testSortIsStable(): void
    {
        $in = [self::rec('a', 'm', true, 5), self::rec('b', 'm', true, 5), self::rec('c', 'm', true, 1), self::rec('d', 'm', true, 5)];

        self::assertSame(['c', 'a', 'b', 'd'], self::ids((new RecordingFilter())->forLesson($in, ['m'], 'h')));
    }

    public function testEmptyInputs(): void
    {
        self::assertSame([], (new RecordingFilter())->forLesson([], ['m'], 'h'));
        self::assertSame([], (new RecordingFilter())->forLesson([self::rec('a', 'm', true, 1)], [], 'h'));
    }

    public function testNumericMeetingIdsMatchAsStrings(): void
    {
        $in = [self::rec('a', '123', true, 1)];
        self::assertSame(['a'], self::ids((new RecordingFilter())->forLesson($in, ['123'], 'h')));
    }
}

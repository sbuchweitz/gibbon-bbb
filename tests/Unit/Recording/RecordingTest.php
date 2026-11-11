<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Tests\Unit\Recording;

use Gibbon\Module\BigBlueButton\Recording\PlaybackFormat;
use Gibbon\Module\BigBlueButton\Recording\Recording;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Recording::class)]
final class RecordingTest extends TestCase
{
    private function make(): Recording
    {
        return new Recording('r-1', 'm-1', 'Lesson', true, 1000, 2000, ['bbb-origin' => 'Gibbon'], [
            new PlaybackFormat('presentation', 'https://x.test/p'),
            new PlaybackFormat('video', 'https://x.test/v'),
        ]);
    }

    public function testLookups(): void
    {
        $r = $this->make();
        self::assertSame('https://x.test/v', $r->format('video')?->url);
        self::assertNull($r->format('summary'));
        self::assertSame('Gibbon', $r->meta('bbb-origin'));
        self::assertNull($r->meta('missing'));
    }

    public function testWithFormatsReturnsCopy(): void
    {
        $r = $this->make();
        $c = $r->withFormats([new PlaybackFormat('summary', 'https://x.test/s')]);

        self::assertCount(2, $r->formats);
        self::assertCount(1, $c->formats);
        self::assertSame('r-1', $c->recordID);
        self::assertSame(2000, $c->endTime);
    }

    public function testJsonRoundTrip(): void
    {
        $r = $this->make();
        $json = json_encode($r->toArray(), JSON_THROW_ON_ERROR);
        $back = Recording::fromArray(json_decode($json, true, 512, JSON_THROW_ON_ERROR));

        self::assertEquals($r, $back);
    }

    public function testEmptyMetadataRoundTrip(): void
    {
        $r = new Recording('r', 'm', 'n', false, 0, 0, [], []);
        self::assertEquals($r, Recording::fromArray($r->toArray()));
    }

    /** @return array<string, array{0: array<mixed>}> */
    public static function malformed(): array
    {
        $ok = [
            'recordID' => 'r', 'meetingID' => 'm', 'name' => 'n', 'published' => true,
            'startTime' => 1, 'endTime' => 2, 'metadata' => ['a' => 'b'],
            'formats' => [['type' => 'video', 'url' => 'https://x.test']],
        ];
        $cases = ['empty' => [[]]];
        foreach (['recordID' => 5, 'meetingID' => null, 'name' => [], 'published' => 'true', 'startTime' => '1',
                  'endTime' => 1.5, 'metadata' => 'x', 'formats' => 'x'] as $k => $bad) {
            $cases["bad $k"] = [array_merge($ok, [$k => $bad])];
            $missing = $ok;
            unset($missing[$k]);
            $cases["missing $k"] = [$missing];
        }
        $cases['metadata non-string value'] = [array_merge($ok, ['metadata' => ['a' => 1]])];
        $cases['format not array'] = [array_merge($ok, ['formats' => ['x']])];
        $cases['format bad url'] = [array_merge($ok, ['formats' => [['type' => 'v', 'url' => 3]]])];
        $cases['format missing type'] = [array_merge($ok, ['formats' => [['url' => 'u']]])];

        return $cases;
    }

    /** @param array<mixed> $data */
    #[DataProvider('malformed')]
    public function testFromArrayRejectsMalformed(array $data): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Recording::fromArray($data);
    }
}

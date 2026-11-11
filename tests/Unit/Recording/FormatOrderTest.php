<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Tests\Unit\Recording;

use Gibbon\Module\BigBlueButton\Recording\FormatOrder;
use Gibbon\Module\BigBlueButton\Recording\PlaybackFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FormatOrder::class)]
final class FormatOrderTest extends TestCase
{
    /** @param list<PlaybackFormat> $f @return list<string> */
    private static function types(array $f): array
    {
        return array_map(static fn (PlaybackFormat $x): string => $x->type, $f);
    }

    public function testKnownOrderThenAlphabeticOthers(): void
    {
        $in = [];
        foreach (['zeta', 'summary', 'alpha', 'video', 'transcript', 'presentation'] as $t) {
            $in[] = new PlaybackFormat($t, "https://x.test/$t");
        }

        self::assertSame(
            ['presentation', 'video', 'transcript', 'summary', 'alpha', 'zeta'],
            self::types((new FormatOrder())->sort($in))
        );
    }

    public function testDedupeFirstWins(): void
    {
        $out = (new FormatOrder())->sort([
            new PlaybackFormat('video', 'https://x.test/1'),
            new PlaybackFormat('video', 'https://x.test/2'),
        ]);

        self::assertCount(1, $out);
        self::assertSame('https://x.test/1', $out[0]->url);
    }

    public function testEmpty(): void
    {
        self::assertSame([], (new FormatOrder())->sort([]));
        self::assertSame([], (new FormatOrder())->presentationOnly([]));
    }

    public function testPresentationOnly(): void
    {
        $out = (new FormatOrder())->presentationOnly([
            new PlaybackFormat('video', 'u'),
            new PlaybackFormat('presentation', 'u'),
            new PlaybackFormat('presentation_video', 'u'),
        ]);

        self::assertSame(['presentation', 'presentation_video'], self::types($out));
    }
}

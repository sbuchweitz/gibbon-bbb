<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Tests\Unit\Recording;

use Gibbon\Module\BigBlueButton\Recording\PlaybackFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PlaybackFormat::class)]
final class PlaybackFormatTest extends TestCase
{
    public function testWithUrlReturnsNewCopy(): void
    {
        $a = new PlaybackFormat('video', 'https://a.test/x');
        $b = $a->withUrl('https://b.test/x');

        self::assertSame('https://a.test/x', $a->url);
        self::assertSame('https://b.test/x', $b->url);
        self::assertSame('video', $b->type);
    }
}

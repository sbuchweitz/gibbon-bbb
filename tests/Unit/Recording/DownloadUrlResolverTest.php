<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Tests\Unit\Recording;

use Gibbon\Module\BigBlueButton\Recording\DownloadUrlResolver;
use Gibbon\Module\BigBlueButton\Recording\PlaybackFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DownloadUrlResolver::class)]
final class DownloadUrlResolverTest extends TestCase
{
    /** @return array<string, array{0: string, 1: string, 2: ?string}> */
    public static function cases(): array
    {
        return [
            'video' => ['video', 'https://b.test/playback/video/abc-1/', 'https://b.test/playback/video/abc-1/video-0.m4v'],
            'video no slash' => ['video', 'https://b.test/playback/video/abc-1', 'https://b.test/playback/video/abc-1/video-0.m4v'],
            'video query fragment' => ['video', 'https://b.test/playback/video/abc-1/?t=1#x', 'https://b.test/playback/video/abc-1/video-0.m4v'],
            'video many slashes' => ['video', 'https://b.test/playback/video/abc-1///', 'https://b.test/playback/video/abc-1/video-0.m4v'],
            'transcript' => ['transcript', 'https://b.test/presentation/a/transcript/transcript.txt', 'https://b.test/presentation/a/transcript/transcript.txt'],
            'transcript non txt' => ['transcript', 'https://b.test/t/transcript.vtt', 'https://b.test/t/transcript.vtt'],
            'txt other type' => ['notes', 'https://b.test/n/notes.txt', 'https://b.test/n/notes.txt'],
            'txt with query' => ['notes', 'https://b.test/n/notes.txt?v=1', 'https://b.test/n/notes.txt?v=1'],
            'txt uppercase' => ['notes', 'https://b.test/n/NOTES.TXT', 'https://b.test/n/NOTES.TXT'],
            'presentation' => ['presentation', 'https://b.test/playback/presentation/2.3/abc', null],
            'summary html' => ['summary', 'https://b.test/summary/summary.html', null],
            'txt in query only' => ['summary', 'https://b.test/s?f=a.txt', null],
        ];
    }

    #[DataProvider('cases')]
    public function testDownloadUrl(string $type, string $url, ?string $expected): void
    {
        self::assertSame($expected, (new DownloadUrlResolver())->downloadUrl(new PlaybackFormat($type, $url)));
    }

    public function testVideoWithInvalidUrlIsNull(): void
    {
        self::assertNull((new DownloadUrlResolver())->downloadUrl(new PlaybackFormat('video', 'javascript:x')));
    }
}

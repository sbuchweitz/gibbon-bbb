<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Tests\Unit\Recording;

use Gibbon\Module\BigBlueButton\Recording\PlaybackUrlRewriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PlaybackUrlRewriter::class)]
final class PlaybackUrlRewriterTest extends TestCase
{
    /** @return array<string, array{0: string}> */
    public static function badUrls(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'data' => ['data:text/html,x'],
            'relative' => ['/playback/x'],
            'protocol relative' => ['//evil.test/x'],
            'empty' => [''],
            'no host' => ['https:///x'],
            'ftp' => ['ftp://x.test/a'],
            'space' => ['https://x.test/a b'],
            'leading space' => [' https://x.test/a'],
            'newline' => ["https://x.test/a\nb"],
            'tab' => ["https://x.test/\ta"],
            'nul' => ["https://x.test/a\0"],
            'backslash trick' => ['https://x.test\\@evil.test/'],
            'no scheme' => ['x.test/a'],
        ];
    }

    #[DataProvider('badUrls')]
    public function testRejectsBadUrls(string $url): void
    {
        self::assertNull((new PlaybackUrlRewriter(null))->rewrite($url));
        self::assertNull((new PlaybackUrlRewriter('https://bbb.example.net'))->rewrite($url));
        self::assertNull(PlaybackUrlRewriter::origin($url));
    }

    public function testNoOverrideReturnsUnchanged(): void
    {
        $u = 'https://bigbluebutton.example.org/playback/presentation/2.3/abc?x=1#f';
        self::assertSame($u, (new PlaybackUrlRewriter(null))->rewrite($u));
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function overrides(): array
    {
        return [
            'host swap' => ['https://bbb.example.net', 'https://bigbluebutton.example.org/playback/p/2.3/id', 'https://bbb.example.net/playback/p/2.3/id'],
            'keeps query and fragment' => ['https://bbb.example.net', 'https://old.test/a/b?x=1&y=%20#frag', 'https://bbb.example.net/a/b?x=1&y=%20#frag'],
            'scheme swap and port' => ['http://bbb.local:8080', 'https://old.test:9443/a', 'http://bbb.local:8080/a'],
            'drops old port' => ['https://bbb.example.net', 'https://old.test:8443/a', 'https://bbb.example.net/a'],
            'origin only url' => ['https://bbb.example.net', 'https://old.test', 'https://bbb.example.net'],
            'query without path' => ['https://bbb.example.net', 'https://old.test?x=1', 'https://bbb.example.net?x=1'],
            'ipv6 source' => ['https://bbb.example.net', 'http://[::1]:8080/x', 'https://bbb.example.net/x'],
            'ipv6 override' => ['http://[::1]:8080', 'https://old.test/x?y', 'http://[::1]:8080/x?y'],
            'userinfo dropped' => ['https://bbb.example.net', 'https://user:pw@old.test/x', 'https://bbb.example.net/x'],
        ];
    }

    #[DataProvider('overrides')]
    public function testOverride(string $origin, string $url, string $expected): void
    {
        self::assertSame($expected, (new PlaybackUrlRewriter($origin))->rewrite($url));
    }

    /** @return array<string, array{0: string}> */
    public static function badOrigins(): array
    {
        return [
            'empty' => [''],
            'no scheme' => ['bbb.example.net'],
            'ftp' => ['ftp://bbb.example.net'],
            'trailing slash' => ['https://bbb.example.net/'],
            'path' => ['https://bbb.example.net/x'],
            'query' => ['https://bbb.example.net?x=1'],
            'no host' => ['https://'],
            'javascript' => ['javascript:alert(1)'],
        ];
    }

    #[DataProvider('badOrigins')]
    public function testConstructorValidatesOrigin(string $origin): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PlaybackUrlRewriter($origin);
    }

    /** @return array<string, array{0: string, 1: ?string}> */
    public static function origins(): array
    {
        return [
            'plain' => ['https://a.test/x/y?z', 'https://a.test'],
            'port' => ['http://a.test:8080/x', 'http://a.test:8080'],
            'ipv6' => ['http://[::1]:8080/x', 'http://[::1]:8080'],
            'uppercase scheme/host' => ['HTTPS://A.Test/x', 'https://a.test'],
            'invalid' => ['javascript:x', null],
        ];
    }

    #[DataProvider('origins')]
    public function testOrigin(string $url, ?string $expected): void
    {
        self::assertSame($expected, PlaybackUrlRewriter::origin($url));
    }
}

<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Tests\Unit\Recording;

use Gibbon\Module\BigBlueButton\Recording\CacheTtlPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CacheTtlPolicy::class)]
final class CacheTtlPolicyTest extends TestCase
{
    /** @return array<string, array{0: bool, 1: bool, 2: string, 3: int}> */
    public static function cases(): array
    {
        $end = '2026-01-01 10:00:00';

        return [
            'failed wins over recordings' => [true, true, '2026-01-01 11:00:00', 30],
            'failed, none' => [false, true, '2026-01-01 11:00:00', 30],
            'has recordings' => [true, false, '2026-02-01 11:00:00', 300],
            'none, during lesson' => [false, false, '2026-01-01 09:00:00', 60],
            'none, exactly 6h after' => [false, false, '2026-01-01 16:00:00', 60],
            'none, 6h + 1s after' => [false, false, '2026-01-01 16:00:01', 900],
            'none, long after' => [false, false, '2026-03-01 00:00:00', 900],
        ];
    }

    #[DataProvider('cases')]
    public function testTtl(bool $has, bool $failed, string $now, int $expected): void
    {
        $end = new \DateTimeImmutable('2026-01-01 10:00:00', new \DateTimeZone('UTC'));
        $n = new \DateTimeImmutable($now, new \DateTimeZone('UTC'));

        self::assertSame($expected, (new CacheTtlPolicy())->ttlSeconds($has, $failed, $end, $n));
    }

    public function testConstants(): void
    {
        self::assertSame(30, CacheTtlPolicy::FAILED);
        self::assertSame(300, CacheTtlPolicy::HAS_RECORDINGS);
        self::assertSame(60, CacheTtlPolicy::PENDING);
        self::assertSame(900, CacheTtlPolicy::IDLE);
    }
}

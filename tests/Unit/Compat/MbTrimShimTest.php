<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Tests\Unit\Compat;

use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\TestCase;

/**
 * bigbluebutton-api-php 3.0 calls mb_trim() (PHP 8.4+) in its cookie-jar handling. In 3.0.0 that code is
 * unreachable (it reads the wrong jar column), but a library fix would make it fatal on PHP 8.2/8.3.
 */
final class MbTrimShimTest extends TestCase
{
    #[RequiresPhp('< 8.4')]
    public function testShimIsDefinedInTheLibraryNamespaceBeforePhp84(): void
    {
        self::assertTrue(\function_exists('BigBlueButton\\mb_trim'));
        self::assertSame('abc123', \BigBlueButton\mb_trim("  abc123\n\t"));
        self::assertSame('abc', \BigBlueButton\mb_trim('--abc--', '-'));
        self::assertSame('Ünïcödé', \BigBlueButton\mb_trim("\u{00A0} Ünïcödé "));
    }

    #[RequiresPhp('>= 8.4')]
    public function testNativeFunctionIsUsedFromPhp84(): void
    {
        self::assertTrue(\function_exists('mb_trim'));
        self::assertFalse(\function_exists('BigBlueButton\\mb_trim'));
    }
}

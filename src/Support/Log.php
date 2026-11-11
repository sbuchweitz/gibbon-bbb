<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Support;

use Gibbon\Module\BigBlueButton\Bbb\UrlRedactor;

/** Writes to the PHP error log (Gibbon's server log); never includes checksums or the secret. */
final class Log
{
    public static function error(string $context, \Throwable $error, string $secret = ''): void
    {
        error_log(sprintf('[BigBlueButton] %s: %s: %s', $context, $error::class, UrlRedactor::redact($error->getMessage(), $secret)));
    }
}

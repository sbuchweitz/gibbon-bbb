<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Bbb;

/**
 * Library exceptions embed the full checksummed API URL; strip anything that could replay a request.
 */
final class UrlRedactor
{
    public static function redact(string $message, string $secret = ''): string
    {
        $redacted = (string) preg_replace('/checksum=[^&\s]+/i', 'checksum=[redacted]', $message);

        return $secret === '' ? $redacted : str_replace($secret, '[redacted]', $redacted);
    }
}

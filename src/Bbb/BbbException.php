<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Bbb;

/**
 * Any failure talking to the BigBlueButton server: transport errors, FAILED return codes or unparsable XML.
 * Messages never contain checksummed URLs (see UrlRedactor).
 */
final class BbbException extends \RuntimeException
{
    public function __construct(string $message, private readonly string $messageKey = '', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function messageKey(): string
    {
        return $this->messageKey;
    }
}

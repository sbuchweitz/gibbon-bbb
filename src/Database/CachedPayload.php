<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Database;

final readonly class CachedPayload
{
    public function __construct(public string $payload, public \DateTimeImmutable $fetchedAt)
    {
    }
}

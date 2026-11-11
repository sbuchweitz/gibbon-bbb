<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Service;

/** Someone joining a lesson's meeting. */
final readonly class Participant
{
    public function __construct(public int $personID, public string $fullName, public bool $moderator)
    {
    }
}

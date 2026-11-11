<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Bbb;

final readonly class JoinSpec
{
    public function __construct(
        public string $meetingID,
        public string $fullName,
        public bool $moderator,
        public string $userID,
    ) {
    }
}

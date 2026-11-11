<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Config;

/**
 * How "Join Class (New Tab)" opens the meeting.
 */
enum JoinMode: string
{
    /** A Gibbon page in the new tab that embeds BigBlueButton in an iframe. */
    case Embedded = 'embedded';

    /** The new tab goes straight to BigBlueButton (no iframe, no cookie relaxation needed). */
    case Direct = 'direct';

    public static function fromSetting(string $value): self
    {
        return self::tryFrom($value) ?? self::Embedded;
    }
}

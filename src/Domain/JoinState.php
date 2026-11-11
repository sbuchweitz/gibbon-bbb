<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Domain;

enum JoinState: string
{
    /** A join URL is available. */
    case Ready = 'ready';
    /** The window is open but no moderator has started the meeting yet. */
    case Waiting = 'waiting';
    case Upcoming = 'upcoming';
    case Closed = 'closed';
}

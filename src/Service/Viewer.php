<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Service;

/**
 * The signed-in person asking to see a lesson.
 *
 * @param string $highestAction getHighestGroupedAction() for /modules/Planner/planner_view_full.php
 */
final readonly class Viewer
{
    public function __construct(public int $personID, public string $highestAction)
    {
    }
}

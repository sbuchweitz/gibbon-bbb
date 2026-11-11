<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Domain;

/**
 * BigBlueButton role follows the person's role in the class or lesson, not their Gibbon role:
 * staff who can merely view every lesson join as viewers.
 */
final class MeetingRoleResolver
{
    private const MODERATOR_CLASS_ROLES = ['Teacher', 'Assistant', 'Technician'];
    private const MODERATOR_GUEST_ROLES = ['Guest Teacher', 'Guest Assistant', 'Guest Technician'];

    /**
     * @param string|null $classRole the person's gibbonCourseClassPerson.role for the lesson's class
     * @param string|null $guestRole the person's gibbonPlannerEntryGuest.role for the lesson
     */
    public function isModerator(?string $classRole, ?string $guestRole): bool
    {
        return in_array($classRole, self::MODERATOR_CLASS_ROLES, true)
            || in_array($guestRole, self::MODERATOR_GUEST_ROLES, true);
    }
}

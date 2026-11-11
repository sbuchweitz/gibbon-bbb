<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Service;

use Gibbon\Module\BigBlueButton\Database\LessonGateway;
use Gibbon\Module\BigBlueButton\Domain\LessonAccessFacts;
use Gibbon\Module\BigBlueButton\Domain\LessonAccessPolicy;
use Gibbon\Module\BigBlueButton\Domain\MeetingRoleResolver;

/**
 * Decides whether a viewer may see one lesson (same rules as the lesson page) and whether they moderate it.
 */
final class LessonAccess
{
    public function __construct(
        private readonly LessonGateway $lessons,
        private readonly LessonAccessPolicy $policy = new LessonAccessPolicy(),
        private readonly MeetingRoleResolver $roles = new MeetingRoleResolver(),
    ) {
    }

    /**
     * @param int|null $childID the child a parent is viewing the lesson for (?search=)
     * @param string $today Y-m-d in the school's timezone
     */
    public function check(int $plannerEntryID, Viewer $viewer, ?int $childID, string $today): LessonAccessResult
    {
        $lesson = $plannerEntryID > 0 ? $this->lessons->lesson($plannerEntryID) : null;
        if ($lesson === null) {
            return LessonAccessResult::denied();
        }

        $classRole = $this->lessons->classRole($lesson->classID, $viewer->personID);
        $guestRole = $this->lessons->guestRole($lesson->id, $viewer->personID);
        $viewsAsParent = $viewer->highestAction === 'Lesson Planner_viewMyChildrensClasses' && $childID !== null && $childID > 0;

        $facts = new LessonAccessFacts(
            highestAction: $viewer->highestAction,
            classRole: $classRole,
            guestRole: $guestRole,
            parentOfSubject: $viewsAsParent && $this->lessons->isParentOf($viewer->personID, (int) $childID, $today),
            subjectClassRole: $viewsAsParent ? $this->lessons->classRole($lesson->classID, (int) $childID) : null,
            subjectGuestRole: $viewsAsParent ? $this->lessons->guestRole($lesson->id, (int) $childID) : null,
            viewableStudents: $lesson->viewableStudents,
            viewableParents: $lesson->viewableParents,
        );

        if (!$this->policy->allows($facts)) {
            return LessonAccessResult::denied($lesson);
        }

        return new LessonAccessResult($lesson, true, $this->roles->isModerator($classRole, $guestRole));
    }
}

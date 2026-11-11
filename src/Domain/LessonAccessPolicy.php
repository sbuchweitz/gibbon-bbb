<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Domain;

/**
 * Replicates core's planner_view_full.php rules so the module's own pages (join, recording) grant
 * exactly the access the lesson page does. Class roles ending in " - Left" must be passed as null.
 */
final class LessonAccessPolicy
{
    public function allows(LessonAccessFacts $facts): bool
    {
        return match ($facts->highestAction) {
            'Lesson Planner_viewEditAllClasses',
            'Lesson Planner_viewAllEditMyClasses',
            'Lesson Planner_viewOnly' => true,
            'Lesson Planner_viewMyClasses' => $this->allowsOwnClass($facts),
            'Lesson Planner_viewMyChildrensClasses' => $this->allowsChildsClass($facts),
            default => false,
        };
    }

    private function allowsOwnClass(LessonAccessFacts $facts): bool
    {
        $role = $facts->classRole ?? $facts->guestRole;

        return $role !== null && !($role === 'Student' && !$facts->viewableStudents);
    }

    private function allowsChildsClass(LessonAccessFacts $facts): bool
    {
        $childRole = $facts->subjectClassRole ?? $facts->subjectGuestRole;

        return $facts->parentOfSubject
            && $childRole !== null
            && $facts->viewableParents
            && !($childRole === 'Student' && !$facts->viewableStudents);
    }
}

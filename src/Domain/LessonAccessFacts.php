<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Domain;

/**
 * Everything LessonAccessPolicy needs to decide whether a person may see one lesson.
 * "Subject" is the child a parent is viewing the lesson for (planner_view_full.php's ?search=).
 */
final readonly class LessonAccessFacts
{
    public function __construct(
        public string $highestAction,
        public ?string $classRole,
        public ?string $guestRole,
        public bool $parentOfSubject,
        public ?string $subjectClassRole,
        public ?string $subjectGuestRole,
        public bool $viewableStudents,
        public bool $viewableParents,
    ) {
    }
}

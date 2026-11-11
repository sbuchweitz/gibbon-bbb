<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Tests\Unit\Domain;

use Gibbon\Module\BigBlueButton\Domain\LessonAccessFacts;
use Gibbon\Module\BigBlueButton\Domain\LessonAccessPolicy;
use Gibbon\Module\BigBlueButton\Domain\MeetingRoleResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Mirrors the access rules of core's modules/Planner/planner_view_full.php.
 */
#[CoversClass(LessonAccessPolicy::class)]
#[CoversClass(LessonAccessFacts::class)]
#[CoversClass(MeetingRoleResolver::class)]
final class LessonAccessPolicyTest extends TestCase
{
    /** @param array<string, mixed> $overrides */
    private static function facts(array $overrides): LessonAccessFacts
    {
        return new LessonAccessFacts(...$overrides + [
            'highestAction' => 'Lesson Planner_viewMyClasses',
            'classRole' => null,
            'guestRole' => null,
            'parentOfSubject' => false,
            'subjectClassRole' => null,
            'subjectGuestRole' => null,
            'viewableStudents' => true,
            'viewableParents' => true,
        ]);
    }

    /** @return iterable<string, array{array<string, mixed>, bool}> */
    public static function decisions(): iterable
    {
        yield 'staff with view/edit all' => [['highestAction' => 'Lesson Planner_viewEditAllClasses'], true];
        yield 'staff with view all, edit mine' => [['highestAction' => 'Lesson Planner_viewAllEditMyClasses'], true];
        yield 'view only' => [['highestAction' => 'Lesson Planner_viewOnly'], true];
        yield 'view-all ignores student visibility' => [['highestAction' => 'Lesson Planner_viewOnly', 'viewableStudents' => false], true];
        yield 'no planner action' => [['highestAction' => ''], false];
        yield 'unknown action' => [['highestAction' => 'Lesson Planner_somethingElse', 'classRole' => 'Teacher'], false];

        yield 'class teacher' => [['classRole' => 'Teacher'], true];
        yield 'enrolled student' => [['classRole' => 'Student'], true];
        yield 'student, lesson hidden from students' => [['classRole' => 'Student', 'viewableStudents' => false], false];
        yield 'teacher, lesson hidden from students' => [['classRole' => 'Teacher', 'viewableStudents' => false], true];
        yield 'not in class' => [[], false];
        yield 'guest' => [['guestRole' => 'Guest Student'], true];
        yield 'guest when hidden from students' => [['guestRole' => 'Guest Student', 'viewableStudents' => false], true];

        $parent = ['highestAction' => 'Lesson Planner_viewMyChildrensClasses', 'parentOfSubject' => true];
        yield 'parent of enrolled child' => [$parent + ['subjectClassRole' => 'Student'], true];
        yield 'parent of guest child' => [$parent + ['subjectGuestRole' => 'Guest Student'], true];
        yield 'parent, child not in class' => [$parent, false];
        yield 'not that child\'s parent' => [['parentOfSubject' => false] + $parent + ['subjectClassRole' => 'Student'], false];
        yield 'parent, hidden from parents' => [$parent + ['subjectClassRole' => 'Student', 'viewableParents' => false], false];
        yield 'parent, hidden from students' => [$parent + ['subjectClassRole' => 'Student', 'viewableStudents' => false], false];
    }

    /** @param array<string, mixed> $facts */
    #[DataProvider('decisions')]
    public function testDecision(array $facts, bool $allowed): void
    {
        self::assertSame($allowed, (new LessonAccessPolicy())->allows(self::facts($facts)));
    }

    /** @return iterable<string, array{?string, ?string, bool}> */
    public static function moderatorRoles(): iterable
    {
        yield 'teacher' => ['Teacher', null, true];
        yield 'assistant' => ['Assistant', null, true];
        yield 'technician' => ['Technician', null, true];
        yield 'student' => ['Student', null, false];
        yield 'parent' => ['Parent', null, false];
        yield 'left teacher' => ['Teacher - Left', null, false];
        yield 'guest teacher' => [null, 'Guest Teacher', true];
        yield 'guest assistant' => [null, 'Guest Assistant', true];
        yield 'guest technician' => [null, 'Guest Technician', true];
        yield 'guest student' => [null, 'Guest Student', false];
        yield 'nobody' => [null, null, false];
    }

    #[DataProvider('moderatorRoles')]
    public function testModeratorRoles(?string $classRole, ?string $guestRole, bool $expected): void
    {
        self::assertSame($expected, (new MeetingRoleResolver())->isModerator($classRole, $guestRole));
    }

    public function testModeratorFollowsTheLessonRoleNotViewAllRights(): void
    {
        $facts = self::facts(['highestAction' => 'Lesson Planner_viewEditAllClasses']);

        self::assertTrue((new LessonAccessPolicy())->allows($facts));
        self::assertFalse((new MeetingRoleResolver())->isModerator($facts->classRole, $facts->guestRole));
    }
}

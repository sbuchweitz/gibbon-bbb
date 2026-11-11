<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Service;

use Gibbon\Module\BigBlueButton\Domain\Lesson;

final readonly class LessonAccessResult
{
    public function __construct(public ?Lesson $lesson, public bool $allowed, public bool $moderator)
    {
    }

    public static function denied(?Lesson $lesson = null): self
    {
        return new self($lesson, false, false);
    }
}

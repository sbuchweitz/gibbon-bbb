<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Domain;

/**
 * The slice of a gibbonPlannerEntry row the module needs.
 */
final readonly class Lesson
{
    /**
     * @param array<array-key, mixed> $fields decoded gibbonPlannerEntry.fields (custom field ID => value)
     */
    public function __construct(
        public int $id,
        public int $classID,
        public string $name,
        public string $course,
        public string $class,
        public string $date,
        public string $timeStart,
        public string $timeEnd,
        public bool $viewableStudents,
        public bool $viewableParents,
        public array $fields,
    ) {
    }

    public function classLabel(): string
    {
        return $this->course.'.'.$this->class;
    }

    /** Custom field value by gibbonCustomFieldID (stored zero-padded, e.g. "0007"). */
    public function fieldValue(int $customFieldID): ?string
    {
        $value = $this->fields[sprintf('%04d', $customFieldID)] ?? $this->fields[$customFieldID] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    public function window(\DateTimeZone $timezone): LiveWindow
    {
        return LiveWindow::fromLesson($this->date, $this->timeStart, $this->timeEnd, $timezone);
    }
}

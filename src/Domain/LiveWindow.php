<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Domain;

/**
 * When a lesson's live class can be joined: from LEAD_MINUTES before it starts to TAIL_MINUTES after it ends.
 */
final readonly class LiveWindow
{
    public const LEAD_MINUTES = 10;
    public const TAIL_MINUTES = 10;
    /** Extra time before BigBlueButton force-ends a meeting, so overrunning classes are not cut off. */
    public const MEETING_BUFFER_MINUTES = 30;
    public const MIN_MEETING_MINUTES = 15;

    public function __construct(
        public \DateTimeImmutable $lessonStart,
        public \DateTimeImmutable $lessonEnd,
    ) {
        if ($lessonEnd < $lessonStart) {
            throw new \InvalidArgumentException('A lesson cannot end before it starts.');
        }
    }

    /**
     * @param string $date Y-m-d
     * @param string $timeStart H:i:s
     * @param string $timeEnd H:i:s; earlier than $timeStart means the lesson ends after midnight
     */
    public static function fromLesson(string $date, string $timeStart, string $timeEnd, \DateTimeZone $timezone): self
    {
        $start = self::parse($date, $timeStart, $timezone);
        $end = self::parse($date, $timeEnd, $timezone);

        return new self($start, $end < $start ? $end->modify('+1 day') : $end);
    }

    public function opensAt(): \DateTimeImmutable
    {
        return $this->lessonStart->modify(sprintf('-%d minutes', self::LEAD_MINUTES));
    }

    public function closesAt(): \DateTimeImmutable
    {
        return $this->lessonEnd->modify(sprintf('+%d minutes', self::TAIL_MINUTES));
    }

    public function state(\DateTimeImmutable $now): LiveState
    {
        return match (true) {
            $now < $this->opensAt() => LiveState::Upcoming,
            $now > $this->closesAt() => LiveState::Closed,
            default => LiveState::Open,
        };
    }

    /** BigBlueButton "duration" for a meeting created at $now: the rest of the window plus a buffer. */
    public function meetingDurationMinutes(\DateTimeImmutable $now): int
    {
        $remainingMinutes = (int) ceil(($this->closesAt()->getTimestamp() - $now->getTimestamp()) / 60);

        return max(self::MIN_MEETING_MINUTES, $remainingMinutes + self::MEETING_BUFFER_MINUTES);
    }

    private static function parse(string $date, string $time, \DateTimeZone $timezone): \DateTimeImmutable
    {
        $value = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $date.' '.$time, $timezone);
        $errors = \DateTimeImmutable::getLastErrors();
        if ($value === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new \InvalidArgumentException(sprintf('Invalid lesson date/time "%s %s".', $date, $time));
        }

        return $value;
    }
}

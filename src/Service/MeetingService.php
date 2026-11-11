<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Service;

use Gibbon\Module\BigBlueButton\Bbb\BbbApi;
use Gibbon\Module\BigBlueButton\Bbb\JoinSpec;
use Gibbon\Module\BigBlueButton\Bbb\MeetingSpec;
use Gibbon\Module\BigBlueButton\Config\Settings;
use Gibbon\Module\BigBlueButton\Database\MeetingGateway;
use Gibbon\Module\BigBlueButton\Domain\Clock;
use Gibbon\Module\BigBlueButton\Domain\JoinState;
use Gibbon\Module\BigBlueButton\Domain\Lesson;
use Gibbon\Module\BigBlueButton\Domain\LiveState;
use Gibbon\Module\BigBlueButton\Domain\MeetingIdFactory;

/**
 * Joining a lesson's live class. Only moderators create meetings; viewers wait until one exists.
 * BbbException from the API propagates to the caller.
 */
final class MeetingService
{
    private const MAX_NAME_LENGTH = 200;

    public function __construct(
        private readonly MeetingGateway $meetings,
        private readonly BbbApi $bbb,
        private readonly MeetingIdFactory $ids,
        private readonly Settings $settings,
        private readonly Clock $clock,
        private readonly \DateTimeZone $timezone,
        private readonly string $logoutUrl,
        private readonly string $originServerName,
    ) {
    }

    public function join(Lesson $lesson, Participant $participant): JoinDecision
    {
        $window = $lesson->window($this->timezone);
        $now = $this->clock->now();

        return match ($window->state($now)) {
            LiveState::Upcoming => JoinDecision::upcoming($window->opensAt()),
            LiveState::Closed => JoinDecision::closed(),
            LiveState::Open => $participant->moderator
                ? $this->startAndJoin($lesson, $participant, $window->meetingDurationMinutes($now))
                : $this->joinRunning($lesson, $participant),
        };
    }

    /** Join state without side effects (for the waiting page's polling). */
    public function status(Lesson $lesson, bool $moderator): JoinState
    {
        $state = $lesson->window($this->timezone)->state($this->clock->now());
        if ($state !== LiveState::Open) {
            return $state === LiveState::Upcoming ? JoinState::Upcoming : JoinState::Closed;
        }
        $record = $this->meetings->findByLesson($lesson->id);

        return $moderator || ($record !== null && $this->bbb->meetingExists($record->meetingID)) ? JoinState::Ready : JoinState::Waiting;
    }

    /**
     * Meeting IDs whose recordings belong to the lesson. A lesson that held a meeting keeps its recordings
     * whatever later happens to its Video Chat option. Pre-upgrade lessons fall back to their v1.x ID, like
     * v1.x itself, only while Video Chat is ticked (so old lessons without video never query BigBlueButton).
     *
     * @return list<string>
     */
    public function recordingMeetingIDs(Lesson $lesson, bool $hasVideoChat): array
    {
        $record = $this->meetings->findByLesson($lesson->id);
        if ($record !== null) {
            return $record->meetingIDs();
        }

        return $hasVideoChat && $this->settings->showLegacyRecordings && $this->isPreUpgrade($lesson)
            ? [MeetingIdFactory::legacy($lesson->id)]
            : [];
    }

    /** Whether the lesson has held a meeting since version 2.0. */
    public function hasMeeting(Lesson $lesson): bool
    {
        return $this->meetings->findByLesson($lesson->id) !== null;
    }

    private function startAndJoin(Lesson $lesson, Participant $participant, int $durationMinutes): JoinDecision
    {
        $record = $this->meetings->findByLesson($lesson->id) ?? $this->meetings->claim(
            $lesson->id,
            $this->ids->create($lesson->id),
            $this->isPreUpgrade($lesson) && $this->settings->showLegacyRecordings ? MeetingIdFactory::legacy($lesson->id) : null,
            $participant->personID,
            $this->clock->now(),
        );

        $this->bbb->createMeeting(new MeetingSpec(
            meetingID: $record->meetingID,
            name: mb_substr($lesson->classLabel().' – '.$lesson->name, 0, self::MAX_NAME_LENGTH),
            durationMinutes: $durationMinutes,
            logoutUrl: $this->logoutUrl,
            allowRequestsWithoutSession: $this->settings->allowRequestsWithoutSession(),
            metadata: [
                'bbb-origin' => 'Gibbon',
                'bbb-origin-server-name' => $this->originServerName,
                'gibbon-instance' => $this->ids->instanceHash,
                'gibbon-planner-entry' => (string) $lesson->id,
            ],
        ));

        return JoinDecision::ready($this->joinUrl($record->meetingID, $participant));
    }

    private function joinRunning(Lesson $lesson, Participant $participant): JoinDecision
    {
        $record = $this->meetings->findByLesson($lesson->id);
        if ($record === null || !$this->bbb->meetingExists($record->meetingID)) {
            return JoinDecision::waiting();
        }

        return JoinDecision::ready($this->joinUrl($record->meetingID, $participant));
    }

    private function joinUrl(string $meetingID, Participant $participant): string
    {
        $userID = $this->ids->instanceHash.'-'.$participant->personID;

        return $this->bbb->joinUrl(new JoinSpec($meetingID, $participant->fullName, $participant->moderator, $userID));
    }

    private function isPreUpgrade(Lesson $lesson): bool
    {
        return $lesson->id <= $this->settings->legacyMaxPlannerEntryID;
    }
}

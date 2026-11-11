<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Tests\Support;

use Gibbon\Module\BigBlueButton\Bbb\BbbApi;
use Gibbon\Module\BigBlueButton\Bbb\BbbException;
use Gibbon\Module\BigBlueButton\Bbb\JoinSpec;
use Gibbon\Module\BigBlueButton\Bbb\MeetingSpec;
use Gibbon\Module\BigBlueButton\Recording\Recording;

/** In-memory BbbApi for service tests. */
final class FakeBbbApi implements BbbApi
{
    /** @var array<string, MeetingSpec> */
    public array $meetings = [];
    /** @var list<Recording> */
    public array $recordings = [];
    /** @var list<list<string>> */
    public array $recordingCalls = [];
    public bool $failing = false;

    public function createMeeting(MeetingSpec $spec): void
    {
        $this->guard();
        $this->meetings[$spec->meetingID] ??= $spec;
    }

    public function meetingExists(string $meetingID): bool
    {
        $this->guard();

        return isset($this->meetings[$meetingID]);
    }

    public function joinUrl(JoinSpec $spec): string
    {
        return sprintf('https://bbb.test/join?meetingID=%s&role=%s&userID=%s&fullName=%s', $spec->meetingID, $spec->moderator ? 'MODERATOR' : 'VIEWER', $spec->userID, rawurlencode($spec->fullName));
    }

    public function recordings(array $meetingIDs): array
    {
        $this->recordingCalls[] = $meetingIDs;
        $this->guard();

        return array_values(array_filter($this->recordings, static fn (Recording $r): bool => in_array($r->meetingID, $meetingIDs, true)));
    }

    public function testConnection(): string
    {
        $this->guard();

        return '2.0';
    }

    private function guard(): void
    {
        if ($this->failing) {
            throw new BbbException('BBB is down', 'requestFailed');
        }
    }
}

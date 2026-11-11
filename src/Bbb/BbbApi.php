<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Bbb;

use Gibbon\Module\BigBlueButton\Recording\Recording;

/**
 * The BigBlueButton calls the module makes. Every method throws BbbException on any failure.
 */
interface BbbApi
{
    /** Creates the meeting, or does nothing if it already exists. */
    public function createMeeting(MeetingSpec $spec): void;

    public function meetingExists(string $meetingID): bool;

    /** Builds the signed join URL locally (no HTTP request). */
    public function joinUrl(JoinSpec $spec): string;

    /**
     * @param list<string> $meetingIDs
     * @return list<Recording>
     */
    public function recordings(array $meetingIDs): array;

    /** Calls the API with the configured secret and returns the server's API version. */
    public function testConnection(): string;
}

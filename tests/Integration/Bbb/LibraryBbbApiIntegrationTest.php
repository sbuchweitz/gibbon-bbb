<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Tests\Integration\Bbb;

use Gibbon\Module\BigBlueButton\Bbb\BbbException;
use Gibbon\Module\BigBlueButton\Bbb\JoinSpec;
use Gibbon\Module\BigBlueButton\Bbb\LibraryBbbApi;
use Gibbon\Module\BigBlueButton\Bbb\MeetingSpec;
use Gibbon\Module\BigBlueButton\Tests\Support\MockBbb;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Runs the real bundled library against the mock BBB server from tests/harness (BBB_MOCK_URL).
 */
#[CoversClass(LibraryBbbApi::class)]
final class LibraryBbbApiIntegrationTest extends TestCase
{
    private MockBbb $mock;

    protected function setUp(): void
    {
        $this->mock = MockBbb::fromEnvironment();
        $this->mock->reset();
    }

    private function api(?string $secret = null, ?string $url = null): LibraryBbbApi
    {
        return LibraryBbbApi::connect($url ?? $this->mock->apiUrl(), $secret ?? $this->mock->secret());
    }

    private static function spec(string $meetingID = 'gibbon-0123456789ab-7-cafebabe'): MeetingSpec
    {
        return new MeetingSpec($meetingID, 'Science 8B', 60, 'https://gibbon.test/ended', true, ['bbb-origin' => 'Gibbon']);
    }

    public function testCreatesAMeetingThatThenExists(): void
    {
        $api = $this->api();
        self::assertFalse($api->meetingExists('gibbon-0123456789ab-7-cafebabe'));

        $api->createMeeting(self::spec());
        $api->createMeeting(self::spec()); // idempotent: BBB answers duplicateWarning

        self::assertTrue($api->meetingExists('gibbon-0123456789ab-7-cafebabe'));
        $create = $this->mock->lastRequest('create');
        self::assertSame('false', $create['params']['autoStartRecording']);
        self::assertSame('true', $create['params']['allowRequestsWithoutSession']);
    }

    public function testJoinUrlIsAcceptedByTheServer(): void
    {
        $api = $this->api();
        $api->createMeeting(self::spec());

        $location = $this->mock->followJoin($api->joinUrl(new JoinSpec('gibbon-0123456789ab-7-cafebabe', 'Ms Teacher', true, 'u-1')));

        self::assertStringContainsString('/html5client/join?sessionToken=', $location);
    }

    public function testListsRecordingsForSeveralMeetingIdsInOneCall(): void
    {
        $this->mock->seedRecording('planner7', ['presentation']);
        $this->mock->seedRecording('gibbon-0123456789ab-7-cafebabe', ['presentation', 'video']);
        $this->mock->seedRecording('someone-else', ['presentation']);

        $recordings = $this->api()->recordings(['gibbon-0123456789ab-7-cafebabe', 'planner7']);

        self::assertCount(2, $recordings);
        self::assertEqualsCanonicalizing(['planner7', 'gibbon-0123456789ab-7-cafebabe'], array_map(static fn ($r) => $r->meetingID, $recordings));
    }

    public function testNoRecordingsIsAnEmptyList(): void
    {
        self::assertSame([], $this->api()->recordings(['nothing-here']));
        self::assertSame([], $this->api()->recordings([]));
    }

    public function testWrongSecretRaisesARedactedException(): void
    {
        try {
            $this->api('wrong-secret-value')->createMeeting(self::spec());
            self::fail('Expected BbbException');
        } catch (BbbException $e) {
            self::assertSame('checksumError', $e->messageKey());
            self::assertStringNotContainsString('wrong-secret-value', $e->getMessage());
        }
    }

    public function testUnreachableServerRaisesWithinTheTimeout(): void
    {
        $started = microtime(true);
        try {
            $this->api(null, 'http://10.255.255.1/bigbluebutton/')->meetingExists('x');
            self::fail('Expected BbbException');
        } catch (BbbException $e) {
            self::assertStringNotContainsString('checksum=', $e->getMessage());
            self::assertLessThan(LibraryBbbApi::TOTAL_TIMEOUT_SECONDS + 2, microtime(true) - $started);
        }
    }

    public function testSessionCookieOnApiResponsesIsHarmless(): void
    {
        // bbb-web can answer API calls with a JSESSIONID cookie; the library must keep working (see MbTrimShimTest).
        $this->mock->configure(['setJSessionIdOnApi' => true]);

        $this->api()->createMeeting(self::spec());

        self::assertTrue($this->api()->meetingExists('gibbon-0123456789ab-7-cafebabe'));
    }

    public function testTestConnectionReportsTheServerVersion(): void
    {
        self::assertNotSame('', $this->api()->testConnection());
    }
}

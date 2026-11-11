<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Tests\Unit\Bbb;

use Gibbon\Module\BigBlueButton\Bbb\JoinSpec;
use Gibbon\Module\BigBlueButton\Bbb\LibraryBbbApi;
use Gibbon\Module\BigBlueButton\Bbb\MeetingSpec;
use Gibbon\Module\BigBlueButton\Bbb\UrlRedactor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LibraryBbbApi::class)]
#[CoversClass(MeetingSpec::class)]
#[CoversClass(JoinSpec::class)]
#[CoversClass(UrlRedactor::class)]
final class LibraryBbbApiTest extends TestCase
{
    /** @return array<string, string> */
    private static function query(string $httpQuery): array
    {
        parse_str($httpQuery, $params);

        return array_map('strval', $params);
    }

    private static function meeting(bool $allowWithoutSession = false): MeetingSpec
    {
        return new MeetingSpec(
            meetingID: 'gibbon-0123456789ab-42-deadbeef',
            name: 'Maths 7A – Fractions',
            durationMinutes: 95,
            logoutUrl: 'https://gibbon.school.org/modules/BigBlueButton/bbb_ended.php',
            allowRequestsWithoutSession: $allowWithoutSession,
            metadata: ['bbb-origin' => 'Gibbon', 'gibbon-instance' => '0123456789ab', 'gibbon-planner-entry' => '42'],
        );
    }

    public function testCreateRecordsOnlyWhenAModeratorStartsIt(): void
    {
        $params = self::query(LibraryBbbApi::createParameters(self::meeting())->getHTTPQuery());

        self::assertSame('true', $params['record']);
        self::assertSame('false', $params['autoStartRecording']);
        self::assertSame('true', $params['allowStartStopRecording']);
    }

    public function testCreateCarriesIdentityDurationLogoutAndMetadata(): void
    {
        $params = self::query(LibraryBbbApi::createParameters(self::meeting())->getHTTPQuery());

        self::assertSame('gibbon-0123456789ab-42-deadbeef', $params['meetingID']);
        self::assertSame('Maths 7A – Fractions', $params['name']);
        self::assertSame('95', $params['duration']);
        self::assertSame('https://gibbon.school.org/modules/BigBlueButton/bbb_ended.php', $params['logoutURL']);
        self::assertSame('Gibbon', $params['meta_bbb-origin']);
        self::assertSame('0123456789ab', $params['meta_gibbon-instance']);
        self::assertSame('42', $params['meta_gibbon-planner-entry']);
    }

    public function testSessionCookieCheckIsRelaxedPerMeetingOnlyWhenAsked(): void
    {
        $strict = self::query(LibraryBbbApi::createParameters(self::meeting(false))->getHTTPQuery());
        $relaxed = self::query(LibraryBbbApi::createParameters(self::meeting(true))->getHTTPQuery());

        self::assertArrayNotHasKey('allowRequestsWithoutSession', $strict);
        self::assertSame('true', $relaxed['allowRequestsWithoutSession']);
    }

    public function testJoinUsesRoleBasedJoinWithAStableUserId(): void
    {
        $moderator = self::query(LibraryBbbApi::joinParameters(new JoinSpec('m-1', 'Ms Teacher', true, '0123456789ab-0000012'))->getHTTPQuery());
        $viewer = self::query(LibraryBbbApi::joinParameters(new JoinSpec('m-1', 'Sam Student', false, '0123456789ab-0000099'))->getHTTPQuery());

        self::assertSame('MODERATOR', $moderator['role']);
        self::assertSame('VIEWER', $viewer['role']);
        self::assertSame('Sam Student', $viewer['fullName']);
        self::assertSame('0123456789ab-0000099', $viewer['userID']);
        self::assertSame('true', $viewer['redirect']);
        self::assertArrayNotHasKey('password', $viewer);
    }

    public function testJoinUrlIsSignedForTheConfiguredServer(): void
    {
        $api = LibraryBbbApi::connect('https://bbb.school.org/bigbluebutton/', 'secret-value-123');
        $url = $api->joinUrl(new JoinSpec('m-1', 'Sam Student', false, 'u-1'));

        self::assertStringStartsWith('https://bbb.school.org/bigbluebutton/api/join?', $url);
        self::assertMatchesRegularExpression('/&checksum=[0-9a-f]{64}$/', $url);
    }

    public function testRedactorRemovesChecksumsAndSecrets(): void
    {
        $message = 'Bad response, HTTP code: 500, url: https://bbb.school.org/bigbluebutton/api/create?meetingID=x&checksum=abc123DEF456 secret-value-123';

        $redacted = UrlRedactor::redact($message, 'secret-value-123');

        self::assertStringNotContainsString('abc123DEF456', $redacted);
        self::assertStringNotContainsString('secret-value-123', $redacted);
        self::assertStringContainsString('checksum=[redacted]', $redacted);
    }
}

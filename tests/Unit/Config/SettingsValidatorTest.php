<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Tests\Unit\Config;

use Gibbon\Module\BigBlueButton\Config\JoinMode;
use Gibbon\Module\BigBlueButton\Config\Settings;
use Gibbon\Module\BigBlueButton\Config\SettingsValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SettingsValidator::class)]
final class SettingsValidatorTest extends TestCase
{
    private const SECRET = 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0';

    private function current(string $secret = ''): Settings
    {
        return Settings::fromStore(['bigBlueButtonCredentials' => $secret]);
    }

    /** @param array<string, string> $overrides */
    private function input(array $overrides = []): array
    {
        return $overrides + [
            'enableBigBlueButton' => 'Y',
            'bigBlueButtonURL' => 'https://bbb.school.org/bigbluebutton/',
            'bigBlueButtonCredentials' => self::SECRET,
            'joinMode' => 'embedded',
            'embeddedAllowWithoutSession' => 'Y',
            'recordingHostOverride' => '',
            'showLegacyRecordings' => 'Y',
        ];
    }

    public function testValidInputProducesNormalisedValues(): void
    {
        $result = (new SettingsValidator())->validate($this->input(), $this->current());

        self::assertTrue($result->isValid(), implode(', ', $result->errors));
        self::assertSame([
            'enableBigBlueButton' => 'Y',
            'bigBlueButtonURL' => 'https://bbb.school.org/bigbluebutton/',
            'joinMode' => 'embedded',
            'embeddedAllowWithoutSession' => 'Y',
            'showLegacyRecordings' => 'Y',
            'recordingHostOverride' => '',
            'bigBlueButtonCredentials' => self::SECRET,
        ], $result->values);
    }

    public function testBlankSecretKeepsTheStoredOne(): void
    {
        $result = (new SettingsValidator())->validate($this->input(['bigBlueButtonCredentials' => '']), $this->current(self::SECRET));

        self::assertTrue($result->isValid());
        self::assertArrayNotHasKey('bigBlueButtonCredentials', $result->values);
    }

    public function testEnablingWithoutAnySecretFails(): void
    {
        $result = (new SettingsValidator())->validate($this->input(['bigBlueButtonCredentials' => '']), $this->current());

        self::assertFalse($result->isValid());
        self::assertContains('Enter the BigBlueButton shared secret.', $result->errors);
    }

    public function testDisablingDoesNotRequireServerDetails(): void
    {
        $input = $this->input(['enableBigBlueButton' => 'N', 'bigBlueButtonURL' => '', 'bigBlueButtonCredentials' => '']);
        $result = (new SettingsValidator())->validate($input, $this->current());

        self::assertTrue($result->isValid(), implode(', ', $result->errors));
        self::assertSame('N', $result->values['enableBigBlueButton']);
        self::assertSame('', $result->values['bigBlueButtonURL']);
    }

    /** @return iterable<string, array{array<string, string>, string}> */
    public static function invalidInputs(): iterable
    {
        yield 'enable flag' => [['enableBigBlueButton' => 'yes'], 'Choose whether BigBlueButton is enabled.'];
        yield 'url scheme' => [['bigBlueButtonURL' => 'ftp://bbb.school.org/bigbluebutton/'], 'Enter a valid http(s) BigBlueButton server URL.'];
        yield 'url userinfo' => [['bigBlueButtonURL' => 'https://admin:pw@bbb.school.org/'], 'Enter a valid http(s) BigBlueButton server URL.'];
        yield 'url query' => [['bigBlueButtonURL' => 'https://bbb.school.org/bigbluebutton/?x=1'], 'Enter a valid http(s) BigBlueButton server URL.'];
        yield 'url missing' => [['bigBlueButtonURL' => ''], 'Enter a valid http(s) BigBlueButton server URL.'];
        yield 'secret too short' => [['bigBlueButtonCredentials' => 'short'], 'The shared secret must be 16–256 printable characters without spaces.'];
        yield 'secret under 16' => [['bigBlueButtonCredentials' => 'fifteen-chars-x'], 'The shared secret must be 16–256 printable characters without spaces.'];
        yield 'legacy toggle' => [['showLegacyRecordings' => 'maybe'], 'Choose whether to show recordings made before version 2.0.'];
        yield 'secret with space' => [['bigBlueButtonCredentials' => 'abc def ghi jkl'], 'The shared secret must be 16–256 printable characters without spaces.'];
        yield 'join mode' => [['joinMode' => 'popup'], 'Choose a valid join mode.'];
        yield 'session flag' => [['embeddedAllowWithoutSession' => 'maybe'], 'Choose whether embedded meetings may skip the session cookie check.'];
        yield 'override path' => [['recordingHostOverride' => 'https://bbb.school.org/playback'], 'The recording host override must be an origin such as https://bbb.school.org'];
        yield 'override scheme' => [['recordingHostOverride' => 'javascript:alert(1)'], 'The recording host override must be an origin such as https://bbb.school.org'];
        yield 'override host' => [['recordingHostOverride' => 'https://exa mple.org'], 'The recording host override must be an origin such as https://bbb.school.org'];
    }

    /** @param array<string, string> $overrides */
    #[DataProvider('invalidInputs')]
    public function testRejectsInvalidInput(array $overrides, string $expectedError): void
    {
        $result = (new SettingsValidator())->validate($this->input($overrides), $this->current());

        self::assertFalse($result->isValid());
        self::assertContains($expectedError, $result->errors);
    }

    public function testMissingFieldsAreTreatedAsInvalidNotFatal(): void
    {
        $result = (new SettingsValidator())->validate([], $this->current());

        self::assertFalse($result->isValid());
    }

    /** @return iterable<string, array{string, string}> */
    public static function serverUrls(): iterable
    {
        yield 'already canonical' => ['https://bbb.school.org/bigbluebutton/', 'https://bbb.school.org/bigbluebutton/'];
        yield 'no trailing slash' => ['https://bbb.school.org/bigbluebutton', 'https://bbb.school.org/bigbluebutton/'];
        yield 'api suffix' => ['https://bbb.school.org/bigbluebutton/api/', 'https://bbb.school.org/bigbluebutton/'];
        yield 'host only' => ['https://bbb.school.org', 'https://bbb.school.org/bigbluebutton/'];
        yield 'no scheme' => ['bbb.school.org/bigbluebutton/', 'https://bbb.school.org/bigbluebutton/'];
        yield 'upper case host and port' => ['HTTPS://BBB.School.org:8443/bigbluebutton/', 'https://bbb.school.org:8443/bigbluebutton/'];
        yield 'whitespace' => ['  https://bbb.school.org/bigbluebutton/  ', 'https://bbb.school.org/bigbluebutton/'];
        yield 'ipv6' => ['http://[fd00::5]:8080/bigbluebutton', 'http://[fd00::5]:8080/bigbluebutton/'];
        yield 'scalelite path' => ['https://lb.school.org/bigbluebutton/', 'https://lb.school.org/bigbluebutton/'];
    }

    #[DataProvider('serverUrls')]
    public function testNormalisesServerUrls(string $input, string $expected): void
    {
        self::assertSame($expected, SettingsValidator::normaliseServerUrl($input));
    }

    /** @return iterable<string, array{string, ?string}> */
    public static function origins(): iterable
    {
        yield 'blank' => ['', ''];
        yield 'plain' => ['https://bbb.school.org', 'https://bbb.school.org'];
        yield 'trailing slash' => ['https://bbb.school.org/', 'https://bbb.school.org'];
        yield 'no scheme' => ['bbb.school.org', 'https://bbb.school.org'];
        yield 'port' => ['http://192.0.2.10:2006', 'http://192.0.2.10:2006'];
        yield 'path' => ['https://bbb.school.org/x', null];
        yield 'query' => ['https://bbb.school.org?x', null];
        yield 'ftp' => ['ftp://bbb.school.org', null];
    }

    #[DataProvider('origins')]
    public function testNormalisesOrigins(string $input, ?string $expected): void
    {
        self::assertSame($expected, SettingsValidator::normaliseOrigin($input));
    }

    public function testJoinModeEnumMatchesStoredValues(): void
    {
        self::assertSame(JoinMode::Direct, JoinMode::fromSetting('direct'));
        self::assertSame(JoinMode::Embedded, JoinMode::fromSetting('anything-else'));
    }
}

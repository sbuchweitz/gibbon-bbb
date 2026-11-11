<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Tests\Unit\Config;

use Gibbon\Module\BigBlueButton\Config\JoinMode;
use Gibbon\Module\BigBlueButton\Config\Settings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Settings::class)]
#[CoversClass(JoinMode::class)]
final class SettingsTest extends TestCase
{
    public function testDefaultsForAFreshInstall(): void
    {
        $settings = Settings::fromStore([]);

        self::assertFalse($settings->enabled);
        self::assertSame('', $settings->serverUrl);
        self::assertSame('', $settings->secret);
        self::assertSame(JoinMode::Embedded, $settings->joinMode);
        self::assertFalse($settings->embeddedAllowWithoutSession, 'fails closed when the row is missing');
        self::assertNull($settings->recordingHostOverride);
        self::assertSame(0, $settings->legacyMaxPlannerEntryID);
        self::assertTrue($settings->showLegacyRecordings);
        self::assertFalse($settings->secretRotationRequired);
        self::assertFalse($settings->isConfigured());
    }

    public function testReadsStoredValues(): void
    {
        $settings = Settings::fromStore([
            'enableBigBlueButton' => 'Y',
            'bigBlueButtonURL' => 'https://bbb.school.org/bigbluebutton',
            'bigBlueButtonCredentials' => 'secret-value-123',
            'joinMode' => 'direct',
            'embeddedAllowWithoutSession' => 'N',
            'recordingHostOverride' => 'https://bbb.school.org',
            'legacyMaxPlannerEntryID' => '00000123',
            'showLegacyRecordings' => 'N',
            'secretRotationRequired' => 'Y',
        ]);

        self::assertTrue($settings->enabled);
        self::assertSame('https://bbb.school.org/bigbluebutton/', $settings->serverUrl);
        self::assertSame('secret-value-123', $settings->secret);
        self::assertSame(JoinMode::Direct, $settings->joinMode);
        self::assertFalse($settings->embeddedAllowWithoutSession);
        self::assertSame('https://bbb.school.org', $settings->recordingHostOverride);
        self::assertSame(123, $settings->legacyMaxPlannerEntryID);
        self::assertFalse($settings->showLegacyRecordings);
        self::assertTrue($settings->secretRotationRequired);
        self::assertTrue($settings->isConfigured());
    }

    public function testInvalidStoredUrlIsTreatedAsNotConfigured(): void
    {
        $settings = Settings::fromStore(['enableBigBlueButton' => 'Y', 'bigBlueButtonURL' => 'not a url', 'bigBlueButtonCredentials' => 'secret-value-123']);

        self::assertSame('', $settings->serverUrl);
        self::assertFalse($settings->isConfigured());
    }

    public function testInvalidOverrideIsIgnored(): void
    {
        self::assertNull(Settings::fromStore(['recordingHostOverride' => 'javascript:alert(1)'])->recordingHostOverride);
    }

    public function testSecretIsNotExposedByDebugOutput(): void
    {
        $settings = Settings::fromStore(['bigBlueButtonCredentials' => 'secret-value-123']);

        self::assertStringNotContainsString('secret-value-123', print_r($settings, true));
        self::assertStringNotContainsString('secret-value-123', var_export($settings->__debugInfo(), true));
    }
}

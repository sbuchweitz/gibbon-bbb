<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Tests\Integration\Config;

use Gibbon\Module\BigBlueButton\Config\SettingsProcess;
use Gibbon\Module\BigBlueButton\Config\SettingsRepository;
use Gibbon\Module\BigBlueButton\Tests\Support\MockBbb;
use Gibbon\Module\BigBlueButton\Tests\Support\TestDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SettingsProcess::class)]
#[CoversClass(SettingsRepository::class)]
final class SettingsProcessTest extends TestCase
{
    private \PDO $pdo;
    private MockBbb $mock;

    protected function setUp(): void
    {
        $this->mock = MockBbb::fromEnvironment();
        $this->pdo = TestDatabase::connect();
        TestDatabase::seedSettings($this->pdo, ['enableBigBlueButton' => 'N', 'bigBlueButtonURL' => '', 'bigBlueButtonCredentials' => '', 'joinMode' => 'embedded', 'embeddedAllowWithoutSession' => 'Y', 'recordingHostOverride' => '', 'showLegacyRecordings' => 'Y', 'secretRotationRequired' => 'Y', 'legacyMaxPlannerEntryID' => '42']);
        $this->pdo->exec("INSERT INTO gibbonCustomField (context, name, active) VALUES ('Lesson Plan', 'Video Chat', 'N'), ('Lesson Plan', 'Presentation Only', 'N'), ('Lesson Plan', 'Other', 'N')");
    }

    /** @param array<string, string> $overrides */
    private function post(array $overrides = []): array
    {
        return $overrides + [
            'enableBigBlueButton' => 'Y',
            'bigBlueButtonURL' => $this->mock->apiUrl(),
            'bigBlueButtonCredentials' => $this->mock->secret(),
            'joinMode' => 'embedded',
            'embeddedAllowWithoutSession' => 'Y',
            'recordingHostOverride' => '',
            'showLegacyRecordings' => 'Y',
        ];
    }

    /** @return array<string, string> */
    private function activeFields(): array
    {
        return $this->pdo->query("SELECT name, active FROM gibbonCustomField ORDER BY name")->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    public function testSavesValidSettingsActivatesLessonFieldsAndTestsTheConnection(): void
    {
        $outcome = (new SettingsProcess($this->pdo))->handle($this->post());

        self::assertSame('success0', $outcome->returnCode, implode(', ', $outcome->errors));
        self::assertStringContainsString('Connected to BigBlueButton', (string) $outcome->message);
        $settings = (new SettingsRepository($this->pdo))->load();
        self::assertTrue($settings->isConfigured());
        self::assertSame(42, $settings->legacyMaxPlannerEntryID, 'not editable from the form');
        self::assertFalse($settings->secretRotationRequired, 'saving a new secret clears the upgrade warning');
        self::assertSame(['Other' => 'N', 'Presentation Only' => 'Y', 'Video Chat' => 'Y'], $this->activeFields());
    }

    public function testBlankSecretKeepsTheSavedOne(): void
    {
        (new SettingsProcess($this->pdo))->handle($this->post());
        $outcome = (new SettingsProcess($this->pdo))->handle($this->post(['bigBlueButtonCredentials' => '']));

        self::assertSame('success0', $outcome->returnCode);
        self::assertSame($this->mock->secret(), (new SettingsRepository($this->pdo))->load()->secret);
    }

    public function testKeepingTheOldSecretKeepsTheRotationWarning(): void
    {
        $this->pdo->exec("UPDATE gibbonSetting SET value='an-old-secret-value' WHERE name='bigBlueButtonCredentials'");
        (new SettingsProcess($this->pdo))->handle($this->post(['bigBlueButtonCredentials' => '']));

        self::assertTrue((new SettingsRepository($this->pdo))->load()->secretRotationRequired);
    }

    public function testInvalidInputIsRejectedAndNothingIsStored(): void
    {
        $outcome = (new SettingsProcess($this->pdo))->handle($this->post(['bigBlueButtonURL' => 'ftp://nope']));

        self::assertSame('error3', $outcome->returnCode);
        self::assertNotEmpty($outcome->errors);
        self::assertFalse((new SettingsRepository($this->pdo))->load()->enabled);
    }

    public function testSavesButWarnsWhenTheServerRejectsTheSecret(): void
    {
        $outcome = (new SettingsProcess($this->pdo))->handle($this->post(['bigBlueButtonCredentials' => 'not-the-right-secret']));

        self::assertSame('warning1', $outcome->returnCode);
        self::assertStringContainsString('connection test failed', (string) $outcome->message);
        self::assertStringNotContainsString('not-the-right-secret', (string) $outcome->message);
        self::assertTrue((new SettingsRepository($this->pdo))->load()->enabled);
    }

    public function testDisablingDeactivatesLessonFieldsWithoutTestingTheConnection(): void
    {
        (new SettingsProcess($this->pdo))->handle($this->post());
        $outcome = (new SettingsProcess($this->pdo))->handle($this->post(['enableBigBlueButton' => 'N']));

        self::assertSame('success0', $outcome->returnCode);
        self::assertNull($outcome->message);
        self::assertSame(['Other' => 'N', 'Presentation Only' => 'N', 'Video Chat' => 'N'], $this->activeFields());
    }
}

<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Config;

use Gibbon\Module\BigBlueButton\Database\LessonGateway;

/**
 * Reads and writes the module's rows in gibbonSetting (scope "BigBlueButton").
 */
final class SettingsRepository
{
    /** Settings the admin form may change; legacyMaxPlannerEntryID is written only by the upgrade. */
    private const EDITABLE = [
        'enableBigBlueButton',
        'bigBlueButtonURL',
        'bigBlueButtonCredentials',
        'joinMode',
        'embeddedAllowWithoutSession',
        'recordingHostOverride',
        'showLegacyRecordings',
    ];

    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function load(): Settings
    {
        $statement = $this->pdo->prepare('SELECT name, value FROM gibbonSetting WHERE scope=:scope');
        $statement->execute(['scope' => Settings::SCOPE]);
        $stored = [];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $stored[(string) $row['name']] = $row['value'] === null ? null : (string) $row['value'];
        }

        return Settings::fromStore($stored);
    }

    /** @param array<string, string> $values validated values (see SettingsValidator) */
    public function save(array $values): void
    {
        $statement = $this->pdo->prepare('UPDATE gibbonSetting SET value=:value WHERE scope=:scope AND name=:name');
        $this->pdo->beginTransaction();
        try {
            foreach (array_intersect_key($values, array_flip(self::EDITABLE)) as $name => $value) {
                $statement->execute(['value' => $value, 'scope' => Settings::SCOPE, 'name' => $name]);
            }
            if (isset($values['bigBlueButtonCredentials'])) {
                $statement->execute(['value' => 'N', 'scope' => Settings::SCOPE, 'name' => 'secretRotationRequired']);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** The lesson edit form only shows the module's custom fields while BigBlueButton is enabled. */
    public function setLessonFieldsActive(bool $active): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE gibbonCustomField SET active=:active WHERE context='Lesson Plan' AND name IN (:videoChat, :presentationOnly)"
        );
        $statement->execute([
            'active' => $active ? 'Y' : 'N',
            'videoChat' => LessonGateway::VIDEO_CHAT_FIELD,
            'presentationOnly' => LessonGateway::PRESENTATION_ONLY_FIELD,
        ]);
    }
}

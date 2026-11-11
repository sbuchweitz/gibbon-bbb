<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Config;

use Gibbon\Module\BigBlueButton\Bbb\BbbApi;
use Gibbon\Module\BigBlueButton\Bbb\BbbException;
use Gibbon\Module\BigBlueButton\Bbb\LibraryBbbApi;

/**
 * Handles the settings form: validate, store, toggle the lesson fields, then test the connection.
 */
final class SettingsProcess
{
    /** @var \Closure(Settings): BbbApi */
    private readonly \Closure $connect;

    /** @param (\Closure(Settings): BbbApi)|null $connect */
    public function __construct(
        private readonly \PDO $pdo,
        ?\Closure $connect = null,
        private readonly SettingsValidator $validator = new SettingsValidator(),
    ) {
        $this->connect = $connect ?? static fn (Settings $s): BbbApi => LibraryBbbApi::connect($s->serverUrl, $s->secret);
    }

    /** @param array<string, mixed> $post */
    public function handle(array $post): SettingsOutcome
    {
        $repository = new SettingsRepository($this->pdo);
        $result = $this->validator->validate($post, $repository->load());
        if (!$result->isValid()) {
            return new SettingsOutcome('error3', $result->errors);
        }

        $repository->save($result->values);
        $repository->setLessonFieldsActive($result->values['enableBigBlueButton'] === 'Y');
        $settings = $repository->load();
        if (!$settings->enabled) {
            return new SettingsOutcome('success0');
        }

        try {
            $version = ($this->connect)($settings)->testConnection();

            return new SettingsOutcome('success0', [], sprintf('Connected to BigBlueButton (API version %s).', $version));
        } catch (BbbException $e) {
            return new SettingsOutcome('warning1', [], 'Settings saved, but the connection test failed: '.$e->getMessage());
        }
    }
}

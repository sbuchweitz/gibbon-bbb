<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Config;

/**
 * The module's settings (gibbonSetting scope "BigBlueButton"), normalised and type-safe.
 * Invalid stored values degrade to "not configured" rather than reaching the BBB client.
 */
final readonly class Settings
{
    public const SCOPE = 'BigBlueButton';

    public function __construct(
        public bool $enabled,
        public string $serverUrl,
        public string $secret,
        public JoinMode $joinMode,
        public bool $embeddedAllowWithoutSession,
        public ?string $recordingHostOverride,
        public int $legacyMaxPlannerEntryID,
        /** Recordings made before 2.0 carry no instance marker; hide them when the BBB server is shared. */
        public bool $showLegacyRecordings = true,
        /** Set by the 2.0 upgrade until a new shared secret is saved. */
        public bool $secretRotationRequired = false,
    ) {
    }

    /** @param array<string, string|null> $stored raw gibbonSetting values keyed by name */
    public static function fromStore(array $stored): self
    {
        $value = static fn (string $name): string => trim((string) ($stored[$name] ?? ''));
        $override = SettingsValidator::normaliseOrigin($value('recordingHostOverride'));

        return new self(
            enabled: $value('enableBigBlueButton') === 'Y',
            serverUrl: SettingsValidator::normaliseServerUrl($value('bigBlueButtonURL')) ?? '',
            secret: $value('bigBlueButtonCredentials'),
            joinMode: JoinMode::fromSetting($value('joinMode')),
            embeddedAllowWithoutSession: $value('embeddedAllowWithoutSession') === 'Y',
            recordingHostOverride: $override === '' ? null : $override,
            legacyMaxPlannerEntryID: max(0, (int) $value('legacyMaxPlannerEntryID')),
            showLegacyRecordings: $value('showLegacyRecordings') !== 'N',
            secretRotationRequired: $value('secretRotationRequired') === 'Y',
        );
    }

    public function isConfigured(): bool
    {
        return $this->enabled && $this->serverUrl !== '' && $this->secret !== '';
    }

    /**
     * Whether meetings created for embedded joins must skip BBB's session-cookie check.
     * A cross-site iframe cannot carry BBB's cookie, so this is needed unless Gibbon and BBB share a site.
     */
    public function allowRequestsWithoutSession(): bool
    {
        return $this->joinMode === JoinMode::Embedded && $this->embeddedAllowWithoutSession;
    }

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return ['secret' => $this->secret === '' ? '' : '[redacted]'] + array_diff_key(get_object_vars($this), ['secret' => true]);
    }
}

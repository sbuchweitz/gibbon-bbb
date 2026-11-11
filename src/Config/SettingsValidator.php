<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Config;

/**
 * Validates and normalises the settings form before anything is stored.
 */
final class SettingsValidator
{
    private const SECRET_PATTERN = '/^[\x21-\x7E]{16,256}$/';

    /**
     * @param array<string, mixed> $input raw POST data
     */
    public function validate(array $input, Settings $current): SettingsValidationResult
    {
        $field = static fn (string $name): string => is_string($input[$name] ?? null) ? trim($input[$name]) : '';
        $values = [];
        $errors = [];

        $enabled = $field('enableBigBlueButton');
        if (!in_array($enabled, ['Y', 'N'], true)) {
            $errors[] = 'Choose whether BigBlueButton is enabled.';
        }
        $values['enableBigBlueButton'] = $enabled;

        $url = $field('bigBlueButtonURL');
        $normalisedUrl = $url === '' ? null : self::normaliseServerUrl($url);
        if ($normalisedUrl === null && ($enabled === 'Y' || $url !== '')) {
            $errors[] = 'Enter a valid http(s) BigBlueButton server URL.';
        }
        $values['bigBlueButtonURL'] = $normalisedUrl ?? '';

        $mode = $field('joinMode');
        if (JoinMode::tryFrom($mode) === null) {
            $errors[] = 'Choose a valid join mode.';
        }
        $values['joinMode'] = $mode;

        $allowWithoutSession = $field('embeddedAllowWithoutSession');
        if (!in_array($allowWithoutSession, ['Y', 'N'], true)) {
            $errors[] = 'Choose whether embedded meetings may skip the session cookie check.';
        }
        $values['embeddedAllowWithoutSession'] = $allowWithoutSession;

        $showLegacy = $field('showLegacyRecordings');
        if (!in_array($showLegacy, ['Y', 'N'], true)) {
            $errors[] = 'Choose whether to show recordings made before version 2.0.';
        }
        $values['showLegacyRecordings'] = $showLegacy;

        $override = self::normaliseOrigin($field('recordingHostOverride'));
        if ($override === null) {
            $errors[] = 'The recording host override must be an origin such as https://bbb.school.org';
        }
        $values['recordingHostOverride'] = $override ?? '';

        $secret = $field('bigBlueButtonCredentials');
        if ($secret !== '') {
            if (preg_match(self::SECRET_PATTERN, $secret) !== 1) {
                $errors[] = 'The shared secret must be 16–256 printable characters without spaces.';
            }
            $values['bigBlueButtonCredentials'] = $secret;
        } elseif ($enabled === 'Y' && $current->secret === '') {
            $errors[] = 'Enter the BigBlueButton shared secret.';
        }

        return new SettingsValidationResult($values, $errors);
    }

    /**
     * Canonical API base URL ending in "/" (e.g. https://bbb.school.org/bigbluebutton/), or null if invalid.
     * Accepts what admins typically paste: with or without scheme, trailing "api/", or just the host.
     */
    public static function normaliseServerUrl(string $url): ?string
    {
        $parts = self::parseHttpUrl($url);
        if ($parts === null || isset($parts['query']) || isset($parts['fragment'])) {
            return null;
        }

        $path = rtrim($parts['path'] ?? '', '/');
        $path = (string) preg_replace('#/api$#i', '', $path);
        if ($path === '') {
            $path = '/bigbluebutton';
        }

        return self::originOf($parts).$path.'/';
    }

    /**
     * Canonical origin (scheme://host[:port]); '' for blank input; null if invalid.
     */
    public static function normaliseOrigin(string $origin): ?string
    {
        if (trim($origin) === '') {
            return '';
        }
        $parts = self::parseHttpUrl($origin);
        if ($parts === null || isset($parts['query']) || isset($parts['fragment']) || !in_array($parts['path'] ?? '', ['', '/'], true)) {
            return null;
        }

        return self::originOf($parts);
    }

    /**
     * @return array{scheme: string, host: string, port?: int, path?: string, query?: string, fragment?: string}|null
     */
    private static function parseHttpUrl(string $url): ?array
    {
        $url = trim($url);
        if ($url === '' || preg_match('/[\s\x00-\x1F\x7F]/', $url) === 1) {
            return null;
        }
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $url) !== 1) {
            $url = 'https://'.$url;
        }

        $parts = parse_url($url);
        if ($parts === false || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true) || !self::isValidHost($host)) {
            return null;
        }

        return ['scheme' => $scheme, 'host' => $host] + array_intersect_key($parts, array_flip(['port', 'path', 'query', 'fragment']));
    }

    private static function isValidHost(string $host): bool
    {
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            return filter_var(substr($host, 1, -1), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
        }

        return filter_var($host, FILTER_VALIDATE_IP) !== false
            || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    }

    /** @param array{scheme: string, host: string, port?: int} $parts */
    private static function originOf(array $parts): string
    {
        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}

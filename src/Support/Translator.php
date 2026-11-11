<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Gibbon\Module\BigBlueButton\Support;

/**
 * Translates with Gibbon's __() in the module's own text domain (works in hooks, where the
 * session's current module is Planner); outside Gibbon it only substitutes {placeholders}.
 */
final class Translator
{
    public const DOMAIN = 'BigBlueButton';

    /** @param array<string, scalar> $params */
    public function translate(string $text, array $params = []): string
    {
        if (\function_exists('__')) {
            return (string) \__($text, $params, ['domain' => self::DOMAIN]);
        }
        $replacements = [];
        foreach ($params as $key => $value) {
            $replacements['{'.$key.'}'] = (string) $value;
        }

        return strtr($text, $replacements);
    }
}

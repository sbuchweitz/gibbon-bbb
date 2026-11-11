<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 *
 * Environment guard + autoloader. Returns a list of problems ([] when the module can run).
 * Deliberately written in PHP 7/8.0-compatible syntax so it can report "PHP 8.2 required"
 * instead of a fatal error that would take the whole lesson page down.
 */

if (!function_exists('bigBlueButtonBootstrap')) {
    /** @return string[] */
    function bigBlueButtonBootstrap()
    {
        static $problems = null;
        if ($problems !== null) {
            return $problems;
        }

        $problems = [];
        if (PHP_VERSION_ID < 80200) {
            $problems[] = sprintf('PHP 8.2 or newer is required (this server runs PHP %s).', PHP_VERSION);
        }
        foreach (['curl', 'json', 'mbstring', 'simplexml'] as $extension) {
            if (!extension_loaded($extension)) {
                $problems[] = sprintf('The PHP extension "%s" is missing.', $extension);
            }
        }
        $autoload = __DIR__.'/vendor/autoload.php';
        if (!is_file($autoload)) {
            $problems[] = 'The module folder is incomplete (vendor/ is missing). Reinstall it from a release package.';
        }
        if ($problems === []) {
            require_once $autoload;
        }

        return $problems;
    }
}

return bigBlueButtonBootstrap();

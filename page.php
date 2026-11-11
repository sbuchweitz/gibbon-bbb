<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 *
 * Shared front controller for the module's standalone (new tab) pages. PHP 7-parseable like bootstrap.php.
 * Not a page itself: opening it directly does nothing.
 */

if (!function_exists('bigBlueButtonRunPage')) {
    /** @param callable $handler fn(ModuleServices, View): Response */
    function bigBlueButtonRunPage(string $address, callable $handler): void
    {
        global $session, $connection2, $guid;

        $problems = require __DIR__.'/bootstrap.php';
        if ($problems !== []) {
            http_response_code(503);
            header('Content-Type: text/plain; charset=utf-8');
            echo "The BigBlueButton module cannot run on this server:\n- ".implode("\n- ", $problems)."\n";

            return;
        }

        \Gibbon\Module\BigBlueButton\Gibbon\GibbonEnvironment::runPage($session, $connection2, (string) $guid, $address, $handler);
    }
}

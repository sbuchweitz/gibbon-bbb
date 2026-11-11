<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 *
 * "Lesson Planner" hook: core's planner_view_full.php includes this file at top-level scope (once per hook:
 * "View live sessions" and "View recorded sessions") after checking the hook permission. Everything runs inside
 * a closure so no variable leaks into, or clobbers, the lesson page.
 */

(static function () use ($values, $hook, $session, $connection2, $guid): void {
    $problems = require __DIR__.'/bootstrap.php';
    if ($problems !== []) {
        // Both hooks include this file; warn once per page.
        if (!defined('BIGBLUEBUTTON_ENV_WARNED') && isActionAccessible($guid, $connection2, '/modules/BigBlueButton/bbb_settings.php')) {
            define('BIGBLUEBUTTON_ENV_WARNED', true);
            echo "<div class='warning'>".htmlspecialchars('BigBlueButton: '.implode(' ', $problems), ENT_QUOTES, 'UTF-8').'</div>';
        }

        return;
    }

    echo \Gibbon\Module\BigBlueButton\Gibbon\GibbonEnvironment::renderHook(
        $session,
        $connection2,
        (string) $guid,
        (int) ($values['gibbonPlannerEntryID'] ?? 0),
        (string) ($hook['name'] ?? ''),
        $_GET['search'] ?? null
    );
})();

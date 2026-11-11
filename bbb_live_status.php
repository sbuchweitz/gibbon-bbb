<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 * JSON join status polled by the waiting page: {"state": "ready|waiting|upcoming|closed|denied|error"}.
 */

use Gibbon\Module\BigBlueButton\Controller\LiveController;
use Gibbon\Module\BigBlueButton\Gibbon\RequestContext;

require_once __DIR__.'/../../gibbon.php';
require_once __DIR__.'/page.php';

bigBlueButtonRunPage('/modules/BigBlueButton/bbb_live_status.php', static function ($services, $view) {
    return (new LiveController($services, $view))
        ->status(RequestContext::id($_GET['gibbonPlannerEntryID'] ?? null), RequestContext::childID($_GET['search'] ?? null));
});

<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 * "Join Class (New Tab)" in Embedded mode: a Gibbon page with the class in an iframe.
 */

use Gibbon\Module\BigBlueButton\Config\JoinMode;
use Gibbon\Module\BigBlueButton\Controller\LiveController;
use Gibbon\Module\BigBlueButton\Gibbon\RequestContext;

require_once __DIR__.'/../../gibbon.php';
require_once __DIR__.'/page.php';

bigBlueButtonRunPage('/modules/BigBlueButton/bbb_live.php', static function ($services, $view) {
    return (new LiveController($services, $view))
        ->page(RequestContext::id($_GET['gibbonPlannerEntryID'] ?? null), RequestContext::childID($_GET['search'] ?? null), JoinMode::Embedded);
});

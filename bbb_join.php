<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 * Direct join: redirects the tab to BigBlueButton (Direct mode, and the "open without the frame" fallback).
 */

use Gibbon\Module\BigBlueButton\Config\JoinMode;
use Gibbon\Module\BigBlueButton\Controller\LiveController;
use Gibbon\Module\BigBlueButton\Gibbon\RequestContext;

require_once __DIR__.'/../../gibbon.php';
require_once __DIR__.'/page.php';

bigBlueButtonRunPage('/modules/BigBlueButton/bbb_join.php', static fn ($services, $view) => (new LiveController($services, $view))
    ->page(RequestContext::id($_GET['gibbonPlannerEntryID'] ?? null), RequestContext::childID($_GET['search'] ?? null), JoinMode::Direct));

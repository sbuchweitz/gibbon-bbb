<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 * One recording format in a Gibbon page (opened in a new tab from the lesson's recording buttons).
 */

use Gibbon\Module\BigBlueButton\Controller\RecordingController;
use Gibbon\Module\BigBlueButton\Gibbon\RequestContext;

require_once __DIR__.'/../../gibbon.php';
require_once __DIR__.'/page.php';

bigBlueButtonRunPage('/modules/BigBlueButton/bbb_recording.php', static fn ($services, $view) => (new RecordingController($services, $view))->page(
    RequestContext::id($_GET['gibbonPlannerEntryID'] ?? null),
    RequestContext::childID($_GET['search'] ?? null),
    is_string($_GET['recordID'] ?? null) ? $_GET['recordID'] : '',
    is_string($_GET['format'] ?? null) ? $_GET['format'] : ''
));

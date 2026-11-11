<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 * Where BigBlueButton sends people when they leave the class (the meeting's logoutURL). Often shown inside the
 * class iframe, so it has no header bar and needs no permission: it reveals nothing.
 */

use Gibbon\Module\BigBlueButton\Gibbon\GibbonEnvironment;
use Gibbon\Module\BigBlueButton\Http\Response;

require_once __DIR__.'/../../gibbon.php';
$problems = require __DIR__.'/bootstrap.php';

if ($problems !== []) {
    header('Content-Type: text/plain; charset=utf-8');
    echo 'You have left the class. You can close this tab.';
} else {
    Response::page(GibbonEnvironment::view()->render('message', [
        'title' => 'BigBlueButton',
        'message' => 'You have left the class. If it was recorded, the recording appears on the lesson page once it has been processed. You can close this tab.',
        'assetBase' => rtrim((string) $session->get('absoluteURL'), '/').'/modules/BigBlueButton/',
        'bare' => true,
    ]))->send();
}

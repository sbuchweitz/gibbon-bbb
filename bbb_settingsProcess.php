<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 * Saves the settings form. Core validates the CSRF token for every *Process.php POST.
 */

use Gibbon\Module\BigBlueButton\Config\SettingsProcess;
use Gibbon\Module\BigBlueButton\Support\Log;

require_once __DIR__.'/../../gibbon.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/BigBlueButton/bbb_settings.php';

if (!isActionAccessible($guid, $connection2, '/modules/BigBlueButton/bbb_settings.php')) {
    header("Location: {$URL}&return=error0");
    exit;
}

$problems = require __DIR__.'/bootstrap.php';
if ($problems !== []) {
    header("Location: {$URL}&return=error1");
    exit;
}

try {
    $outcome = (new SettingsProcess($connection2))->handle($_POST);
} catch (\Throwable $e) {
    Log::error('saving settings', $e);
    header("Location: {$URL}&return=error2");
    exit;
}

$session->set('bigBlueButtonSettingsFlash', ['returnCode' => $outcome->returnCode, 'errors' => $outcome->errors, 'message' => $outcome->message]);
header("Location: {$URL}&return={$outcome->returnCode}");

<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 * Admin settings page (rendered inside Gibbon's index.php). Only administrators have this action.
 */

use Gibbon\Forms\Form;
use Gibbon\Module\BigBlueButton\Config\SettingsRepository;

if (!isActionAccessible($guid, $connection2, '/modules/BigBlueButton/bbb_settings.php')) {
    $page->addError(__('You do not have access to this action.'));

    return;
}

$page->breadcrumbs->add(__m('BigBlueButton Settings'));

$problems = require __DIR__.'/bootstrap.php';
if ($problems !== []) {
    $page->addError(__m('The BigBlueButton module cannot run on this server:').' '.htmlspecialchars(implode(' ', $problems)));

    return;
}

$flash = $session->get('bigBlueButtonSettingsFlash');
$session->set('bigBlueButtonSettingsFlash', null);
if (is_array($flash)) {
    foreach ($flash['errors'] ?? [] as $error) {
        echo "<div class='error'>".htmlspecialchars(__m($error))."</div>";
    }
    if (!empty($flash['message'])) {
        $level = ($flash['returnCode'] ?? '') === 'success0' ? 'success' : 'warning';
        echo "<div class='{$level}'>".htmlspecialchars(__m($flash['message']))."</div>";
    }
}

$settings = (new SettingsRepository($connection2))->load();

if ($settings->secretRotationRequired) {
    echo "<div class='warning'>".__m('After upgrading to version 2.0, please replace the BigBlueButton shared secret: change it on the BigBlueButton server with {command} and enter the new secret below.', ['command' => '<code>bbb-conf --setsecret &lt;new-secret&gt;</code>'])."</div>";
}

$form = Form::create('bigBlueButtonSettings', $session->get('absoluteURL').'/modules/BigBlueButton/bbb_settingsProcess.php');
$form->addHiddenValue('address', $session->get('address'));

$form->addRow()->addHeading('Server', __m('BigBlueButton Server'))
    ->append(__m('BigBlueButton is an external service, not affiliated with Gibbon. Run {command} on the BigBlueButton server to see its URL and shared secret.', ['command' => '<code>bbb-conf --secret</code>']));

$row = $form->addRow();
    $row->addLabel('enableBigBlueButton', __m('Enable BigBlueButton'))->description(__m('Adds a "Video Chat" option to lessons.'));
    $row->addYesNo('enableBigBlueButton')->selected($settings->enabled ? 'Y' : 'N')->required();

$form->toggleVisibilityByClass('bigBlueButton')->onSelect('enableBigBlueButton')->when('Y');

$row = $form->addRow()->addClass('bigBlueButton');
    $row->addLabel('bigBlueButtonURL', __m('Server URL'))->description(__m('For example https://bbb.school.org/bigbluebutton/'));
    $row->addURL('bigBlueButtonURL')->setValue($settings->serverUrl)->required();

$row = $form->addRow()->addClass('bigBlueButton');
    $row->addLabel('bigBlueButtonCredentials', __m('Shared Secret'))
        ->description($settings->secret === '' ? __m('Required.') : __m('A secret is saved. Leave blank to keep it.'));
    $row->addPassword('bigBlueButtonCredentials')->setValue('')->maxLength(256)->setAttribute('autocomplete', 'new-password');

$form->addRow()->addClass('bigBlueButton')->addHeading('Joining', __m('Joining a Class'));

$row = $form->addRow()->addClass('bigBlueButton');
    $row->addLabel('joinMode', __m('Join Class (New Tab) opens'))
        ->description(__m('Embedded keeps a Gibbon header above the class. Direct sends the tab straight to BigBlueButton.')
            .(str_starts_with((string) $session->get('absoluteURL'), 'https:') ? '' : ' '.__m('This Gibbon site is not on HTTPS: an embedded class cannot use the camera or microphone on a plain-HTTP page, so the class page opens the class directly instead.')));
    $row->addSelect('joinMode')->fromArray([
        'embedded' => __m('A Gibbon page with the class embedded'),
        'direct' => __m('BigBlueButton directly'),
    ])->selected($settings->joinMode->value)->required();

$row = $form->addRow()->addClass('bigBlueButton');
    $row->addLabel('embeddedAllowWithoutSession', __m('Allow embedded join without session cookie'))
        ->description(__m('Needed when Gibbon and BigBlueButton are on different domains, because an embedded class cannot use BigBlueButton\'s session cookie. It is applied per meeting (allowRequestsWithoutSession is not needed in the server configuration) and relies on the private join link alone. Choose No if both share a domain, such as gibbon.school.org and bbb.school.org.'));
    $row->addYesNo('embeddedAllowWithoutSession')->selected($settings->embeddedAllowWithoutSession ? 'Y' : 'N')->required();

$form->addRow()->addClass('bigBlueButton')->addHeading('Recordings', __m('Recordings'));

$row = $form->addRow()->addClass('bigBlueButton');
    $row->addLabel('recordingHostOverride', __m('Recording Host Override'))
        ->description(__m('Optional, for example https://bbb.school.org. Use it only if recordings fail to load because BigBlueButton publishes the wrong host (such as bigbluebutton.example.org). The proper fix is {command} on the BigBlueButton server.', ['command' => '<code>bbb-conf --setip &lt;host&gt;</code>']));
    $row->addTextField('recordingHostOverride')->setValue((string) $settings->recordingHostOverride)->maxLength(255);

$row = $form->addRow()->addClass('bigBlueButton');
    $row->addLabel('showLegacyRecordings', __m('Show Recordings Made Before Version 2.0'))
        ->description(__m('Turn off if this BigBlueButton server is shared with other Gibbon sites: recordings from before version 2.0 cannot be told apart between sites.'));
    $row->addYesNo('showLegacyRecordings')->selected($settings->showLegacyRecordings ? 'Y' : 'N')->required();

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit();

echo $form->getOutput();

<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 */

// Describes the module for a fresh install. Upgrades from earlier versions are in CHANGEDB.php.

require_once __DIR__.'/src/Database/Schema.php';

$name = 'BigBlueButton';
$description = 'BigBlueButton video classes and recordings for lesson plans.';
$entryURL = 'bbb_settings.php';
$type = 'Additional';
$category = 'Admin';
$version = '2.0.0';
$author = 'The gibbon-bbb authors';
$url = 'https://github.com/sbuchweitz/gibbon-bbb';

// Tables (plain "CREATE TABLE `name`": core's uninstaller reads the table name from the third word)
$moduleTables = \Gibbon\Module\BigBlueButton\Database\Schema::tables();

// Settings
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('BigBlueButton', 'enableBigBlueButton', 'Enable BigBlueButton', 'Turns BigBlueButton video classes on for lesson plans.', 'N');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('BigBlueButton', 'bigBlueButtonURL', 'BigBlueButton Server URL', 'API base URL, e.g. https://bbb.school.org/bigbluebutton/', '');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('BigBlueButton', 'bigBlueButtonCredentials', 'BigBlueButton Shared Secret', 'Shared secret from bbb-conf --secret.', '');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('BigBlueButton', 'joinMode', 'Join Mode', 'embedded: a Gibbon page embedding the class; direct: straight to BigBlueButton.', 'embedded');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('BigBlueButton', 'embeddedAllowWithoutSession', 'Allow Embedded Join Without Session Cookie', 'Per-meeting allowRequestsWithoutSession for embedded joins across domains.', 'Y');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('BigBlueButton', 'recordingHostOverride', 'Recording Host Override', 'Optional origin that replaces the host of recording playback URLs.', '');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('BigBlueButton', 'showLegacyRecordings', 'Show Pre-2.0 Recordings', 'Turn off if the BigBlueButton server is shared with other Gibbon sites.', 'Y');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('BigBlueButton', 'secretRotationRequired', 'Shared Secret Must Be Replaced', 'Set by the 2.0 upgrade until a new shared secret is saved.', 'N');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('BigBlueButton', 'legacyMaxPlannerEntryID', 'Legacy Lesson Boundary', 'Lessons up to this ID may have recordings under the pre-2.0 meeting ID.', '0');";

// Lesson custom fields (inactive until BigBlueButton is enabled in the settings)
$gibbonSetting[] = "INSERT INTO `gibbonCustomField` (`context`, `name`, `active`, `description`, `type`, `options`, `required`, `hidden`, `heading`, `sequenceNumber`)
SELECT 'Lesson Plan', 'Video Chat', 'N', 'Adds a BigBlueButton video class to this lesson.', 'checkboxes', 'Include', 'N', 'Y', 'Basic Information', 1
WHERE NOT EXISTS (SELECT 1 FROM `gibbonCustomField` WHERE `context`='Lesson Plan' AND `name`='Video Chat');";
$gibbonSetting[] = "INSERT INTO `gibbonCustomField` (`context`, `name`, `active`, `description`, `type`, `options`, `required`, `hidden`, `heading`, `sequenceNumber`)
SELECT 'Lesson Plan', 'Presentation Only', 'N', 'Show only the presentation playback of this lesson''s recordings.', 'checkboxes', 'Yes', 'N', 'Y', 'Basic Information', 2
WHERE NOT EXISTS (SELECT 1 FROM `gibbonCustomField` WHERE `context`='Lesson Plan' AND `name`='Presentation Only');";

// Actions. Settings expose the BBB shared secret: administrators only.
$actionRows[] = [
    'name'                      => 'BigBlueButton Settings',
    'precedence'                => '0',
    'category'                  => 'Admin',
    'description'               => 'Manage the BigBlueButton server connection. Shows the shared secret: administrators only.',
    'URLList'                   => 'bbb_settings.php',
    'entryURL'                  => 'bbb_settings.php',
    'entrySidebar'              => 'N',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'View live sessions',
    'precedence'                => '0',
    'category'                  => 'Planner',
    'description'               => 'Join the live BigBlueButton class of lessons the user can view.',
    'URLList'                   => 'planner_view_full.php,bbb_live.php,bbb_join.php,bbb_live_status.php',
    'entryURL'                  => 'planner_view_full.php',
    'entrySidebar'              => 'N',
    'menuShow'                  => 'N',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'Y',
    'defaultPermissionStudent'  => 'Y',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'Y',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'Y',
    'categoryPermissionParent'  => 'Y',
    'categoryPermissionOther'   => 'Y',
];

$actionRows[] = [
    'name'                      => 'View recorded sessions',
    'precedence'                => '0',
    'category'                  => 'Planner',
    'description'               => 'Watch BigBlueButton recordings of lessons the user can view.',
    'URLList'                   => 'planner_view_full.php,bbb_recording.php',
    'entryURL'                  => 'planner_view_full.php',
    'entrySidebar'              => 'N',
    'menuShow'                  => 'N',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'Y',
    'defaultPermissionStudent'  => 'Y',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'Y',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'Y',
    'categoryPermissionParent'  => 'Y',
    'categoryPermissionOther'   => 'Y',
];

// Hooks: one per action, both rendered by hook_lessonPlannerView.php.
foreach (['View live sessions', 'View recorded sessions'] as $i => $hookAction) {
    $options = serialize(['sourceModuleName' => $name, 'sourceModuleAction' => $hookAction, 'sourceModuleInclude' => 'hook_lessonPlannerView.php']);
    $hooks[$i] = "INSERT INTO `gibbonHook` (`gibbonHookID`, `name`, `type`, `options`, `gibbonModuleID`) VALUES (NULL, '{$hookAction}', 'Lesson Planner', '{$options}', (SELECT gibbonModuleID FROM gibbonModule WHERE name='{$name}'));";
}

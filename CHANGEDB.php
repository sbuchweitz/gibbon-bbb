<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 *
 * Database changes Gibbon applies when the module is updated: each entry runs once, for installations older
 * than its version. Separate SQL statements with ;end and don't use ;end anywhere else.
 */

require_once __DIR__.'/src/Database/Schema.php';

$sql = [];
$count = 0;

// 2.0.0 - Brings any 1.x installation up to date in one idempotent step:
//   module tables (stable per-lesson meeting IDs, recording cache); lesson custom fields if missing;
//   settings page for the Administrator role only (role 001 is core's built-in Administrator role, which
//   module installs grant defaultPermissionAdmin to); URL lists for the new pages; new settings, including
//   a reminder to replace the shared secret. Existing grants of the live/recorded actions are left unchanged.
$sql[$count][0] = '2.0.0';
$sql[$count][1] = implode(';end
', \Gibbon\Module\BigBlueButton\Database\Schema::tablesIfNotExists()).";end
INSERT INTO gibbonCustomField (context, name, active, description, type, options, required, hidden, heading, sequenceNumber)
    SELECT 'Lesson Plan', 'Video Chat', IFNULL((SELECT value FROM gibbonSetting WHERE scope='BigBlueButton' AND name='enableBigBlueButton' LIMIT 1), 'N'),
        'Adds a BigBlueButton video class to this lesson.', 'checkboxes', 'Include', 'N', 'Y', 'Basic Information', 1 FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM gibbonCustomField WHERE context='Lesson Plan' AND name='Video Chat');end
INSERT INTO gibbonCustomField (context, name, active, description, type, options, required, hidden, heading, sequenceNumber)
    SELECT 'Lesson Plan', 'Presentation Only', IFNULL((SELECT value FROM gibbonSetting WHERE scope='BigBlueButton' AND name='enableBigBlueButton' LIMIT 1), 'N'),
        'Show only the presentation playback of this lesson''s recordings.', 'checkboxes', 'Yes', 'N', 'Y', 'Basic Information', 2 FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM gibbonCustomField WHERE context='Lesson Plan' AND name='Presentation Only');end
DELETE gibbonPermission FROM gibbonPermission
    JOIN gibbonAction ON (gibbonAction.gibbonActionID=gibbonPermission.gibbonActionID)
    JOIN gibbonModule ON (gibbonModule.gibbonModuleID=gibbonAction.gibbonModuleID)
    WHERE gibbonModule.name='BigBlueButton' AND gibbonAction.name='BigBlueButton Settings' AND gibbonPermission.gibbonRoleID<>1;end
UPDATE gibbonAction JOIN gibbonModule ON (gibbonModule.gibbonModuleID=gibbonAction.gibbonModuleID)
    SET gibbonAction.description='Manage the BigBlueButton server connection. Shows the shared secret: administrators only.',
        defaultPermissionTeacher='N', defaultPermissionStudent='N', defaultPermissionParent='N', defaultPermissionSupport='N',
        categoryPermissionStudent='N', categoryPermissionParent='N', categoryPermissionOther='N'
    WHERE gibbonModule.name='BigBlueButton' AND gibbonAction.name='BigBlueButton Settings';end
UPDATE gibbonAction JOIN gibbonModule ON (gibbonModule.gibbonModuleID=gibbonAction.gibbonModuleID)
    SET URLList='planner_view_full.php,bbb_live.php,bbb_join.php,bbb_live_status.php'
    WHERE gibbonModule.name='BigBlueButton' AND gibbonAction.name='View live sessions';end
UPDATE gibbonAction JOIN gibbonModule ON (gibbonModule.gibbonModuleID=gibbonAction.gibbonModuleID)
    SET URLList='planner_view_full.php,bbb_recording.php'
    WHERE gibbonModule.name='BigBlueButton' AND gibbonAction.name='View recorded sessions';end
INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
    SELECT 'BigBlueButton', 'joinMode', 'Join Mode', 'embedded: a Gibbon page embedding the class; direct: straight to BigBlueButton.', 'embedded' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM gibbonSetting WHERE scope='BigBlueButton' AND name='joinMode');end
INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
    SELECT 'BigBlueButton', 'embeddedAllowWithoutSession', 'Allow Embedded Join Without Session Cookie', 'Per-meeting allowRequestsWithoutSession for embedded joins across domains.', 'Y' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM gibbonSetting WHERE scope='BigBlueButton' AND name='embeddedAllowWithoutSession');end
INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
    SELECT 'BigBlueButton', 'recordingHostOverride', 'Recording Host Override', 'Optional origin that replaces the host of recording playback URLs.', '' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM gibbonSetting WHERE scope='BigBlueButton' AND name='recordingHostOverride');end
INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
    SELECT 'BigBlueButton', 'legacyMaxPlannerEntryID', 'Legacy Lesson Boundary', 'Lessons up to this ID may have recordings under the pre-2.0 meeting ID.', (SELECT IFNULL(MAX(gibbonPlannerEntryID), 0) FROM gibbonPlannerEntry) FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM gibbonSetting WHERE scope='BigBlueButton' AND name='legacyMaxPlannerEntryID');end
INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
    SELECT 'BigBlueButton', 'showLegacyRecordings', 'Show Pre-2.0 Recordings', 'Turn off if the BigBlueButton server is shared with other Gibbon sites.', 'Y' FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM gibbonSetting WHERE scope='BigBlueButton' AND name='showLegacyRecordings');end
INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
    SELECT 'BigBlueButton', 'secretRotationRequired', 'Shared Secret Must Be Replaced', 'Set by the 2.0 upgrade until a new shared secret is saved.', IF(EXISTS (SELECT 1 FROM gibbonSetting WHERE scope='BigBlueButton' AND name='bigBlueButtonCredentials' AND value<>''), 'Y', 'N') FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM gibbonSetting WHERE scope='BigBlueButton' AND name='secretRotationRequired');end
UPDATE gibbonSetting SET nameDisplay='BigBlueButton Shared Secret', description='Shared secret from bbb-conf --secret.'
    WHERE scope='BigBlueButton' AND name='bigBlueButtonCredentials';end
UPDATE gibbonSetting SET description='API base URL, e.g. https://bbb.school.org/bigbluebutton/'
    WHERE scope='BigBlueButton' AND name='bigBlueButtonURL';end
UPDATE gibbonAction JOIN gibbonModule ON (gibbonModule.gibbonModuleID=gibbonAction.gibbonModuleID)
    SET gibbonAction.description='Join the live BigBlueButton class of lessons the user can view.'
    WHERE gibbonModule.name='BigBlueButton' AND gibbonAction.name='View live sessions';end
UPDATE gibbonAction JOIN gibbonModule ON (gibbonModule.gibbonModuleID=gibbonAction.gibbonModuleID)
    SET gibbonAction.description='Watch BigBlueButton recordings of lessons the user can view.'
    WHERE gibbonModule.name='BigBlueButton' AND gibbonAction.name='View recorded sessions';end
UPDATE gibbonCustomField SET `hidden`='Y' WHERE context='Lesson Plan' AND name IN ('Video Chat', 'Presentation Only');end
";

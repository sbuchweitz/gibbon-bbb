<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 * Logout URL of meetings created by module versions before 2.0; kept so those still land somewhere sensible.
 */

header('Location: bbb_ended.php', true, 302);

<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 * Inline warning on the lesson page, shown only to people who can manage the module's settings.
 */
?>
<div class="warning">
    <?= $t('BigBlueButton') ?>: <?= $t($v['message']) ?>
    <a href="<?= $e($v['settingsUrl']) ?>"><?= $t('Open BigBlueButton Settings') ?></a>
</div>

<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 * Slim header bar: lesson name + class. Pages add their own links after including this.
 */
?>
<header class="bbb-bar">
    <div class="bbb-bar__title">
        <strong><?= $e($v['title'] ?? 'BigBlueButton') ?></strong>
        <?php if (!empty($v['subtitle'])): ?><span><?= $e($v['subtitle']) ?></span><?php endif; ?>
    </div>

<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 * A plain message page (access denied, class ended, server unreachable…). $v['bare'] hides the header bar,
 * for pages that may show inside the class iframe.
 *
 * @var array{title: string, message: string, assetBase: string, bare?: bool, link?: array{url: string, label: string}} $v
 */
require __DIR__.'/partials/head.php';
if (empty($v['bare'])) {
    require __DIR__.'/partials/bar.php';
    echo '</header>';
}
?>
<main class="bbb-center">
    <div class="bbb-card">
        <p><?= $t($v['message']) ?></p>
        <?php if (!empty($v['link'])): ?>
            <p><a class="bbb-button" href="<?= $e($v['link']['url']) ?>"><?= $t($v['link']['label']) ?></a></p>
        <?php endif; ?>
    </div>
</main>
</body>
</html>

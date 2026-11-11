<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 * Shown until the class can be joined; js/live.js polls the status endpoint and reloads when it is ready.
 *
 * @var array{title: string, subtitle: string, upcoming: bool, opensAt: ?\DateTimeImmutable, statusUrl: string, assetBase: string} $v
 */
require __DIR__.'/partials/head.php';
require __DIR__.'/partials/bar.php';
?>
</header>
<main class="bbb-center">
    <div class="bbb-card" data-bbb-status-url="<?= $e($v['statusUrl']) ?>" aria-live="polite">
        <div class="bbb-spinner" aria-hidden="true"></div>
        <?php if ($v['upcoming'] && $v['opensAt'] !== null): ?>
            <p><?= $t('The class opens at {time}.', ['time' => $v['opensAt']->format('H:i')]) ?></p>
        <?php else: ?>
            <p><?= $t('Waiting for the teacher to start the class…') ?></p>
        <?php endif; ?>
        <p class="bbb-hint"><?= $t('Keep this tab open: it joins automatically.') ?></p>
    </div>
</main>
<script src="<?= $e($v['assetBase']) ?>js/live.js" defer></script>
</body>
</html>

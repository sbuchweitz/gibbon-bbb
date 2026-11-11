<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 * Lesson page section during/before the live window. The class never loads inline: it opens in a new tab.
 *
 * @var array{open: bool, opensAt: \DateTimeImmutable, sameDay: bool, joinUrl: string, assetBase: string} $v
 */
?>
<link rel="stylesheet" href="<?= $e($v['assetBase']) ?>css/bbb.css">
<section class="bbb-panel" data-bbb="live">
    <h2><?= $t('Video Chat') ?></h2>
    <?php if ($v['open']): ?>
        <a class="bbb-button" href="<?= $e($v['joinUrl']) ?>" target="_blank" rel="noopener"><?= $t('Join Class (New Tab)') ?></a>
    <?php elseif ($v['sameDay']): ?>
        <p class="bbb-hint"><?= $t('The class opens at {time}.', ['time' => $v['opensAt']->format('H:i')]) ?></p>
    <?php else: ?>
        <p class="bbb-hint"><?= $t('The class opens on {date} at {time}.', ['date' => $v['opensAt']->format('j M Y'), 'time' => $v['opensAt']->format('H:i')]) ?></p>
    <?php endif; ?>
</section>

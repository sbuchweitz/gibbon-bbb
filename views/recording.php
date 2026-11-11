<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 * One recording format in a Gibbon page, with links to the recording's other formats.
 *
 * @var array{title: string, subtitle: string, recording: \Gibbon\Module\BigBlueButton\Recording\RecordingView, current: \Gibbon\Module\BigBlueButton\Recording\FormatLink, assetBase: string} $v
 */
require __DIR__.'/partials/head.php';
require __DIR__.'/partials/bar.php';
$recording = $v['recording'];
$current = $v['current'];
?>
    <nav class="bbb-bar__actions">
        <span class="bbb-bar__meta"><?= $t('Recording {number}', ['number' => $recording->number]) ?> · <?= $e($recording->startedAt->format('j M Y H:i')) ?></span>
        <?php foreach ($recording->links as $link): ?>
            <a href="<?= $e($link->viewerUrl) ?>" class="bbb-tab<?= $link->type === $current->type ? ' is-current' : '' ?>"
               <?= $link->type === $current->type ? 'aria-current="page"' : '' ?>><?= $t($link->label) ?></a>
        <?php endforeach; ?>
        <?php if ($current->downloadable): ?>
            <a href="<?= $e($current->href) ?>" download rel="noopener"><?= $t('Download') ?></a>
        <?php endif; ?>
        <a href="<?= $e($current->playbackUrl) ?>" target="_blank" rel="noopener noreferrer"><?= $t('Open without frame') ?></a>
    </nav>
</header>
<main class="bbb-stage">
    <iframe class="bbb-frame" src="<?= $e($current->playbackUrl) ?>" title="<?= $t('Recording') ?>"
            allow="fullscreen; autoplay; clipboard-write; picture-in-picture" allowfullscreen
            sandbox="allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox allow-downloads"
            referrerpolicy="no-referrer"></iframe>
</main>
</body>
</html>

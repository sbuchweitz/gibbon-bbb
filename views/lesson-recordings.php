<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 * Lesson page section listing recordings. Each format is a link: a click opens the Gibbon viewer page
 * (js/lesson.js); the href is the media file where there is one, so "Save link as…" downloads it.
 *
 * @var array{recordings: list<\Gibbon\Module\BigBlueButton\Recording\RecordingView>, failed: bool, assetBase: string} $v
 */
?>
<link rel="stylesheet" href="<?= $e($v['assetBase']) ?>css/bbb.css">
<section class="bbb-panel" data-bbb="recordings">
    <h2><?= $t('Class Recordings') ?></h2>
    <?php if ($v['failed']): ?>
        <p class="bbb-hint bbb-hint--warn"><?= $t('BigBlueButton could not be reached, so this list may be out of date.') ?></p>
    <?php endif; ?>
    <?php if ($v['recordings'] === []): ?>
        <p class="bbb-hint"><?= $t('No recordings yet. If the class was recorded, it appears here once BigBlueButton has processed it.') ?></p>
    <?php else: ?>
        <ul class="bbb-recordings">
            <?php foreach ($v['recordings'] as $recording): ?>
                <li class="bbb-recording" data-record-id="<?= $e($recording->recordID) ?>">
                    <span class="bbb-recording__label">
                        <?= $t('Recording {number}', ['number' => $recording->number]) ?>
                        · <?= $e($recording->startedAt->format('H:i')) ?>
                        <?php if ($recording->durationMinutes > 0): ?>· <?= $t('{minutes} min', ['minutes' => $recording->durationMinutes]) ?><?php endif; ?>
                    </span>
                    <span class="bbb-recording__formats">
                        <?php foreach ($recording->links as $link): ?>
                            <a class="bbb-button bbb-button--small" href="<?= $e($link->href) ?>" data-bbb-viewer="<?= $e($link->viewerUrl) ?>"
                               data-format="<?= $e($link->type) ?>" target="_blank" rel="noopener"
                               <?php if ($link->downloadable): ?>title="<?= $t('Click to watch. Right-click and choose "Save link as…" to download.') ?>"<?php endif; ?>><?= $t($link->label) ?></a>
                        <?php endforeach; ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<script src="<?= $e($v['assetBase']) ?>js/lesson.js" defer></script>

<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 * The live class embedded in a Gibbon page (opened in a new tab from the lesson).
 * Popups are allowed so BigBlueButton's learning analytics dashboard can open.
 *
 * @var array{title: string, subtitle: string, joinUrl: string, directUrl: string, assetBase: string} $v
 */
require __DIR__.'/partials/head.php';
require __DIR__.'/partials/bar.php';
?>
    <nav class="bbb-bar__actions">
        <a href="<?= $e($v['directUrl']) ?>"><?= $t('Trouble joining? Open the class without the frame') ?></a>
    </nav>
</header>
<main class="bbb-stage">
    <iframe class="bbb-frame" src="<?= $e($v['joinUrl']) ?>" title="<?= $t('BigBlueButton class') ?>"
            allow="camera; microphone; display-capture; fullscreen; autoplay; clipboard-write; picture-in-picture" allowfullscreen
            sandbox="allow-scripts allow-same-origin allow-forms allow-modals allow-popups allow-popups-to-escape-sandbox allow-downloads allow-top-navigation-by-user-activation"
            referrerpolicy="no-referrer"></iframe>
</main>
</body>
</html>

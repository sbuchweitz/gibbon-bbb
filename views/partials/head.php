<?php
/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 * Shared <head> for the module's standalone pages. Expects $v['title'] and $v['assetBase'].
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $e($v['title'] ?? 'BigBlueButton') ?></title>
    <link rel="stylesheet" href="<?= $e($v['assetBase']) ?>css/bbb.css">
</head>
<body class="bbb-page">

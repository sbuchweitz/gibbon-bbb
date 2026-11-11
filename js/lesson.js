/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 * Recording buttons on the lesson page: a click (left, middle or with modifiers) opens the Gibbon viewer page in a
 * new tab, while the link's href stays the media file so "Save link as…" downloads the video.
 */
(function () {
    'use strict';
    if (window.bbbLessonBound) {
        return; // the lesson page may be swapped in by htmx more than once
    }
    window.bbbLessonBound = true;

    function openViewer(event) {
        var link = event.target instanceof Element ? event.target.closest('a[data-bbb-viewer]') : null;
        if (!link) {
            return;
        }
        event.preventDefault();
        window.open(link.getAttribute('data-bbb-viewer'), '_blank', 'noopener');
    }

    document.addEventListener('click', function (event) {
        if (event.button === 0) {
            openViewer(event);
        }
    });
    document.addEventListener('auxclick', function (event) {
        if (event.button === 1) {
            openViewer(event);
        }
    });
})();

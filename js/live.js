/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 * Class page: loads the embedded class, or joins directly where an embedded class cannot work.
 * Waiting page: polls the join status and reloads once the class can be joined (or has ended).
 */
(function () {
    'use strict';
    var frame = document.querySelector('iframe.bbb-frame[data-src]');
    if (frame) {
        if (window.isSecureContext) {
            frame.src = frame.getAttribute('data-src');
        } else {
            // Plain-HTTP page: the frame would get no camera/microphone and BigBlueButton's client would fail.
            window.location.replace(document.querySelector('[data-bbb-direct-url]').getAttribute('data-bbb-direct-url'));
        }
    }
})();

(function () {
    'use strict';
    var box = document.querySelector('[data-bbb-status-url]');
    if (!box) {
        return;
    }
    var statusUrl = box.getAttribute('data-bbb-status-url');
    var delay = 5000;
    var maxDelay = 30000;

    function schedule() {
        window.setTimeout(poll, delay);
        delay = Math.min(maxDelay, Math.round(delay * 1.3));
    }

    function poll() {
        fetch(statusUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store' })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.state === 'ready' || data.state === 'closed' || data.state === 'denied') {
                    window.location.reload();
                } else {
                    schedule();
                }
            })
            .catch(schedule);
    }

    schedule();
})();

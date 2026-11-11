/*
 * gibbon-bbb: BigBlueButton module for Gibbon.
 * SPDX-License-Identifier: Apache-2.0
 * Waiting page: polls the join status and reloads once the class can be joined (or has ended).
 */
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

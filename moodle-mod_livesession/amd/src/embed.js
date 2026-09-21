// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Embeds the Zoom meeting inside the activity page and keeps attendance ticking.
 *
 * The meeting runs in the Zoom Meeting SDK's Component View, which renders into a div
 * on this page - the student never leaves Moodle and the Zoom desktop app is never
 * invoked. While the meeting is open the module calls home on a fixed interval so the
 * server can accumulate attended time; the server, not the browser, decides what that
 * time is worth.
 *
 * @module     mod_livesession/embed
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import {getString} from 'core/str';
import Log from 'core/log';

/** @type {Object|null} The Zoom Component View client. */
let zoomClient = null;

/** @type {number|null} Handle for the heartbeat interval. */
let heartbeatTimer = null;

/** @type {number} Course module id of the session being attended. */
let courseModuleId = 0;

/** @type {boolean} Guards against sending two leave calls for one departure. */
let leaveSent = false;

/**
 * Load the Zoom Meeting SDK bundle from Zoom's CDN, once per page.
 *
 * The SDK is not bundled with the plugin: Zoom requires the client and the service to
 * stay within a supported version range, so pinning a copy in the repository would go
 * stale and start failing to connect.
 *
 * @param {String} version the SDK version configured by the site administrator
 * @returns {Promise<Object>} resolves with the ZoomMtgEmbedded global
 */
const loadSdk = (version) => new Promise((resolve, reject) => {
    if (window.ZoomMtgEmbedded) {
        resolve(window.ZoomMtgEmbedded);
        return;
    }

    const script = document.createElement('script');
    script.src = `https://source.zoom.us/${version}/zoom-meeting-embedded-${version}.umd.min.js`;
    script.async = true;
    script.onload = () => {
        if (window.ZoomMtgEmbedded) {
            resolve(window.ZoomMtgEmbedded);
        } else {
            reject(new Error('Zoom Meeting SDK loaded but did not register itself.'));
        }
    };
    script.onerror = () => reject(new Error('Could not load the Zoom Meeting SDK.'));
    document.head.appendChild(script);
});

/**
 * Show a message in the status strip above the meeting.
 *
 * @param {HTMLElement} statusEl
 * @param {String} message
 * @param {String} variant a Bootstrap alert variant
 * @returns {void}
 */
const setStatus = (statusEl, message, variant = 'info') => {
    if (!statusEl) {
        return;
    }
    statusEl.className = `alert alert-${variant}`;
    statusEl.textContent = message;
    statusEl.classList.remove('d-none');
};

/**
 * Tell the server we are still here.
 *
 * A failed heartbeat is logged but never surfaced: a blip in connectivity should not
 * throw an error banner over a meeting the student is still watching. The next
 * heartbeat carries the missed time, because the server measures from its own clock.
 *
 * @param {HTMLElement} counterEl element that displays the running total
 * @returns {void}
 */
const sendHeartbeat = (counterEl) => {
    Ajax.call([{
        methodname: 'mod_livesession_record_heartbeat',
        args: {cmid: courseModuleId},
    }])[0].then(async(response) => {
        if (counterEl) {
            counterEl.textContent = await getString('attendancecounter', 'mod_livesession',
                Math.round(response.duration / 60));
        }
        return response;
    }).catch((error) => {
        Log.debug('mod_livesession: heartbeat failed, will retry on the next tick.', error);
    });
};

/**
 * Close the attendance segment.
 *
 * On a normal leave this goes through the usual AJAX stack. During page unload that
 * stack is not reliable, so the beacon variant posts directly with keepalive set,
 * which the browser is obliged to finish even as the page goes away.
 *
 * @param {String} reason
 * @param {Boolean} beacon send as an unload-safe request
 * @returns {void}
 */
const sendLeave = (reason, beacon = false) => {
    if (leaveSent) {
        return;
    }
    leaveSent = true;

    if (heartbeatTimer !== null) {
        window.clearInterval(heartbeatTimer);
        heartbeatTimer = null;
    }

    if (beacon) {
        const url = `${M.cfg.wwwroot}/lib/ajax/service.php`
            + `?sesskey=${encodeURIComponent(M.cfg.sesskey)}`
            + `&info=mod_livesession_record_leave`;
        try {
            window.fetch(url, {
                method: 'POST',
                keepalive: true,
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify([{
                    index: 0,
                    methodname: 'mod_livesession_record_leave',
                    args: {cmid: courseModuleId, reason: reason},
                }]),
            });
        } catch (error) {
            Log.debug('mod_livesession: unload leave beacon failed.', error);
        }
        return;
    }

    Ajax.call([{
        methodname: 'mod_livesession_record_leave',
        args: {cmid: courseModuleId, reason: reason},
    }])[0].catch((error) => {
        Log.debug('mod_livesession: leave call failed.', error);
    });
};

/**
 * Work out how large the meeting canvas should be inside its container.
 *
 * @param {HTMLElement} root
 * @returns {{width: Number, height: Number}}
 */
const viewportFor = (root) => {
    const width = Math.max(320, Math.floor(root.clientWidth || 960));
    // 16:9, but never so tall that the toolbar falls below the fold on a laptop.
    const height = Math.min(Math.round(width * 9 / 16), Math.round(window.innerHeight * 0.75));
    return {width, height};
};

/**
 * Join the meeting and start recording attendance.
 *
 * @param {HTMLElement} root the element the meeting renders into
 * @param {HTMLElement} statusEl
 * @param {HTMLElement} counterEl
 * @param {HTMLElement} joinButton
 * @returns {Promise<void>}
 */
const startMeeting = async(root, statusEl, counterEl, joinButton) => {
    setStatus(statusEl, await getString('connecting', 'mod_livesession'), 'info');

    const config = await Ajax.call([{
        methodname: 'mod_livesession_join_session',
        args: {cmid: courseModuleId},
    }])[0];

    const sdk = await loadSdk(config.sdkversion);

    zoomClient = sdk.createClient();
    const size = viewportFor(root);

    await zoomClient.init({
        zoomAppRoot: root,
        language: 'en-US',
        patchJsMedia: true,
        leaveOnPageUnload: true,
        customize: {
            video: {
                isResizable: true,
                viewSizes: {
                    default: size,
                    ribbon: {width: 300, height: size.height},
                },
            },
        },
    });

    // Zoom closes the connection for reasons we did not initiate - the host ending the
    // meeting, a network drop - so treat any move to Closed as a departure.
    zoomClient.on('connection-change', async(payload) => {
        if (payload && payload.state === 'Closed') {
            sendLeave('closed');
            setStatus(statusEl, await getString('meetingended', 'mod_livesession'), 'secondary');
            if (joinButton) {
                joinButton.classList.remove('d-none');
                joinButton.disabled = false;
                leaveSent = false;
            }
        }
    });

    await zoomClient.join({
        signature: config.signature,
        sdkKey: config.sdkkey,
        meetingNumber: config.meetingnumber,
        password: config.passcode,
        userName: config.username,
        userEmail: config.useremail,
        tk: '',
    });

    leaveSent = false;
    setStatus(statusEl, await getString('attendancerecording', 'mod_livesession'), 'success');

    const interval = Math.max(15, parseInt(config.heartbeatinterval, 10) || 60) * 1000;
    heartbeatTimer = window.setInterval(() => sendHeartbeat(counterEl), interval);
    sendHeartbeat(counterEl);
};

/**
 * Wire up the join button for a live session activity page.
 *
 * The meeting is not started automatically: browsers only grant camera and microphone
 * access off the back of a user gesture, and the click doubles as the moment the
 * student is told their attendance is being recorded.
 *
 * @param {Number} cmid course module id
 * @param {String} rootId id of the element the meeting renders into
 * @param {String} buttonId id of the join button
 * @param {String} statusId id of the status strip
 * @param {String} counterId id of the running attendance total
 * @returns {void}
 */
export const init = (cmid, rootId, buttonId, statusId, counterId) => {
    courseModuleId = parseInt(cmid, 10);

    const root = document.getElementById(rootId);
    const joinButton = document.getElementById(buttonId);
    const statusEl = document.getElementById(statusId);
    const counterEl = document.getElementById(counterId);

    if (!root || !joinButton) {
        return;
    }

    joinButton.addEventListener('click', async(e) => {
        e.preventDefault();
        joinButton.disabled = true;
        joinButton.classList.add('d-none');

        try {
            await startMeeting(root, statusEl, counterEl, joinButton);
        } catch (error) {
            Log.error('mod_livesession: could not start the meeting.', error);
            const message = (error && (error.message || error.reason)) || '';
            setStatus(statusEl, `${await getString('error:joinfailed', 'mod_livesession')} ${message}`, 'danger');
            joinButton.disabled = false;
            joinButton.classList.remove('d-none');
        }
    });

    window.addEventListener('pagehide', () => sendLeave('unload', true));
};

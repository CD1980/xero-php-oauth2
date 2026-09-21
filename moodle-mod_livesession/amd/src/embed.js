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
 * The URLs to try for the Component View bundle, most likely first.
 *
 * The first entry is the one Zoom's own 6.x bundle names in its license header and
 * that Zoom's CDN sample uses for the versioned directory layout. The rest are older
 * or alternate layouts, kept so a version mismatch degrades rather than dies. An
 * administrator can bypass the list entirely with the "Meeting SDK URL" setting.
 *
 * @param {String} version the SDK version configured by the site administrator
 * @param {String} override a full URL from site configuration, may contain {version}
 * @returns {Array<String>}
 */
const sdkUrlCandidates = (version, override) => {
    if (override) {
        return [override.replace(/\{version\}/g, version)];
    }
    return [
        `https://source.zoom.us/${version}/zoom-meeting-embedded-${version}.min.js`,
        `https://source.zoom.us/zoom-meeting-embedded-${version}.min.js`,
        `https://source.zoom.us/${version}/zoomus-websdk-embedded.umd.min.js`,
    ];
};

/**
 * Globals the SDK may publish itself under, in the order we prefer them.
 *
 * @type {Array<String>}
 */
const SDK_GLOBALS = ['ZoomMtgEmbedded', 'ReactWidgets'];

/**
 * Find the SDK on the window, whichever name it used.
 *
 * @returns {Object|null}
 */
const findSdkGlobal = () => {
    for (const name of SDK_GLOBALS) {
        const candidate = window[name];
        if (candidate && typeof candidate.createClient === 'function') {
            return candidate;
        }
    }
    return null;
};

/**
 * Append one script tag and resolve when it loads.
 *
 * Moodle has RequireJS on every page, so define.amd is truthy. A UMD bundle checks
 * exactly that and registers itself as an anonymous AMD module rather than assigning
 * a global - which RequireJS then discards, because nothing asked for it. The script
 * loads perfectly and the global never appears. Hiding define.amd for the duration of
 * the load forces the bundle down its browser-global branch instead. The window is
 * kept as short as possible and restored even if the load fails.
 *
 * @param {String} src
 * @returns {Promise<void>}
 */
const loadScript = (src) => {
    const amd = window.define && window.define.amd;
    if (amd) {
        window.define.amd = undefined;
    }

    const restore = () => {
        if (amd) {
            window.define.amd = amd;
        }
    };

    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = src;
        script.async = true;
        // Without this a cross-origin script's exceptions are reduced to a bare
        // "Script error." with no filename, message or line, which hides the one
        // piece of information worth having. Zoom's CDN sends CORS headers, so
        // asking for them costs nothing.
        script.crossOrigin = 'anonymous';
        script.onload = () => resolve();
        script.onerror = () => reject(new Error(`Failed to load ${src}`));
        document.head.appendChild(script);
    }).then(restore, (error) => {
        restore();
        throw error;
    });
};

/**
 * Ask why a URL could not be fetched, so the report says more than "it failed".
 *
 * A script tag's error event carries no status, which is the difference between a
 * wrong version (404) and a blocked request (a policy or firewall). A HEAD request
 * distinguishes them.
 *
 * @param {String} url
 * @returns {Promise<String>}
 */
const probeUrl = async(url) => {
    try {
        const response = await fetch(url, {method: 'HEAD', mode: 'cors', cache: 'no-store'});
        const type = (response.headers.get('content-type') || 'none').split(';')[0];
        if (response.status === 200 && !/javascript|ecmascript/i.test(type)) {
            return `HTTP 200 but content-type is "${type}", so the browser refused to `
                + `execute it - the response is not JavaScript`;
        }
        return `HTTP ${response.status} (content-type ${type})`;
    } catch (error) {
        return `not reachable from this browser (${(error && error.message) || 'blocked'})`;
    }
};

/**
 * Names currently defined on window, for comparing before and after a script runs.
 *
 * @returns {Set<String>}
 */
const globalNames = () => {
    try {
        return new Set(Object.keys(window));
    } catch (error) {
        return new Set();
    }
};

/**
 * Look at what a URL actually returned, when a script loaded but published nothing.
 *
 * A proxy or web filter that answers with an HTML block page and a 200 status is
 * indistinguishable from success to a script tag: it loads, the browser fails to
 * parse it as JavaScript, and no global appears. So is a bundle that throws while
 * evaluating. Reading the body tells the two apart.
 *
 * @param {String} url
 * @returns {Promise<String>}
 */
const inspectResponse = async(url) => {
    try {
        const response = await fetch(url, {mode: 'cors', cache: 'no-store'});
        const type = (response.headers.get('content-type') || 'none').split(';')[0];
        const body = await response.text();
        const head = body.slice(0, 60).replace(/\s+/g, ' ');

        if (/^\s*<(!doctype|html)/i.test(body)) {
            return `HTTP ${response.status}, but the body is HTML, not JavaScript `
                + `(content-type ${type}, starts "${head}") - something is intercepting `
                + `the request and returning a page of its own`;
        }
        return `HTTP ${response.status}, content-type ${type}, ${body.length} bytes, `
            + `starts "${head}"`;
    } catch (error) {
        return `the script tag loaded it, but fetch could not re-read it `
            + `(${(error && error.message) || 'blocked'})`;
    }
};

/**
 * Load the Zoom Meeting SDK bundle, trying each candidate URL in turn.
 *
 * The SDK is not bundled with the plugin: Zoom requires the client and the service to
 * stay within a supported version range, so a copy pinned in the repository would go
 * stale and start failing to connect.
 *
 * @param {String} version the SDK version configured by the site administrator
 * @param {String} override a full URL from site configuration
 * @returns {Promise<Object>} resolves with the ZoomMtgEmbedded global
 */
const loadSdk = async(version, override) => {
    const already = findSdkGlobal();
    if (already) {
        return already;
    }

    // A Content-Security-Policy refusal is reported here and nowhere else; the script
    // tag just fires a bare error event.
    const blocked = [];
    const cspListener = (event) => {
        blocked.push(`"${event.blockedURI || 'inline'}" was blocked by the `
            + `Content-Security-Policy directive "${event.violatedDirective}"`);
    };
    document.addEventListener('securitypolicyviolation', cspListener);

    // A bundle that throws while evaluating still fires onload, so the only trace is
    // an uncaught error against the script's filename.
    const thrown = [];
    const errorListener = (event) => {
        const where = event.filename
            ? `${event.filename}:${event.lineno}`
            : 'an unnamed script';
        thrown.push(`${where} threw: ${event.message || 'no message available'}`);
    };
    window.addEventListener('error', errorListener, true);

    const candidates = sdkUrlCandidates(version, override);
    const notes = [];

    try {
        for (const url of candidates) {
            const before = globalNames();
            try {
                await loadScript(url);
            } catch (error) {
                notes.push(`${url} - ${await probeUrl(url)}`);
                continue;
            }

            const sdk = findSdkGlobal();
            if (sdk) {
                return sdk;
            }

            // Whatever it published is the clue: an unexpected name means we are
            // looking for the wrong one, and nothing at all means it never really ran.
            const added = [...globalNames()].filter((name) => !before.has(name));
            const published = added.length
                ? `it published: ${added.slice(0, 12).join(', ')}`
                : `it published no new globals at all, so it did not run to completion`;
            notes.push(`${url} - loaded but no usable SDK; ${published}; `
                + `${await inspectResponse(url)}`);
        }
    } finally {
        document.removeEventListener('securitypolicyviolation', cspListener);
        window.removeEventListener('error', errorListener, true);
    }

    if (blocked.length) {
        throw new Error(`Could not load the Zoom Meeting SDK. ${blocked.join('; ')}. `
            + `The Zoom SDK needs script-src https://source.zoom.us, `
            + `'wasm-unsafe-eval', worker-src blob: and connect-src https://*.zoom.us `
            + `wss://*.zoom.us in the site's Content-Security-Policy.`);
    }

    // Zoom's Meeting SDK requires a secure context; on plain HTTP it cannot start.
    const context = window.isSecureContext
        ? ''
        : ` This page is not a secure context (${window.location.protocol}//), and the `
            + `Zoom Meeting SDK requires HTTPS.`;

    const threw = thrown.length ? ` Errors while evaluating: ${thrown.join('; ')}.` : '';

    throw new Error(`Could not load the Zoom Meeting SDK. `
        + `${notes.join(' | ')}.${threw}${context}`);
};

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

    const sdk = await loadSdk(config.sdkversion, config.sdkurl);

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

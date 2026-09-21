<?php
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
 * English strings for mod_livesession.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Live session';
$string['modulename'] = 'Live session';
$string['modulenameplural'] = 'Live sessions';
$string['modulename_help'] = 'The live session activity schedules a Zoom video call for a course session and runs it inside the course page, so students never leave Moodle to attend.

Attendance is captured automatically from the embedded meeting: the time each student spends in the call, the IP address they joined from and their sign-in details are recorded, and the resulting mark is written to the gradebook with that evidence as feedback.';
$string['pluginadministration'] = 'Live session administration';
$string['modulename_link'] = 'mod/livesession/view';

// Capabilities.
$string['livesession:addinstance'] = 'Add a new live session';
$string['livesession:view'] = 'View a live session and its schedule';
$string['livesession:join'] = 'Join a live session (attendance is recorded)';
$string['livesession:host'] = 'Host a live session';
$string['livesession:viewattendance'] = 'View attendance records, including IP addresses';
$string['livesession:manageattendance'] = 'Correct attendance records';

// Activity form.
$string['sessionname'] = 'Session name';
$string['sessiondescription'] = 'Description';
$string['schedule'] = 'Schedule';
$string['starttime'] = 'Start';
$string['starttime_help'] = 'When the session begins. The Zoom meeting is created for this time and the course calendar entry follows it.';
$string['duration'] = 'Duration';
$string['duration_help'] = 'How long the session is scheduled to run. When attendance is graded as a percentage, this is the figure the percentage is taken from.';
$string['joinwindowbefore'] = 'Students may join early by';
$string['joinwindowbefore_help'] = 'How long before the start time the join button becomes active for students. Teachers with the host capability can always open the room.';
$string['joinwindowafter'] = 'Students may join late by';
$string['joinwindowafter_help'] = 'How long after the scheduled end a student can still enter the meeting. After this the session is closed.';
$string['meetingoptions'] = 'Meeting options';
$string['zoomhostid'] = 'Zoom host';
$string['zoomhostid_help'] = 'The Zoom user the meeting is created under - an email address, a Zoom user id, or "me" for the account the server-to-server app belongs to. The host must be a licensed user on your Zoom account.';
$string['waitingroom'] = 'Enable waiting room';
$string['waitingroom_help'] = 'Students wait until the host admits them. Zoom does not allow a waiting room and join-before-host at the same time, so switching this on disables join-before-host.';
$string['joinbeforehost'] = 'Allow students to join before the host';
$string['muteonentry'] = 'Mute participants on entry';
$string['autorecord'] = 'Automatic recording';
$string['autorecord:none'] = 'Do not record';
$string['autorecord:local'] = 'Record to the host computer';
$string['autorecord:cloud'] = 'Record to the Zoom cloud';
$string['attendanceandgrading'] = 'Attendance and grading';
$string['gradingmethod'] = 'How attendance becomes a grade';
$string['gradingmethod_help'] = 'Not graded: attendance is recorded but no mark is written.

All or nothing: the full mark is awarded once the student meets the attendance requirement, and zero otherwise.

Proportional: the mark is the fraction of the scheduled duration the student attended.';
$string['gradingmethod:none'] = 'Not graded';
$string['gradingmethod:threshold'] = 'All or nothing against the requirement';
$string['gradingmethod:proportional'] = 'Proportional to time attended';
$string['requiredpercent'] = 'Attendance required (%)';
$string['requiredpercent_help'] = 'The share of the scheduled duration a student must attend to count as present. Ignored when a minimum number of minutes is set below.';
$string['requiredminutes'] = 'Attendance required (minutes)';
$string['requiredminutes_help'] = 'An absolute minimum in minutes. Leave at 0 to use the percentage instead.';
$string['latethreshold'] = 'Mark as late after';
$string['latethreshold_help'] = 'A student who first joins more than this long after the start time is recorded as late, even if they go on to meet the attendance requirement.';
$string['recordip'] = 'Record IP addresses';
$string['recordip_help'] = 'Stores the IP address each student joined from against their attendance record. Switch this off if your privacy policy does not cover it.';
$string['ipinfeedback'] = 'Show the attendance evidence in the gradebook';
$string['ipinfeedback_help'] = 'Writes the join time, time attended, sign-in details and IP address into the gradebook feedback for this activity, so a marks export carries the evidence with it.';
$string['completionattendance'] = 'Minutes of attendance required';
$string['completionattendancegroup'] = 'Require attendance';
$string['completionattendancegroup_help'] = 'The activity is marked complete once the student has attended for at least this many minutes.';
$string['completiondetail:attendance'] = 'Attend for at least {$a} minutes';

// View page.
$string['starts'] = 'Starts';
$string['ends'] = 'Ends';
$string['joinopens'] = 'Joining opens';
$string['attendancerequirement'] = 'Attendance required';
$string['joinsession'] = 'Join the session';
$string['startsession'] = 'Start the session';
$string['openinzoom'] = 'Open in the Zoom app instead (host only)';
$string['notopenyet'] = 'This session is not open yet. You will be able to join from {$a}.';
$string['sessionclosed'] = 'This session has finished.';
$string['youleft'] = 'You have left the session. Your attendance has been recorded.';
$string['attendancenotice'] = 'Your attendance in this session is recorded automatically while you are in the meeting.';
$string['attendancenoticeip'] = 'Your attendance in this session is recorded automatically while you are in the meeting. The time you attend, your sign-in details and the IP address you connect from are stored against your record and shown to your teacher.';
$string['yourattendance'] = 'Your attendance';
$string['viewattendance'] = 'View attendance';
$string['nosessions'] = 'There are no live sessions in this course.';

// JavaScript.
$string['connecting'] = 'Connecting to the meeting...';
$string['attendancerecording'] = 'You are in the meeting. Your attendance is being recorded.';
$string['attendancecounter'] = 'Attendance recorded so far: {$a} minute(s).';
$string['meetingended'] = 'You have left the meeting. Your attendance has been saved.';

// Attendance report.
$string['attendance'] = 'Attendance';
$string['status'] = 'Status';
$string['status:present'] = 'Present';
$string['status:late'] = 'Late';
$string['status:partial'] = 'Partial';
$string['status:absent'] = 'Absent';
$string['status:excused'] = 'Excused';
$string['attendedfor'] = 'Time attended';
$string['attendednone'] = 'None';
$string['attendedminutes'] = 'Time attended (minutes)';
$string['attendedminutes_help'] = 'Replaces the automatically captured figure. The grade is recalculated from this value.';
$string['firstjoin'] = 'First joined';
$string['lastleave'] = 'Last left';
$string['joincount'] = 'Times joined';
$string['lastlogin'] = 'Signed in to Moodle at';
$string['ipaddress'] = 'IP address';
$string['firstip'] = 'IP address (first join)';
$string['lastip'] = 'IP address (most recent)';
$string['useragent'] = 'Browser';
$string['overridden'] = 'Corrected by hand';
$string['overriddenshort'] = 'Corrected';
$string['remarks'] = 'Remarks';
$string['editattendance'] = 'Correct attendance';
$string['attendancesaved'] = 'Attendance record saved.';
$string['clearoverride'] = 'Remove the correction';
$string['clearoverride_help'] = 'Discards the manual correction so this record follows the captured attendance data again.';
$string['downloadcsv'] = 'Download attendance (CSV)';
$string['gotogradebook'] = 'Go to the gradebook';
$string['ipnotice'] = 'This report contains personal data - IP addresses and sign-in details. Handle and share it in line with your organisation\'s privacy policy.';
$string['resetattendance'] = 'Delete all attendance records and audit logs';

// Site settings.
$string['settings:s2sheading'] = 'Zoom API credentials (server-to-server OAuth)';
$string['settings:s2sheading_desc'] = 'Used to create, update and delete the meetings behind each session, and to read the participant report afterwards. Create a <strong>Server-to-Server OAuth</strong> app in the Zoom App Marketplace and give it the scopes listed in the plugin README.';
$string['settings:accountid'] = 'Account ID';
$string['settings:accountid_desc'] = 'From the app\'s credentials page.';
$string['settings:clientid'] = 'Client ID';
$string['settings:clientid_desc'] = 'From the app\'s credentials page.';
$string['settings:clientsecret'] = 'Client secret';
$string['settings:clientsecret_desc'] = 'From the app\'s credentials page. Stored in the Moodle configuration table.';
$string['settings:sdkheading'] = 'Zoom Meeting SDK credentials';
$string['settings:sdkheading_desc'] = 'These come from a second app, used only to sign the token that lets a browser into the meeting. Without it the meeting cannot be embedded in the page.<br><br>Zoom has retired the standalone <strong>Meeting SDK</strong> app type, so build a <strong>General App</strong> instead and switch <strong>Meeting SDK</strong> on under its <strong>Embed</strong> tab. No scopes are needed.<br><br><strong>Use the development credential pair</strong> while the app is unpublished; the production pair is rejected until the app is published.';
$string['settings:sdkclientid'] = 'SDK client ID';
$string['settings:sdkclientid_desc'] = 'The General App\'s Client ID, from its App Credentials page. This is what the signature presents to Zoom as the SDK key.';
$string['settings:sdkclientsecret'] = 'SDK client secret';
$string['settings:sdkclientsecret_desc'] = 'The General App\'s Client Secret, from its App Credentials page. Never sent to the browser - it only signs the short-lived join token.';
$string['settings:sdkversion'] = 'Meeting SDK version';
$string['settings:sdkversion_desc'] = 'The Meeting SDK release loaded in the browser. Zoom only supports clients within a few releases of the current one, so keep this reasonably current. If the meeting panel reports that the SDK could not be loaded, this is usually the setting to correct - check the latest version on npm under @zoom/meetingsdk.';
$string['settings:sdkvendorurl'] = 'Meeting SDK vendor directory';
$string['settings:sdkvendorurl_desc'] = 'Leave blank unless Zoom moves these files. The CDN build of the SDK does not bundle React; it expects React, ReactDOM, Redux and Lodash to already be on the page, and throws "React is not defined" without them. The plugin loads them from <code>https://source.zoom.us/{version}/lib/vendor/</code>, and skips them entirely if React is already present.';
$string['settings:sdkurl'] = 'Meeting SDK URL';
$string['settings:sdkurl_desc'] = 'Leave blank unless the meeting panel cannot load the SDK. Blank means the plugin tries the known Zoom CDN paths in turn. To pin one exactly, enter the full URL; <code>{version}</code> is replaced with the version above. The URL Zoom currently serves is <code>https://source.zoom.us/{version}/zoom-meeting-embedded-{version}.min.js</code>.';
$string['settings:defaultsheading'] = 'Defaults for new sessions';
$string['settings:defaulthost'] = 'Default Zoom host';
$string['settings:defaulthost_desc'] = 'Pre-filled as the Zoom host on new sessions. Use "me" for the account the server-to-server app belongs to.';
$string['settings:defaultduration'] = 'Default duration';
$string['settings:defaultduration_desc'] = 'Pre-filled duration for new sessions.';
$string['settings:aicompanion'] = 'Enable Zoom AI Companion';
$string['settings:aicompanion_desc'] = 'Off by default. When off, new and re-saved sessions ask Zoom not to start the AI meeting summary or AI questions, which is what removes the "Meeting Summary has been enabled" notice participants see on joining. It also means no AI transcript or summary of a class is generated. Existing sessions pick this up the next time the activity is saved.<br><br>If your Zoom account has AI Companion <em>locked on</em> at the account or group level, that lock wins and the notice will still appear; it then has to be turned off in the Zoom web portal under Settings &gt; AI Companion.';
$string['settings:heartbeatinterval'] = 'Attendance check-in interval';
$string['settings:heartbeatinterval_desc'] = 'How often the meeting page tells the server the student is still present. Shorter is more precise and produces more requests.';
$string['settings:staletimeout'] = 'Presume departed after';
$string['settings:staletimeout_desc'] = 'If a browser stops checking in for this long it is treated as gone, and no time beyond its last check-in is credited. This is the largest amount of time a closed laptop can earn.';
$string['settings:privacyheading'] = 'Attendance evidence and privacy';
$string['settings:privacyheading_desc'] = 'This plugin records identifying details as evidence of attendance. Make sure your privacy notice covers them before switching them on.';
$string['settings:recordip'] = 'Record IP addresses by default';
$string['settings:recordip_desc'] = 'Sets the default for new sessions. Clearing this stops IP addresses being recorded on sessions that use the default, but does not remove addresses already stored.';
$string['settings:ipinfeedback'] = 'Write the evidence into the gradebook by default';
$string['settings:ipinfeedback_desc'] = 'Sets the default for new sessions. Gradebook feedback is visible to the student as well as the teacher.';
$string['settings:logretention'] = 'Delete audit log entries after';
$string['settings:logretention_desc'] = 'Reserved for a future release; the audit log is currently kept until the activity or course is deleted. Set to zero to keep everything.';

// Tasks and events.
$string['task:reconcileattendance'] = 'Reconcile live session attendance with Zoom';
$string['task:closestalesegments'] = 'Close abandoned live session attendance records';
$string['event:sessionjoined'] = 'Live session joined';

// Errors.
$string['error:zoomapi'] = 'Zoom rejected the request: {$a}';
$string['error:notconfigured'] = 'This site has no Zoom credentials configured. A site administrator needs to complete the settings for the Live session plugin before meetings can be created or joined.';
$string['error:sdknotconfigured'] = 'The Zoom Meeting SDK credentials are missing, so the meeting cannot be embedded in the page. A site administrator needs to add them in the Live session plugin settings.';
$string['error:tokenunreachable'] = 'Could not reach Zoom to obtain an access token: {$a}';
$string['error:tokenrejected'] = 'Zoom rejected the plugin\'s credentials: {$a}';
$string['error:apiunreachable'] = 'Could not reach the Zoom API: {$a}';
$string['error:apistatus'] = 'The Zoom API returned HTTP status {$a}.';
$string['error:apimalformed'] = 'The Zoom API returned a response that could not be read.';
$string['error:syncfailed'] = 'The Zoom meeting for this session could not be created or updated. This is what Zoom said the last time this activity was saved - it is not a live check: {$a} Save the activity again to retry, or use the Zoom connection test in the plugin settings to check the credentials now.';

// Connection test.
$string['test:title'] = 'Zoom connection test';
$string['test:settingsdesc'] = 'Checks the credentials above against Zoom right now. Use this rather than the error shown on an activity page, which only reports what happened the last time that activity was saved.';
$string['test:intro'] = 'This contacts Zoom using the credentials above, bypassing the cached access token. Nothing is created or changed in your Zoom account.';
$string['test:run'] = 'Test the connection to Zoom';
$string['test:runagain'] = 'Run the test again';
$string['test:running'] = 'Plugin version on disk';
$string['test:installed'] = 'Plugin version in the database';
$string['test:upgradepending'] = 'The code on disk is a different version from the one the database knows about. The upgrade has not been run, so the site is still using the old code. Go to Site administration > Notifications to finish it.';
$string['test:configuration'] = 'Configuration';
$string['test:setting'] = 'Setting';
$string['test:value'] = 'Value';
$string['test:notset'] = 'Not set';
$string['test:result'] = 'Result';
$string['test:step'] = 'Step';
$string['test:outcome'] = 'Outcome';
$string['test:detail'] = 'Detail';
$string['test:pass'] = 'Passed';
$string['test:fail'] = 'Failed';
$string['test:steptoken'] = 'Obtain an access token (server-to-server OAuth)';
$string['test:tokenok'] = 'Zoom issued an access token ({$a} characters).';
$string['test:stephost'] = 'Read meetings for the host "{$a}"';
$string['test:hostok'] = 'The host exists and the meeting scopes are granted ({$a} meetings visible).';
$string['test:allpassed'] = 'Everything passed. The plugin can talk to Zoom, so a live session saved from now on should get a meeting.';
$string['test:backtosettings'] = 'Back to the plugin settings';
$string['error:nomeeting'] = 'No Zoom meeting has been created for this session yet.';
$string['error:outsidejoinwindow'] = 'This session is not open for joining at the moment.';
$string['error:notavailable'] = 'This session is not available to you.';
$string['error:cannotjoin'] = 'You do not have permission to join this session.';
$string['error:joinfailed'] = 'The meeting could not be started.';
$string['error:usernotenrolled'] = 'That user cannot attend this session.';
$string['error:durationtooshort'] = 'The session must run for at least one minute.';
$string['error:scalesnotsupported'] = 'Attendance produces a numeric mark, so scales are not supported here. Choose a point value instead.';
$string['error:percentrange'] = 'Enter a percentage between 0 and 100.';
$string['error:negativeminutes'] = 'Enter zero or more minutes.';
$string['error:requiredexceedsduration'] = 'The attendance requirement cannot be longer than the session itself.';

// Privacy API.
$string['privacy:metadata:attendance'] = 'The attendance record kept for each participant in a live session.';
$string['privacy:metadata:attendance:userid'] = 'The user the record belongs to.';
$string['privacy:metadata:attendance:status'] = 'Whether the user was present, late, partial, absent or excused.';
$string['privacy:metadata:attendance:firstjoin'] = 'When the user first entered the meeting.';
$string['privacy:metadata:attendance:lastseen'] = 'When the user was last confirmed to be in the meeting.';
$string['privacy:metadata:attendance:lastleave'] = 'When the user last left the meeting.';
$string['privacy:metadata:attendance:duration'] = 'Total time the user spent in the meeting.';
$string['privacy:metadata:attendance:joincount'] = 'How many times the user entered the meeting.';
$string['privacy:metadata:attendance:firstip'] = 'The IP address the user first joined from.';
$string['privacy:metadata:attendance:lastip'] = 'The IP address the user most recently connected from.';
$string['privacy:metadata:attendance:useragent'] = 'The browser the user joined with.';
$string['privacy:metadata:attendance:sessionhash'] = 'A one-way hash of the Moodle session, used to show that activity came from a single sign-in without storing the session identifier.';
$string['privacy:metadata:attendance:usernamesnapshot'] = 'The username the user held when they joined.';
$string['privacy:metadata:attendance:lastlogin'] = 'When the user last signed in to Moodle before joining.';
$string['privacy:metadata:attendance:zoomparticipantuuid'] = 'The identifier Zoom assigned the user in the meeting.';
$string['privacy:metadata:attendance:grade'] = 'The mark derived from the user\'s attendance.';
$string['privacy:metadata:attendance:remarks'] = 'Any note a teacher added when correcting the record.';
$string['privacy:metadata:log'] = 'An audit trail of each join, departure and correction, kept as evidence behind the attendance mark.';
$string['privacy:metadata:log:userid'] = 'The user the entry concerns.';
$string['privacy:metadata:log:action'] = 'What happened: a join, a departure, a reconciliation or a correction.';
$string['privacy:metadata:log:ipaddress'] = 'The IP address the request came from.';
$string['privacy:metadata:log:useragent'] = 'The browser the request came from.';
$string['privacy:metadata:log:sessionhash'] = 'A one-way hash of the Moodle session the request belonged to.';
$string['privacy:metadata:log:extra'] = 'Additional detail about the entry.';
$string['privacy:metadata:log:timecreated'] = 'When the entry was recorded.';
$string['privacy:metadata:zoom'] = 'To place a user in the meeting, their display name is sent to Zoom. For users joining as the host, their email address is sent as well.';
$string['privacy:metadata:zoom:fullname'] = 'The name shown to other participants in the meeting.';
$string['privacy:metadata:zoom:email'] = 'The host\'s email address, sent so Zoom can grant host privileges.';
$string['privacy:attendancepath'] = 'Live session attendance';
$string['privacy:logpath'] = 'Live session attendance audit trail';

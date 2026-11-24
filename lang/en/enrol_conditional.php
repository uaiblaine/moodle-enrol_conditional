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
 * Strings for component 'enrol_conditional', language 'en'.
 *
 * @package    enrol_conditional
 * @copyright  2010 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['cannotenrolreason'] = 'You cannot enrol yourself in this course: {$a}';
$string['canntenrol'] = 'Enrolment is disabled or inactive';
$string['canntenrolearly'] = 'You cannot enrol yet; enrolment starts on {$a}.';
$string['canntenrollate'] = 'You cannot enrol any more, since enrolment ended on {$a}.';
$string['capacitybusy'] = 'Many people are enrolling in this course right now. Please try again in a moment.';
$string['checkmodeevents'] = 'When something the conditions depend on changes';
$string['checkmodeevents_desc'] = 'Re-check by default when an event observed by the conditions happens.';
$string['checkmodelogin'] = 'When the user logs in';
$string['checkmodelogin_desc'] = 'Re-check by default the enrolments of users when they log in.';
$string['checkmodeperiodic'] = 'Periodically, in the scheduled task';
$string['checkmodeperiodic_desc'] = 'Re-check by default the enrolments in the scheduled task.';
$string['conditionaction'] = 'When the conditions are no longer met';
$string['conditionaction_desc'] = 'Default action when a re-check finds that an enrolled user no longer meets the conditions.';
$string['conditionactionnone'] = 'Nothing (only record it)';
$string['conditionactionremove'] = 'Remove the enrolment';
$string['conditionactionsuspend'] = 'Suspend the enrolment';
$string['conditional:config'] = 'Configure self enrol instances';
$string['conditional:enrolself'] = 'Self enrol in course';
$string['conditional:holdkey'] = 'Appear as the self enrolment key holder';
$string['conditional:manage'] = 'Manage enrolled users';
$string['conditional:unenrol'] = 'Unenrol users from course';
$string['conditional:unenrolself'] = 'Unenrol self from the course';
$string['conditionreactivate'] = 'Reactivate when the conditions are met again';
$string['conditionreactivate_desc'] = 'Reactivate by default the enrolments suspended by this plugin when the conditions are met again.';
$string['conditions'] = 'Conditions';
$string['conditions_help'] = 'What a user must meet to enrol themselves through this method. Conditions are combined in groups, and groups can hold other groups.

* **All of these**: the group is met when every item in it is met.
* **Any of these**: the group is met when at least one of its items is met.
* **Must not be met**: turns a condition or a group around, so that it is met only when what it describes is not true.

**When to use each one**

* Only one department, and only from a given date: all of these, with a "User profile" condition and a "Date" condition.
* Members of either of two cohorts: any of these, with two "Cohort" conditions.
* Everybody except those who already completed the introductory course: a "Course completed" condition marked must not be met.

**Example**: all of these, holding "Cohort" set to "2027 interns" and "Course completed" set to "Safety basics" and marked must not be met: the 2027 interns who have not yet done the safety course can enrol.

With no conditions, everybody who may enrol themselves is accepted. The conditions are checked when the user enrols, the same way on the enrol page, in the mobile app and by the method itself. The "Enrol me" link and the course listing icon are still shown to users who do not meet them, and the enrol page tells them which conditions are missing. A condition that cannot be evaluated, for example because its type was uninstalled, refuses every new enrolment until a teacher fixes it. Existing enrolments can be checked again at login, periodically, or in the background shortly after something the conditions depend on changes ("Check the conditions"); one that fails is then suspended, removed or only recorded ("When the conditions are no longer met"), and only the suspensions this method made are ever reactivated.';
$string['conditionsnotmet'] = 'You cannot enrol yourself in this course because:';
$string['conditionsnotmetshort'] = 'You do not meet the conditions to enrol yourself in this course.';
$string['confirmbulkdeleteenrolment'] = 'Are you sure you want to delete these user enrolments?';
$string['defaultrole'] = 'Default role assignment';
$string['defaultrole_desc'] = 'Select the role which should be assigned to users during self enrolment.';
$string['deleteselectedusers'] = 'Delete selected user enrolments';
$string['editselectedusers'] = 'Edit selected user enrolments';
$string['enroledas'] = '{$a->enrol} as \'{$a->role}\'';
$string['enrolenddate'] = 'End date';
$string['enrolenddate_help'] = 'The last moment at which users can enrol themselves through this method.

* Until then, users who meet the conditions can enrol.
* Afterwards, the "Enrol me" link and the course listing icon disappear, and the enrol page says that enrolment has ended.
* Users already enrolled keep their enrolment: how long it lasts is set in "Enrolment duration".

**Example**: enabled and set to 15 March 2027, 23:59, to close the enrolment of a class that starts on 16 March.

Inert while its "Enable" box is not ticked: enrolment then stays open with no end. It cannot be earlier than "Start date".';
$string['enrolenddaterror'] = 'Enrolment end date cannot be earlier than start date';
$string['enrolkeyrequired'] = 'An enrolment key will be required.';
$string['enrolme'] = 'Enrol me';
$string['enrolperiod'] = 'Enrolment duration';
$string['enrolperiod_desc'] = 'Default length of time that the enrolment is valid. If set to zero, the enrolment duration will be unlimited by default.';
$string['enrolperiod_help'] = 'How long each enrolment made through this method lasts, counted from the moment the user enrols.

* Each user gets an end date of their own: the moment they enrolled plus this duration.
* When it passes, the user can no longer enter the course, and the site\'s "Enrolment expiry action" decides whether the enrolment is kept, suspended with its roles removed, or removed.
* "Notify before enrolment expires" can warn the teacher, and the user, beforehand.
* A change applies only to enrolments made afterwards; the end dates already given stay as they are.

**Example**: 90 days, for a self-paced course that every learner must finish within three months of joining.

Inert while its "Enable" box is not ticked: enrolments then never end.';
$string['enrolstartdate'] = 'Start date';
$string['enrolstartdate_help'] = 'The first moment at which users can enrol themselves through this method.

* Before then, the "Enrol me" link and the course listing icon are hidden, and the enrol page says when enrolment starts.
* From then on, users who meet the conditions can enrol, until "End date" when it is set.
* It does not delay access: users who enrol enter the course at once.

**Example**: enabled and set to 1 February 2027, 08:00, to open enrolment on the first day of the registration week.

Inert while its "Enable" box is not ticked: users can then enrol straight away.';
$string['eventconditionsnotmet'] = 'Enrolment conditions no longer met';
$string['eventuserenrolmentreactivated'] = 'User enrolment reactivated by conditions';
$string['eventuserenrolmentsuspended'] = 'User enrolment suspended by conditions';
$string['expiredaction'] = 'Enrolment expiry action';
$string['expiredaction_desc'] = 'What happens, on the whole site, when an enrolment made through this method reaches its end date. Unenrolling the user deletes some of their data and settings in the course.';
$string['expiredaction_help'] = 'What happens to an enrolment made through this method when its end date passes. It applies to every course of the site.

* **Keep user enrolled**: the enrolment stays, but the user can no longer enter the course. Giving the enrolment a later end date lets them in again.
* **Disable course enrolment and remove roles**: the enrolment is suspended and the roles it gave in the course are removed.
* **Unenrol user from course**: the enrolment is removed, and some of the user\'s data and settings in the course, such as group memberships, are deleted.

**When to use each one**

* Teachers extend late learners by hand: keep user enrolled.
* Expired learners must lose their roles in the course while their enrolment stays on record: disable course enrolment and remove roles.
* Expired learners must leave no enrolment behind: unenrol user from course.

**Example**: keep user enrolled, on a site where teachers give late learners two more weeks from the participants page.

Inert for enrolments without an end date, that is when "Enrolment duration" is not set and nobody gave the enrolment an end date by hand. It is applied by the scheduled task "Synchronise self enrolments task".';
$string['expiryinactivemessageenrolledbody'] = 'Hi {$a->user},

Your enrolment in the course {$a->course} expires on {$a->timeend} as you have not accessed it in the last {$a->inactivetime} days.

To keep your enrolment active, log in and access <a href="{$a->url}">{$a->course}</a> before {$a->timeend}.';
$string['expiryinactivemessageenrolledsubject'] = 'Your enrolment is expiring: {$a->course}';
$string['expirymessageenrolledbody'] = 'Dear {$a->user},

This is a notification that your enrolment in the course \'{$a->course}\' is due to expire on {$a->timeend}.

If you need help, please contact {$a->enroller}.';
$string['expirymessageenrolledsubject'] = 'Self enrolment expiry notification';
$string['expirymessageenrollerbody'] = 'Self enrolment in the course \'{$a->course}\' will expire within the next {$a->threshold} for the following users:

{$a->users}

To extend their enrolment, go to {$a->extendurl}';
$string['expirymessageenrollersubject'] = 'Self enrolment expiry notification';
$string['expirynotifyall'] = 'Teacher and enrolled user';
$string['expirynotifyenroller'] = 'Teacher only';
$string['groupgone'] = 'You are enrolled in the course, but the group your key belonged to no longer exists, so you were not added to a group.';
$string['groupkey'] = 'Use group enrolment keys';
$string['groupkey_desc'] = 'Use group enrolment keys by default.';
$string['groupkey_help'] = 'Whether the enrolment key of a group of the course also lets users enrol through this method, and puts them in that group.

* **Yes**: a user who types the key of a group is enrolled and added to that group. The method\'s own "Enrolment key" still works, and adds the user to no group.
* **No**: only the method\'s own "Enrolment key" is accepted.

**When to use each one**

* Each class gets its own key, and learners must land in their class\'s group: yes.
* One key for everybody, and the teacher makes the groups later: no.

**Example**: yes, with "Iris-2027" in the "Enrolment key" of the group "Class A" and "Lotus-2027" in that of "Class B"; the method\'s own key is known only to the teachers.

Inert while "Enrolment key" is empty: group keys are accepted only when the method has a key of its own. A group\'s key is set on that group\'s settings page, and the method\'s key cannot be equal to any of them. Whatever key is used, the conditions are still checked, and a wrong key never shows the hint.';
$string['keyholder'] = 'You should have received this enrolment key from:';
$string['longtimenosee'] = 'Unenrol inactive after';
$string['longtimenosee_desc'] = 'Default for new instances: how long a user may go without opening the course, or without logging in, before being unenrolled.';
$string['longtimenosee_help'] = 'How long a user enrolled through this method may stay away before being unenrolled.

* **Never**: nobody is unenrolled for being inactive.
* **7 days** to **1800 days**: a user who has not opened the course for that long, or has not logged in to the site for that long, is unenrolled, and some of their data and settings in the course are deleted.

**When to use each one**

* Learners hold places that others are waiting for: a short period, such as 30 days, together with "Max enrolled users".
* A reference course that people come back to now and then: never.

**Example**: 60 days, with "Notify before enrolment expires" set to "Teacher and enrolled user" and a "Notification threshold" of 7 days, so that learners are warned a week before.

It is applied by the scheduled task "Synchronise self enrolments task", to suspended enrolments as well. A user who never opened the course is unenrolled only when they have not logged in for that long.';
$string['maxenrolled'] = 'Max enrolled users';
$string['maxenrolled_desc'] = 'Default maximum number of users who can be enrolled through a new instance at the same time. 0 means no limit.';
$string['maxenrolled_help'] = 'The largest number of users who can be enrolled through this method at the same time.

* 0 means no limit.
* Once that many users are enrolled, the "Enrol me" link and the course listing icon disappear, and nobody else can enrol until a place frees up.
* The places are counted again at the very moment of enrolling, so two people enrolling at once never both take the last place: one of them is told that the course is full.
* A suspended enrolment, including one suspended because the conditions are no longer met, still takes a place; a removed one frees it.

**Example**: 40, for a workshop with 40 seats.

Only enrolments made through this method count: users enrolled by any other method take no place.';
$string['maxenrolledreached'] = 'Maximum number of users allowed to self-enrol was already reached.';
$string['messageprovider:expiry_notification'] = 'Self enrolment expiry notifications';
$string['newenrols'] = 'Allow new self enrolments';
$string['newenrols_desc'] = 'Allow users to self enrol into new courses by default.';
$string['newenrols_help'] = 'Whether new users can still enrol themselves through this method.

* **Yes**: users who may enrol themselves and meet the conditions can enrol, between "Start date" and "End date" and while places are left.
* **No**: nobody new can enrol, and the "Enrol me" link and the course listing icon disappear. Users already enrolled keep their access, and their enrolments are still checked against the conditions.

**When to use each one**

* Enrolment is open: yes.
* The class is full or closed, but the course goes on for those already in it: no.

**Example**: no, once the 40 learners of a cohort are in and the teacher wants no late arrivals.

Inert while "Keep current self enrolments active" is "No": the field is then disabled, since the method takes no enrolment at all.';
$string['nopassword'] = 'No enrolment key required.';
$string['password'] = 'Enrolment key';
$string['password_help'] = 'A key that users must type to enrol themselves through this method, at most 50 characters long.

* Left empty, nobody is asked for a key.
* Filled in, the key is asked once, when the user enrols, and never again. It is checked the same way on the enrol page, in the mobile app and by the method itself.
* Changing or removing the key does not affect users already enrolled.
* With "Use group enrolment keys" set to "Yes", the key of a group of the course is accepted as well.

**Example**: "Harbour-2027", handed to the class on the first day.

The key does not replace the conditions: a user who knows it but does not meet them cannot enrol. The site can require a key in every method, apply the password policy to it, and show its first letter after a wrong attempt.';
$string['passwordinvalid'] = 'Incorrect enrolment key, please try again';
$string['passwordinvalidhint'] = 'That enrolment key was incorrect, please try again<br />
(Here\'s a hint - it starts with \'{$a}\')';
$string['passwordmatchesgroupkey'] = 'This enrolment key is already used as a group enrolment key.';
$string['pluginname'] = 'Conditional self enrolment';
$string['pluginname_desc'] = 'The conditional self enrolment plugin allows users to choose which courses they want to participate in, as long as they meet the conditions configured for each enrolment method. The courses may be protected by an enrolment key.';
$string['privacy:metadata'] = 'The Conditional self enrolment plugin has no tables of its own. Enrolments, role assignments and group memberships it creates are stored and covered by core. The records of the actions taken when the conditions are no longer met, and the lists of users that some conditions keep in the instance settings, are covered by the Enrolment conditions plugin and its condition types.';
$string['requirepassword'] = 'Require enrolment key';
$string['requirepassword_desc'] = 'Require enrolment key in new courses and prevent removing of enrolment key from existing courses.';
$string['role'] = 'Default assigned role';
$string['sendcoursewelcomemessage'] = 'Send course welcome message';
$string['sendcoursewelcomemessage_desc'] = 'Default for new instances: whether users who enrol themselves receive the course welcome message, and from whom.';
$string['sendcoursewelcomemessage_help'] = 'Whether users who enrol themselves through this method receive a welcome message, and who sends it.

* **No**: no message is sent.
* **From the course contact**: sent by the first user of the course who holds one of the roles the site lists in "Course contacts", usually the teacher. If the course has nobody in those roles, no message is sent.
* **From the key holder**: sent by the first user of the course who holds the capability "Appear as the self enrolment key holder". If nobody holds it, no message is sent.
* **From the no-reply address**: sent from the site\'s no-reply address, so replies reach nobody.

**When to use each one**

* Learners should be able to answer their teacher: from the course contact.
* A coordinator hands out the key and answers the questions: from the key holder.
* A large open course where nobody answers mail: from the no-reply address.

**Example**: from the course contact, with a "Custom welcome message" giving the date and link of the first live session.

The text is set in "Custom welcome message", shown unless this is "No". Users enrolled by other methods do not get this message.';
$string['sendexpirynotificationstask'] = 'Self enrolment send expiry notifications task';
$string['showhint'] = 'Show hint';
$string['showhint_desc'] = 'Show the first letter of the enrolment key after a wrong key is typed, except in methods that accept group enrolment keys.';
$string['status'] = 'Keep current self enrolments active';
$string['status_desc'] = 'Enable self enrolment method in new courses.';
$string['status_help'] = 'Whether this enrolment method is on.

* **Yes**: users enrolled through this method can enter the course, and new users can enrol while "Allow new self enrolments" is "Yes".
* **No**: the method is off. Users enrolled through it can no longer enter the course, unless another method enrols them too; nobody can enrol through it; and their enrolments are no longer checked against the conditions.

**When to use each one**

* The course is running: yes.
* The course is over and its learners must no longer enter it, while their enrolments stay on record: no.
* Only new enrolments must stop: yes, with "Allow new self enrolments" set to "No".

**Example**: no, at the end of the term, so that the enrolments and grades stay for the records but nobody enters the course any more.

Switching back to "Yes" gives access again to everybody still enrolled. While it is "No", no expiry notices are sent, but "Unenrol inactive after" still unenrols inactive users.';
$string['syncenrolmentstask'] = 'Synchronise self enrolments task';
$string['unenrol'] = 'Unenrol user';
$string['unenrolselfconfirm'] = 'Do you really want to unenrol yourself from course "{$a}"?';
$string['unenroluser'] = 'Do you really want to unenrol "{$a->user}" from course "{$a->course}"?';
$string['unenrolusers'] = 'Unenrol users';
$string['usepasswordpolicy'] = 'Use password policy';
$string['usepasswordpolicy_desc'] = 'Use standard password policy for enrolment keys.';

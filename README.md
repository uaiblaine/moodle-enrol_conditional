<img src="docs/img/icon.svg" alt="Conditional self enrolment" width="96" align="right">

moodle-enrol_conditional
========================

[![Moodle Plugin CI](https://github.com/uaiblaine/moodle-enrol_conditional/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/uaiblaine/moodle-enrol_conditional/actions/workflows/ci.yml?query=branch%3Amain)

Self enrolment that depends on conditions: a user enrols only when a set of pluggable conditions, combined with AND, OR and NOT, is met.

The method is based on the core self enrolment (`enrol_self`). The conditions can also be checked again at login, periodically or when events happen, suspending or removing enrolments that no longer meet them. A user who does not meet the conditions sees which ones are missing.

Documentation: <https://moodle-by-kelsoncm.github.io/moodle-enrol_conditional/>

![A user who does not meet the conditions sees which ones are missing](docs/img/student-cannot-enrol.png)

![Condition editor](docs/img/course-conditions-editor.png)

This repository is a fork of [moodle-by-kelsoncm/moodle-enrol_conditional](https://github.com/moodle-by-kelsoncm/moodle-enrol_conditional).


Requirements
------------

- Moodle 5.3 LTS or later (`$plugin->requires` 2026100200; tested on Moodle 5.3).
- [`local_enrolconditions`](https://github.com/uaiblaine/moodle-local_enrolconditions) (the version named in this plugin's `version.php` or later), which provides the condition engine and the `enrolcond` subplugins. Moodle does not allow subplugins in enrolment plugins, which is why the engine is a separate plugin. Its documentation: <https://moodle-by-kelsoncm.github.io/moodle-local_enrolconditions/>.


Installation
------------

Install `local_enrolconditions` to folder `/local/enrolconditions` and this plugin to folder `/enrol/conditional`, then visit *Site administration > Notifications*.

See http://docs.moodle.org/en/Installing_plugins for details on installing Moodle plugins.

After installation, enable the method in *Site administration > Plugins > Enrolments > Manage enrol plugins*, then add the "Conditional self enrolment" method to a course. The method is disabled by default in new courses (`enrol_conditional/status`).


Usage
-----

In the course, *Participants > Enrolment methods > Add method > Conditional self enrolment*. Besides the usual self enrolment options, the form has a condition editor. Conditions included with the engine: date, profile field, cohort, completed course, enrolled in other courses, typed list of usernames, file of usernames and lists defined by administrators as SQL queries.

Existing enrolments can be checked again at login, periodically or on events, and then suspended (default), removed or only logged. The plugin reactivates only the suspensions it made itself, recorded by the engine.

Conditions apply only to enrolments made by this method (including its web services `enrol_conditional_enrol_user` and `enrol_conditional_get_instance_info`, implemented in `classes/external/`). Manual, cohort and other enrolment methods are not affected.

From the command line, `php enrol/conditional/cli/sync.php` runs the enrolment sync once.


Capabilities
------------

- `enrol/conditional:config`: add and edit the method in a course (editing teacher, manager).
- `enrol/conditional:manage`: manage the users enrolled by the method (editing teacher, manager).
- `enrol/conditional:unenrol`: unenrol users from the method (editing teacher, manager).
- `enrol/conditional:unenrolself`: unenrol oneself from the course (student).
- `enrol/conditional:enrolself`: enrol oneself through the method (authenticated user).
- `enrol/conditional:holdkey`: be a key holder, shown to users who ask for the enrolment key (no role by default).


Settings
--------

Under *Site administration > Plugins > Enrolments > Conditional self enrolment*:

- `enrol_conditional/requirepassword`, `enrol_conditional/usepasswordpolicy`: require an enrolment key in new instances, and the password policy for it.
- `enrol_conditional/showhint`: show the first letter of the key as a hint.
- `enrol_conditional/expiredaction`, `enrol_conditional/expirynotifyhour`: what happens when an enrolment expires, and the hour of the expiry notices.
- Defaults of new instances: `enrol_conditional/defaultenrol`, `status`, `newenrols`, `roleid`, `enrolperiod`, `expirynotify`, `expirythreshold`, `longtimenosee`, `maxenrolled`, `groupkey`, `sendcoursewelcomemessage`.
- Condition checking: `enrol_conditional/conditionaction` (record only, suspend or remove; default suspend), `enrol_conditional/conditionreactivate` (reactivate suspended enrolments when the conditions are met again; default on), `enrol_conditional/checkmodelogin`, `enrol_conditional/checkmodeperiodic` and `enrol_conditional/checkmodeevents` (when existing enrolments are checked again; default off).

The engine's own settings (`local_enrolconditions/checkbatchsize`, `local_enrolconditions/checktimelimit`, and `enrolcond_sqlquery/*` for the administrator-defined queries) are listed in its README.


Scheduled tasks and caches
--------------------------

- `\enrol_conditional\task\sync_enrolments`: every 10 minutes, applies the expiry and inactivity rules of the instances.
- `\enrol_conditional\task\send_expiry_notifications`: every 10 minutes, sends the expiry notices (message provider `expiry_notification`).
- The periodic condition check runs in the engine's task, `\local_enrolconditions\task\check_conditions`.
- This plugin defines no cache.
- Lock: when an instance has "Max enrolled users", the enrolment counts the places under a lock per instance taken through `\core\lock\lock_config`, so a cluster needs a lock factory shared by every node (core's default database factory is). A lock that is not granted refuses the enrolment.
- With the "events" check mode, the engine queues the adhoc task `\local_enrolconditions\task\recheck_user` and the enrolment changes when it runs, not in the request that fired the event.


Data stored
-----------

**This plugin creates no database table.** The records of the actions taken when the conditions of an enrolment stopped being met are kept by `local_enrolconditions`, in its table `local_enrolconditions_state` (what it keeps and for how long is in that plugin's README). Enrolments themselves are stored by core.

The condition tree of each instance is stored in the instance (`enrol` table), including, for two condition types, lists of usernames typed by a teacher in the condition or uploaded to the course as a file, and the names of the administrator-defined SQL lists a teacher chose.

**Course backups** that include this enrolment method carry the condition tree, hence those username lists and the uploaded files; when the backup includes users, it also carries the open suspension records of the instance, and a restore writes them to the target site.

**Personal data**: the plugin keeps none of its own (its privacy provider declares so); the personal data it relates to is the suspension state per enrolment, which the engine stores and exports, and the username lists above.

**External services.** The plugin sends no data to any external service. It sends the usual enrolment messages (welcome and expiry notices) through Moodle's messaging.


Troubleshooting
---------------

- *The method does not appear when adding an enrolment method*: it is disabled in *Manage enrol plugins*, or `local_enrolconditions` is not installed.
- *Users who meet the conditions cannot enrol*: a condition may be *unknown* (for example its type was uninstalled), which blocks new enrolments; check the condition editor.
- *Suspended users are not reactivated*: only suspensions recorded by the engine are reactivated; a suspension made by a person is left alone.


Credits
-------

Created by Kelson Medeiros ([moodle-by-kelsoncm](https://github.com/moodle-by-kelsoncm)); this repository is a fork that keeps upstream's code and attribution. Based on `enrol_self` from Moodle 5.3 (Petr Skoda and others), also GPL v3 or later.

Issues of the upstream project: <https://github.com/moodle-by-kelsoncm/moodle-enrol_conditional/issues>


Translations
------------

The English strings ship in the package. The Brazilian Portuguese translation is maintained in this repository (`lang/pt_br`) and reaches sites through [AMOS](https://lang.moodle.org/), as the plugins directory requires; it is left out of the release package.


License
-------

This plugin is licensed under the [GNU GPL v3 or later](http://www.gnu.org/copyleft/gpl.html).

Copyright: 2026 Kelson Medeiros (upstream) and others, as in each file; changes in this fork: 2026 Anderson Blaine

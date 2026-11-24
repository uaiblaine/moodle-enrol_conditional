# Changelog

All notable changes to the Conditional self enrolment plugin will be documented in this file.

## 1.1.1+uai.2 - 2026-11-23

### Security
- The method implements `\local_enrolconditions\tree_writer`, so a privacy request erases a username from the lists of its instances. It is the one writer of the tree that does not validate, because the caller is the privacy system; it refuses a row that is not an instance of this method.
- The course upload refuses an enrolment key longer than 50 characters.

### Changed
- One definition of "needs a key" (`enrolment_key::is_required()`): an instance whose key is `0` now shows the key form, the key icon and the key field in the app information.
- Requires `local_enrolconditions` 1.2.0+uai.2 (2026112301).

### Fixed
- Restoring an old backup whose stored tree does not parse keeps the cohort restriction: the tree becomes the cohort condition alone, `customint5` is cleared only once the restriction is in the tree, and the upgrade step 2026110902 now applies the same rule (`cohort_restriction::add_to_tree()`).
- A key of `0` is a key in the password policy and group-key collision checks of the instance form, when enabling an instance, and in the course upload (`enrolment_key::is_key()`).
- Without a limit, the enrolment is decided under a core lock of the instance and the user (`capacitybusy` when it is refused), so two simultaneous requests of one user cannot restart the enrolment period.
- Restoring a backup taken before the cohort restriction became a condition keeps the restriction: `customint5` becomes a cohort condition in the restored tree; restored from another site, it matches nobody.
- A second request of an already enrolled user no longer restarts the enrolment period: the check is repeated right before the write, under the capacity lock when there is a limit.
- When the group of a group key is deleted during the enrolment, the user stays enrolled and is told they were not added to a group.

### Added
- Tests for thirty guards that no test held, a test of the tree writer through the privacy provider of the engine, and a mutation spec (`mutations/`).

## 1.1.1+uai.1 - 2026-11-23

### Changed
- Course listings and the course navigation decide whether to show the method from the instance alone (status, dates, new enrolments, limit, existing enrolment); the conditions are evaluated on the enrol page, in the key form, in `get_enrol_info()`, in the web services and at the enrolment. A listing with a condition tree costs the same queries as one without. The "Enrol me" link and the listing icon can therefore appear for a user who does not meet the conditions; the enrol page says which are not met.
- `add_instance()` refuses a condition tree that the edit form would refuse, and `update_instance()` does the same when the tree changes; an unchanged stored tree is not validated again, so the course upload can still update the other fields of an instance whose tree stopped validating. Restore keeps storing the tree after a structure check only: an invalid tree evaluates to unknown and nobody enrols until it is fixed.
- Restore recreates a suspension record only for an enrolment the restore itself created, and only when the backup comes from the same site, so a merge into an existing course never hands an enrolment a teacher suspended by hand to the engine.
- With "Max enrolled users" set, the enrolment re-counts the places under a lock per instance; two web nodes can no longer both fill the last place. A lock that is not granted refuses the enrolment (`capacitybusy`).
- The enrolment itself checks the enrolment key and any group key, with `hash_equals()`, through one helper (`\enrol_conditional\local\enrolment_key`) shared with the key form and the web service; an empty key never matches.
- The web services moved to `\enrol_conditional\external\get_instance_info` and `\enrol_conditional\external\enrol_user` (same function names, still in the mobile service, now also available to AJAX). `enrol_conditional_get_instance_info` refuses an instance of another method, validates the context of the instance's course category and returns only the declared fields. `externallib.php` and `locallib.php` are removed.
- The bulk operations *Edit selected user enrolments* and *Delete selected user enrolments* act only on the enrolments of this method, even when a selected user is also enrolled by another one.
- A stored NULL in the check modes (`customint7`) means "at enrolment only" both in PHP and in the SQL the engine uses; `has_check_mode()` is removed.
- The holder of the enrolment key is shown through the `enrol_conditional/keyholder` template, which escapes the name.
- The admin settings *Enrolment expiry action*, *Unenrol inactive after*, *Max enrolled users* and *Send course welcome message* have descriptions of their own instead of their help text.
- The privacy provider explains which core subsystem and which part of `local_enrolconditions` cover each write.

- The instance form passes the method name and the instance id to the condition editor, and the tree validation passes the course context and the tree stored before it, so on the form, in `add_instance()` and in `update_instance()` a condition that names something the saving user may not reference (a course they cannot see) is refused; a condition already stored unchanged is not asked again.
- Requires `local_enrolconditions` 1.2.0+uai.1 (2026112300): the events check mode now queues a re-check task per user instead of acting in the request that fired the event.

### Added
- PHPUnit tests of the reach check on the three save paths (`conditions_reach_test`) and of the queued events re-check; the periodic check tests use `check_periodic()`.
- PHPUnit tests of the listing cost, the limit under a lock, restore ownership, the validation on every save path (with a test that lists the writers of the condition tree from the source), the bulk operations with users enrolled by several methods, the web services through the web service layer and the privacy provider's compliance.

## 1.1.1 - 2026-11-19

### Changed
- The bulk operation *Edit selected user enrolments* updates each enrolment with the enrolment API (`update_user_enrol()`) instead of `$DB->execute()`. The event `user_enrolment_updated` is now triggered only for enrolments that actually change.

### Added
- PHPUnit tests for the bulk operation.

## 1.1.0 - 2026-11-18

### Changed
- The re-checks (login, scheduled task and events), the suspension, removal and reactivation of enrolments and their records moved to `local_enrolconditions` 1.1.0, which any enrolment method can now use. The plugin implements `\local_enrolconditions\enforceable`; its behaviour and settings are unchanged.
- Requires `local_enrolconditions` 1.1.0. The upgrade moves the records of `enrol_conditional_state` to `local_enrolconditions_state`, removes the old table and moves the settings *Enrolments per periodic check* and the position of the periodic check to `local_enrolconditions`.
- The scheduled task *Re-check the conditions of existing enrolments* now belongs to `local_enrolconditions`.
- The events `user_enrolment_suspended`, `user_enrolment_reactivated` and `conditions_not_met` are now triggered by `local_enrolconditions`; the classes of `enrol_conditional` stay only to display the existing logs.
- The privacy provider of `enrol_conditional` declares no personal data: `local_enrolconditions` covers the records.

## 1.0.1 - 2026-11-18

### Changed
- README and documentation point to the repository renamed to `moodle-by-kelsoncm/moodle-enrol_conditional`; the CI installs `moodle-by-kelsoncm/moodle-local_enrolconditions`.
- Documentation with screenshots, the plugin icon as logo and favicon, and links to the `local_enrolconditions` documentation site.
- Screenshots of the documentation (`docs/img/`) stay out of the plugin ZIP.

## 1.0.0 - 2026-11-09

### Added
- Plugin skeleton copied from `enrol_self` of Moodle 5.3 (`MOODLE_503_STABLE`, version 2026100500), renamed to `enrol_conditional`.
- The deprecated `enrol_self_enrol_form` class was not copied; the dynamic form `enrol_conditional\form\enrol_form` is used.
- Depends on `local_enrolconditions`, which declares the `enrolcond` subplugin type: Moodle only accepts subplugins under `mod`, `editor`, `tool` and `local` plugins.
- New courses do not get an instance of this method by default (`defaultenrol` is off).
- Action when a re-check finds that the conditions are no longer met: nothing (logged once per episode), suspend (default) or remove, plus automatic reactivation. Stored in `customint8` (action, plus 16 to reactivate).
- Table `enrol_conditional_state`: the plugin only reactivates enrolments that it suspended itself and that nobody changed since. Unknown results never suspend nor reactivate.
- Events `user_enrolment_suspended`, `user_enrolment_reactivated` and `conditions_not_met`.
- The cohort restriction of `enrol_self` (`customint5`) was removed in favour of the `enrolcond_cohort` condition. The upgrade adds a cohort condition to the tree of instances that used it.
- Check modes per instance in `customint7` (bitmask): at enrolment (always), at login (`sync_user_enrolments`), periodically (scheduled task `check_conditions`, in batches of `checkbatchsize` that resume where the previous run stopped) and on the events observed by the conditions (catch-all observer with a cached map).
- Privacy API: records of `enrol_conditional_state` are exported and deleted with the course context.
- Backup and restore: open records of `enrol_conditional_state` go with the users, so restored suspensions can still be reactivated; condition trees from another site lose their cohort and course references. An invalid tree is kept and keeps blocking self enrolment.
- Behat: self enrolment allowed and blocked by the conditions (step `the course "..." has a conditional self enrolment method with conditions:`), and a Behat step in CI.

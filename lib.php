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
 * Conditional self enrolment plugin.
 *
 * @package    enrol_conditional
 * @copyright  2010 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\single_button;
use core_enrol\output\enrol_page;
use enrol_conditional\local\cohort_restriction;
use enrol_conditional\local\enrolment_key;

/**
 * Conditional self enrolment plugin implementation: core's self enrolment, gated by a condition tree.
 *
 * @author Petr Skoda
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_conditional_plugin extends enrol_plugin implements
    \local_enrolconditions\enforceable,
    \local_enrolconditions\tree_writer {
    /** @var string Column of the enrol table with the condition tree (JSON). */
    public const FIELD_CONDITIONS = 'customtext2';

    /** @var string Column with the action when the conditions stop being met, plus FLAG_REACTIVATE. */
    public const FIELD_ACTION = 'customint8';

    /** @var int Bits of FIELD_ACTION with the action. */
    public const ACTION_MASK = 15;

    /** @var int Bit of FIELD_ACTION: reactivate enrolments suspended by the plugin when the conditions are met again. */
    public const FLAG_REACTIVATE = 16;

    /** @var string Column with the bitmask of when the conditions are checked. */
    public const FIELD_CHECKMODES = 'customint7';

    /** @var array Form field (and site setting) of each optional check mode. */
    public const CHECK_MODE_FIELDS = \local_enrolconditions\output\enforcement_form::CHECK_MODE_FIELDS;

    /** @var string Restore mapping that marks the user enrolments created by the restore in progress. */
    public const RESTORE_CREATED_ENROLMENT = 'enrol_conditional_created_ue';

    /** @var stdClass|null Last enroller found by get_enroller(). */
    protected $lasternoller = null;
    /** @var int Instance id of the last enroller found by get_enroller(). */
    protected $lasternollerinstanceid = 0;

    /**
     * Returns optional enrolment information icons.
     *
     * This is used in course list for quick overview of enrolment options.
     *
     * We are not using single instance parameter because sometimes
     * we might want to prevent icon repetition when multiple instances
     * of one type exist. One instance may also produce several icons.
     *
     * @param array $instances all enrol instances of this type in one course
     * @return array of pix_icon
     */
    public function get_info_icons(array $instances) {
        $key = false;
        $nokey = false;
        foreach ($instances as $instance) {
            // Core asks for the icons once per course box of a listing: the conditions are explained on the
            // enrol page instead of being evaluated here for every course.
            if ($this->get_self_enrol_status($instance, false, false) !== true) {
                continue;
            }
            if (enrolment_key::is_required($instance) || $instance->customint1) {
                $key = true;
            } else {
                $nokey = true;
            }
        }
        $icons = [];
        if ($nokey) {
            $icons[] = new pix_icon('withoutkey', get_string('pluginname', 'enrol_conditional'), 'enrol_conditional');
        }
        if ($key) {
            $icons[] = new pix_icon('withkey', get_string('pluginname', 'enrol_conditional'), 'enrol_conditional');
        }
        return $icons;
    }

    /**
     * Returns localised name of enrol instance
     *
     * @param stdClass $instance (null is accepted too)
     * @return string
     */
    public function get_instance_name($instance) {
        global $DB;

        if (!empty($instance->name)) {
            return format_string($instance->name, true, ['context' => context_course::instance($instance->courseid)]);
        }

        $enrolname = get_string('pluginname', 'enrol_' . $this->get_name());
        if (!empty($instance->roleid)) {
            $role = $DB->get_record('role', ['id' => $instance->roleid]);
            if ($role) {
                $rolename = role_get_name($role, context_course::instance($instance->courseid, IGNORE_MISSING));
                return get_string('enroledas', 'enrol_conditional', ['enrol' => $enrolname, 'role' => $rolename]);
            }
        }

        return $enrolname;
    }

    /**
     * Returns the user the welcome message is sent from.
     *
     * Core looks for the key holder through the enrol/self:holdkey capability; this method uses
     * enrol/conditional:holdkey instead and delegates every other option to core.
     *
     * @param int $sendoption Send option (ENROL_SEND_EMAIL_FROM_*).
     * @param context $context Course context.
     * @return stdClass|null The contact user, or null when there is none.
     */
    public function get_welcome_message_contact(int $sendoption, context $context): ?stdClass {
        if ($sendoption !== ENROL_SEND_EMAIL_FROM_KEY_HOLDER) {
            return parent::get_welcome_message_contact($sendoption, $context);
        }
        [$sort] = users_order_by_sql('u');
        $keyholders = get_users_by_capability($context, 'enrol/conditional:holdkey', 'u.*', $sort);
        return $keyholders ? array_values($keyholders)[0] : null;
    }

    /**
     * Whether the roles assigned by this plugin are protected.
     *
     * @return bool
     */
    public function roles_protected() {
        // Users may tweak the roles later.
        return false;
    }

    /**
     * Whether users with the unenrol capability may unenrol other users.
     *
     * @param stdClass $instance Enrolment instance.
     * @return bool
     */
    public function allow_unenrol(stdClass $instance) {
        // Users with unenrol cap may unenrol other users manually manually.
        return true;
    }

    /**
     * Whether users with the manage capability may change enrolment period and status.
     *
     * @param stdClass $instance Enrolment instance.
     * @return bool
     */
    public function allow_manage(stdClass $instance) {
        // Users with manage cap may tweak period and status.
        return true;
    }

    /**
     * Whether the "Enrol me" link is shown for this instance.
     *
     * Decided without the conditions, like the listing icons: the link leads to the enrol page, which
     * evaluates them and says which are not met.
     *
     * @param stdClass $instance Enrolment instance.
     * @return bool
     */
    public function show_enrolme_link(stdClass $instance) {
        return $this->get_self_enrol_status($instance, false, false) === true;
    }

    /**
     * Return true if we can add a new instance to this course.
     *
     * @param int $courseid
     * @return boolean
     */
    public function can_add_instance($courseid) {
        $context = context_course::instance($courseid, MUST_EXIST);

        if (!has_capability('moodle/course:enrolconfig', $context) || !has_capability('enrol/conditional:config', $context)) {
            return false;
        }

        return true;
    }

    /**
     * Returns edit icons for the page with list of instances.
     *
     * @param stdClass $instance
     * @return array
     */
    public function get_action_icons(stdClass $instance): array {
        global $OUTPUT;

        $context = context_course::instance($instance->courseid);

        $icons = [];
        if (has_any_capability(['enrol/conditional:config', 'moodle/course:editcoursewelcomemessage'], $context)) {
            $linkparams = [
                'courseid' => $instance->courseid,
                'id' => $instance->id,
                'type' => $instance->enrol,
            ];
            $editlink = new moodle_url('/enrol/editinstance.php', $linkparams);
            $icon = new pix_icon('t/edit', get_string('edit'), 'core', ['class' => 'iconsmall']);
            $icons[] = $OUTPUT->action_icon($editlink, $icon);
        }

        return $icons;
    }

    /**
     * Enrols the current user in the course through this instance.
     *
     * Every door (the enrol page, the key form, the web service) calls this method, which checks itself
     * what decides the enrolment, whatever the caller checked before: a guest is refused, the instance must be
     * open to new enrolments, the user must hold enrol/conditional:enrolself, the conditions are evaluated now,
     * the enrolment key must match and, when the instance has a limit, the places are counted under a lock, so
     * that two web nodes cannot both fill the last place.
     *
     * @param stdClass $instance Enrolment instance.
     * @param stdClass|null $data Submitted data; enrolpassword holds the key.
     * @return void
     * @throws moodle_exception When any of the checks above refuses.
     */
    public function enrol_conditional(stdClass $instance, $data = null) {
        global $CFG, $DB, $USER;

        if (!isloggedin() || isguestuser()) {
            throw new moodle_exception('noguestaccess', 'enrol');
        }
        // The places are counted below, under the lock; counting them here as well would decide on a count
        // another node may change before the write.
        $status = $this->get_instance_status($instance, false);
        if ($status !== true) {
            throw new moodle_exception('cannotenrolreason', 'enrol_conditional', '', html_to_text((string) $status, 0, false));
        }
        if (!has_capability('enrol/conditional:enrolself', context_course::instance($instance->courseid))) {
            throw new moodle_exception('canntenrol', 'enrol_conditional');
        }

        $result = $this->evaluate_conditions($instance, (int) $USER->id);
        if ($result !== null && !$result->is_met()) {
            throw new moodle_exception('conditionsnotmetshort', 'enrol_conditional');
        }

        $key = isset($data->enrolpassword) ? (string) $data->enrolpassword : '';
        if (enrolment_key::check($instance, $key) !== enrolment_key::ACCEPTED) {
            throw new moodle_exception('passwordinvalid', 'enrol_conditional');
        }
        $groupid = null;
        if (enrolment_key::is_required($instance) && !empty($instance->customint1)) {
            $groupid = enrolment_key::find_group((int) $instance->courseid, $key);
        }

        $timestart = time();
        $timeend = $instance->enrolperiod ? $timestart + $instance->enrolperiod : 0;

        if ((int) $instance->customint3 > 0) {
            $written = $this->enrol_within_capacity($instance, (int) $USER->id, $timestart, $timeend);
        } else {
            $written = $this->enrol_unless_enrolled($instance, (int) $USER->id, $timestart, $timeend);
        }
        if (!$written) {
            // Another request enrolled this user first: nothing was written, so the period they got stays.
            return;
        }

        \core\notification::success(get_string('youenrolledincourse', 'enrol'));

        if ($groupid !== null) {
            require_once($CFG->dirroot . '/group/lib.php');
            // The group was found before the enrolment: another request may have deleted it since. The user is
            // enrolled by now, so the loss is told instead of thrown.
            $group = $DB->get_record('groups', ['id' => $groupid, 'courseid' => $instance->courseid]);
            if ($group) {
                // No component: core_group's privacy provider covers the membership.
                groups_add_member($group, $USER->id);
            } else {
                \core\notification::warning(get_string('groupgone', 'enrol_conditional'));
            }
        }
    }

    /**
     * Enrols a user unless they are enrolled already, deciding under a lock of this instance and user.
     *
     * The status read at the start of the request may be older than another request's enrolment of the same user,
     * and enrol_user() on an existing enrolment would restart its period. Two simultaneous requests of one user
     * both pass a read taken without a lock, so the read that decides is taken under a core lock of the instance
     * and the user: one user never waits for another, and the lock factory is the site's (shared by every node).
     *
     * @param stdClass $instance Enrolment instance.
     * @param int $userid
     * @param int $timestart
     * @param int $timeend
     * @return bool Whether the enrolment was written.
     * @throws moodle_exception capacitybusy when the lock is not granted; nothing is written then.
     */
    protected function enrol_unless_enrolled(stdClass $instance, int $userid, int $timestart, int $timeend): bool {
        global $DB;
        $factory = \core\lock\lock_config::get_lock_factory('enrol_conditional');
        $lock = $factory->get_lock('enrol_' . $instance->id . '_' . $userid, 10);
        if (!$lock) {
            throw new moodle_exception('capacitybusy', 'enrol_conditional');
        }
        try {
            if ($DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $userid])) {
                return false;
            }
            $this->enrol_user($instance, $userid, $instance->roleid, $timestart, $timeend);
            return true;
        } finally {
            $lock->release();
        }
    }

    /**
     * Enrols a user when the instance still has a free place, deciding under a lock per instance.
     *
     * The enrol page counted the places earlier, possibly on another web node; the count that decides is
     * the one taken under the lock, right before the write, together with whether the user is enrolled already.
     *
     * @param stdClass $instance Enrolment instance with a limit (customint3 > 0).
     * @param int $userid
     * @param int $timestart
     * @param int $timeend
     * @return bool Whether the enrolment was written; false when the user was enrolled already.
     * @throws moodle_exception capacitybusy when the lock is not granted, maxenrolledreached when full.
     */
    protected function enrol_within_capacity(stdClass $instance, int $userid, int $timestart, int $timeend): bool {
        global $DB;
        $factory = \core\lock\lock_config::get_lock_factory('enrol_conditional');
        $lock = $factory->get_lock('capacity_' . $instance->id, 10);
        if (!$lock) {
            throw new moodle_exception('capacitybusy', 'enrol_conditional');
        }
        try {
            if ($DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $userid])) {
                return false;
            }
            if ($DB->count_records('user_enrolments', ['enrolid' => $instance->id]) >= (int) $instance->customint3) {
                throw new moodle_exception('maxenrolledreached', 'enrol_conditional');
            }
            $this->enrol_user($instance, $userid, $instance->roleid, $timestart, $timeend);
            return true;
        } finally {
            $lock->release();
        }
    }

    #[\Override]
    public function enrol_page_hook(stdClass $instance) {
        global $CFG, $OUTPUT, $USER, $PAGE;

        $buttonurl = null;
        $buttontext = '';
        $buttonattrs = [];
        $body = '';
        $title = $this->get_instance_name($instance);

        $enrolstatus = $this->can_self_enrol($instance);
        if ($enrolstatus === true) {
            if (enrolment_key::is_required($instance)) {
                // Self-enrolment with password. Display a button to open a form in a modal.
                $body = get_string('enrolkeyrequired', 'enrol_conditional');
                $buttonurl = $PAGE->url;
                $buttonattrs = [
                    'data-id' => $instance->courseid,
                    'data-instance' => $instance->id,
                    'data-form' => enrol_conditional\form\enrol_form::class,
                    'data-title' => $title,
                ];
                $PAGE->requires->js_call_amd('enrol_conditional/enrol_page', 'initEnrol', [$instance->id]);
            } else {
                // Self-enrolment without password. Display a button to self enrol. If button is pressed - enrol the user.
                if (optional_param('action', null, PARAM_TEXT) === 'enrol' && confirm_sesskey()) {
                    $this->enrol_conditional($instance, (object)[]);
                    return '';
                }
                $body = get_string('nopassword', 'enrol_conditional');
                $buttonurl = new moodle_url($PAGE->url, ['action' => 'enrol', 'sesskey' => sesskey()]);
            }
            $buttontext = get_string('enrolme', 'enrol_conditional');
        } else if (isguestuser()) {
            // User is not logged in. Display a button to login.
            $buttonurl = new moodle_url(get_login_url());
            $body = get_string('noguestaccess', 'enrol');
            $buttontext = get_string('continue');
        } else if (!$enrolstatus) {
            // No reason why user can not use this method, do not display anything.
            return '';
        } else {
            $body = $enrolstatus;
        }

        $notification = new \core\output\notification($body, 'info', false);
        $notification->set_extra_classes(['mb-0']);
        $enrolpage = new enrol_page(
            instance: $instance,
            header: $title,
            body: $OUTPUT->render($notification),
            buttons: $buttonurl ?
                [new single_button($buttonurl, $buttontext, 'get', single_button::BUTTON_PRIMARY, $buttonattrs)] :
            []
        );
        return $OUTPUT->render($enrolpage);
    }

    /**
     * Checks if the current user can enrol themselves, conditions included.
     *
     * Used by the enrol page, the key form, get_enrol_info() and the web services. Listings decide through
     * get_self_enrol_status() without the conditions.
     *
     * @param stdClass $instance enrolment instance
     * @param bool $checkuserenrolment Whether a guest is refused with a login button.
     * @return bool|string true if successful, else error message or false.
     */
    public function can_self_enrol(stdClass $instance, $checkuserenrolment = true) {
        return $this->get_self_enrol_status($instance, (bool) $checkuserenrolment, true);
    }

    /**
     * Whether the current user can enrol themselves, and why not.
     *
     * The cheap facts come first: the instance (status, dates, new enrolments, limit), an existing enrolment
     * and the capability. The conditions run queries of their own per condition, so the listing paths, which
     * core calls once per course of a page, leave them to the enrol page.
     *
     * @param stdClass $instance Enrolment instance.
     * @param bool $checkguest Whether a guest is refused with a login button.
     * @param bool $checkconditions Whether the condition tree is evaluated.
     * @return bool|string true if the user can enrol, else the reason (HTML).
     */
    protected function get_self_enrol_status(stdClass $instance, bool $checkguest, bool $checkconditions) {
        global $OUTPUT, $USER;

        if ($checkguest && isguestuser()) {
            return get_string('noguestaccess', 'enrol') . $OUTPUT->continue_button(get_login_url());
        }

        $result = $this->is_self_enrol_available($instance);
        if ($result !== true) {
            return $result;
        }

        if (!has_capability('enrol/conditional:enrolself', context_course::instance($instance->courseid))) {
            return get_string('canntenrol', 'enrol_conditional');
        }

        // Guests are never enrolled (enrol_conditional() refuses them too), so they skip the conditions.
        if ($checkconditions && isloggedin() && !isguestuser()) {
            $result = $this->evaluate_conditions($instance, (int) $USER->id);
            if ($result !== null && !$result->is_met()) {
                return $this->render_condition_failures($result);
            }
        }

        return true;
    }

    /**
     * Does this plugin support some way to self enrol?
     *
     * Checks the instance and whether the current user is already enrolled, never the capability or the
     * conditions: core calls it for guests to decide whether enrolling could be possible after login.
     *
     * @param stdClass $instance enrolment instance
     * @return bool|string - true means "Enrol me in this course" link could be available
     */
    public function is_self_enrol_available(stdClass $instance) {
        return $this->get_instance_status($instance, true);
    }

    /**
     * Whether the instance takes a new enrolment of the current user, and why not.
     *
     * @param stdClass $instance Enrolment instance.
     * @param bool $countplaces Whether the limit of enrolled users is checked.
     * @return bool|string true, else the reason.
     */
    protected function get_instance_status(stdClass $instance, bool $countplaces) {
        global $DB, $USER;

        if ($instance->status != ENROL_INSTANCE_ENABLED) {
            return get_string('canntenrol', 'enrol_conditional');
        }

        if ($instance->enrolstartdate != 0 && $instance->enrolstartdate > time()) {
            return get_string('canntenrolearly', 'enrol_conditional', userdate($instance->enrolstartdate));
        }

        if ($instance->enrolenddate != 0 && $instance->enrolenddate < time()) {
            return get_string('canntenrollate', 'enrol_conditional', userdate($instance->enrolenddate));
        }

        if (!$instance->customint6) {
            // New enrols not allowed.
            return get_string('canntenrol', 'enrol_conditional');
        }

        if ($DB->record_exists('user_enrolments', ['userid' => $USER->id, 'enrolid' => $instance->id])) {
            return get_string('canntenrol', 'enrol_conditional');
        }

        if ($countplaces && $instance->customint3 > 0) {
            // Max enrol limit specified.
            $count = $DB->count_records('user_enrolments', ['enrolid' => $instance->id]);
            if ($count >= $instance->customint3) {
                // Bad luck, no more self enrolments here.
                return get_string('maxenrolledreached', 'enrol_conditional');
            }
        }

        return true;
    }

    /**
     * Evaluates the conditions of an instance for a user.
     *
     * A stored tree that is no longer valid counts as unknown, so it refuses new enrolments.
     *
     * @param stdClass $instance Enrolment instance.
     * @param int $userid
     * @return \local_enrolconditions\tree_result|null Null when the instance has no conditions.
     */
    public function evaluate_conditions(stdClass $instance, int $userid): ?\local_enrolconditions\tree_result {
        return \local_enrolconditions\local\enforcer::evaluate($this->get_enforcement_tree($instance), $userid, $instance);
    }

    #[\Override]
    public function get_enforcement_tree(stdClass $instance): ?string {
        return $instance->{self::FIELD_CONDITIONS} ?? null;
    }

    /**
     * Stores a tree the engine changed to erase personal data, without validating it.
     *
     * The caller is the privacy system, possibly with no user, so the reach check of update_instance() does not
     * apply. The door still checks what it can: the row must exist and be an instance of this method.
     *
     * @param stdClass $instance Instance as read before the change; only its id is used.
     * @param string $json Tree as \local_enrolconditions\tree::to_json() returns it.
     * @throws coding_exception When the id is not an instance of this method.
     */
    #[\Override]
    public function set_enforcement_tree(stdClass $instance, string $json): void {
        global $DB;
        $current = $DB->get_record('enrol', ['id' => $instance->id ?? 0, 'enrol' => 'conditional']);
        if (!$current) {
            throw new coding_exception('Not an instance of the conditional enrolment method');
        }
        $current->{self::FIELD_CONDITIONS} = $json;
        $current->timemodified = time();
        $DB->update_record('enrol', $current);
        \core\event\enrol_instance_updated::create_from_record($current)->trigger();
        $this->purge_event_map();
    }

    #[\Override]
    public function get_enforcement_check_modes(stdClass $instance): int {
        return self::get_check_modes($instance);
    }

    #[\Override]
    public function get_enforcement_action(stdClass $instance): int {
        return self::get_condition_action($instance);
    }

    #[\Override]
    public function get_enforcement_reactivate(stdClass $instance): bool {
        return self::get_reactivate($instance);
    }

    /**
     * SQL selecting the instances that check their conditions in a mode.
     *
     * Must read the column as get_check_modes() does: a NULL column means CHECK_ENROL only.
     *
     * @param int $mode One of the CHECK_ constants.
     * @return array|null [sql where over the alias e, params]
     */
    #[\Override]
    public function get_enforcement_mode_sql(int $mode): ?array {
        global $DB;
        $column = 'COALESCE(e.' . self::FIELD_CHECKMODES . ', ' . self::CHECK_ENROL . ')';
        return [$DB->sql_bitand($column, $mode) . ' <> 0', []];
    }

    /**
     * HTML that explains which conditions the user does not meet.
     *
     * @param \local_enrolconditions\tree_result $result
     * @return string
     */
    protected function render_condition_failures(\local_enrolconditions\tree_result $result): string {
        global $OUTPUT;
        $failures = new \local_enrolconditions\output\failures($result, get_string('conditionsnotmet', 'enrol_conditional'));
        return $OUTPUT->render($failures);
    }

    /**
     * Return information for enrolment instance containing list of parameters required
     * for enrolment, name of enrolment plugin etc.
     *
     * @param stdClass $instance enrolment instance
     * @return stdClass instance info.
     */
    public function get_enrol_info(stdClass $instance) {

        $instanceinfo = new stdClass();
        $instanceinfo->id = $instance->id;
        $instanceinfo->courseid = $instance->courseid;
        $instanceinfo->type = $this->get_name();
        $instanceinfo->name = $this->get_instance_name($instance);
        $instanceinfo->status = $this->can_self_enrol($instance);

        if (enrolment_key::is_required($instance)) {
            $instanceinfo->requiredparam = new stdClass();
            $instanceinfo->requiredparam->enrolpassword = get_string('password', 'enrol_conditional');
        }

        // If enrolment is possible and password is required then return ws function name to get more information.
        if ((true === $instanceinfo->status) && enrolment_key::is_required($instance)) {
            $instanceinfo->wsfunction = 'enrol_conditional_get_instance_info';
        }
        return $instanceinfo;
    }

    /**
     * Add new instance of enrol plugin with default settings.
     * @param stdClass $course
     * @return int id of new instance
     */
    public function add_default_instance($course) {
        $fields = $this->get_instance_defaults();

        if ($this->get_config('requirepassword')) {
            $fields['password'] = \core\di::get(\core\authentication\password::class)->generate(20);
        }

        return $this->add_instance($course, $fields);
    }

    /**
     * Returns defaults for new instances.
     * @return array
     */
    public function get_instance_defaults() {
        $expirynotify = $this->get_config('expirynotify');
        if ($expirynotify == 2) {
            $expirynotify = 1;
            $notifyall = 1;
        } else {
            $notifyall = 0;
        }

        $fields = [];
        $fields['status']          = $this->get_config('status');
        $fields['roleid']          = $this->get_config('roleid');
        $fields['enrolperiod']     = $this->get_config('enrolperiod');
        $fields['expirynotify']    = $expirynotify;
        $fields['notifyall']       = $notifyall;
        $fields['expirythreshold'] = $this->get_config('expirythreshold');
        $fields['customint1']      = $this->get_config('groupkey');
        $fields['customint2']      = $this->get_config('longtimenosee');
        $fields['customint3']      = $this->get_config('maxenrolled');
        $fields['customint4']      = $this->get_config('sendcoursewelcomemessage');
        $fields['customint6']      = $this->get_config('newenrols');
        $fields[self::FIELD_ACTION] = self::get_action_field(null);
        $fields[self::FIELD_CHECKMODES] = self::get_default_check_modes();

        return $fields;
    }

    /**
     * Send welcome email to specified user.
     *
     * @param stdClass $instance
     * @param stdClass $user user record
     * @return void
     * @deprecated since Moodle 4.4
     * @see \enrol_plugin::send_course_welcome_message_to_user()
     * @todo MDL-81185 Final deprecation in Moodle 4.8.
     */
    #[\core\attribute\deprecated('enrol_plugin::send_course_welcome_message_to_user', since: '4.4', mdl: 'MDL-4188')]
    protected function email_welcome_message($instance, $user) {
        \core\deprecation::emit_deprecation(__FUNCTION__);
        $this->send_course_welcome_message_to_user(
            instance: $instance,
            userid: $user->id,
            sendoption: $instance->customint4,
            message: $instance->customtext1,
        );
    }

    /**
     * Sync all meta course links.
     *
     * @param progress_trace $trace
     * @param int $courseid one course, empty mean all
     * @return int 0 means ok, 1 means error, 2 means plugin disabled
     */
    public function sync(progress_trace $trace, $courseid = null) {
        global $DB;

        if (!enrol_is_enabled('conditional')) {
            $trace->finished();
            return 2;
        }

        // Unfortunately this may take a long time, execution can be interrupted safely here.
        core_php_time_limit::raise();
        raise_memory_limit(MEMORY_HUGE);

        $trace->output('Verifying self-enrolments...');

        $params = ['now' => time(), 'useractive' => ENROL_USER_ACTIVE, 'courselevel' => CONTEXT_COURSE];
        $coursesql = "";
        if ($courseid) {
            $coursesql = "AND e.courseid = :courseid";
            $params['courseid'] = $courseid;
        }

        // Note: the logic of self enrolment guarantees that user logged in at least once (=== u.lastaccess set)
        // and that user accessed course at least once too (=== user_lastaccess record exists).

        // First deal with users that did not log in for a really long time - they do not have user_lastaccess records.
        $sql = "SELECT e.*, ue.userid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON (e.id = ue.enrolid AND e.enrol = 'conditional' AND e.customint2 > 0)
                  JOIN {user} u ON u.id = ue.userid
                 WHERE :now - u.lastaccess > e.customint2
                       $coursesql";
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $instance) {
            $userid = $instance->userid;
            unset($instance->userid);
            $this->unenrol_user($instance, $userid);
            $days = $instance->customint2 / DAYSECS;
            $trace->output("unenrolling user $userid from course $instance->courseid " .
                "as they did not log in for at least $days days", 1);
        }
        $rs->close();

        // Now unenrol from course user did not visit for a long time.
        $sql = "SELECT e.*, ue.userid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON (e.id = ue.enrolid AND e.enrol = 'conditional' AND e.customint2 > 0)
                  JOIN {user_lastaccess} ul ON (ul.userid = ue.userid AND ul.courseid = e.courseid)
                 WHERE :now - ul.timeaccess > e.customint2
                       $coursesql";
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $instance) {
            $userid = $instance->userid;
            unset($instance->userid);
            $this->unenrol_user($instance, $userid);
            $days = $instance->customint2 / DAYSECS;
            $trace->output("unenrolling user $userid from course $instance->courseid " .
                "as they did not access the course for at least $days days", 1);
        }
        $rs->close();

        $trace->output('...user self-enrolment updates finished.');
        $trace->finished();

        $this->process_expirations($trace, $courseid);

        return 0;
    }

    /**
     * Notify users about enrolment expiration.
     *
     * Users may be notified by the expiration time of enrollment or unenrollment due to inactivity. The latter is checked in
     * the last condition of the where clause:
     * days of inactivity + number of days in advance to send the notification > days of inactivity allowed before unenrollment
     *
     * @param int $timenow Current time.
     * @param string $name Name of this enrol plugin.
     * @param progress_trace $trace (accepts bool for backwards compatibility only)
     * @return void
     */
    protected function fetch_users_and_notify_expiry(int $timenow, string $name, progress_trace $trace): void {
        global $DB, $CFG;

        $sql = "SELECT ue.*, e.expirynotify, e.notifyall, e.expirythreshold, e.courseid, c.fullname, e.customint2,
                       COALESCE(ul.timeaccess, 0) AS timeaccess, ue.timestart
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON (e.id = ue.enrolid AND e.enrol = :name AND e.expirynotify > 0  AND e.status = :enabled)
                  JOIN {course} c ON (c.id = e.courseid)
                  JOIN {user} u ON (u.id = ue.userid AND u.deleted = 0 AND u.suspended = 0)
                  LEFT JOIN {user_lastaccess} ul ON (ul.userid = u.id AND ul.courseid = c.id)
                 WHERE ue.status = :active
                       AND ((ue.timeend > 0 AND ue.timeend > :now1 AND ue.timeend < (e.expirythreshold + :now2))
                            OR (e.customint2 > 0 AND (:now3 - COALESCE(ul.timeaccess, 0) + e.expirythreshold > e.customint2)))
              ORDER BY ue.enrolid ASC, u.lastname ASC, u.firstname ASC, u.id ASC";
        $params = [
            'name' => $name,
            'enabled' => ENROL_INSTANCE_ENABLED,
            'active' => ENROL_USER_ACTIVE,
            'now1' => $timenow,
            'now2' => $timenow,
            'now3' => $timenow,
        ];

        $rs = $DB->get_recordset_sql($sql, $params);

        $lastenrollid = 0;
        $users = [];

        foreach ($rs as $ue) {
            $expirycond = ($ue->timeend > 0) && ($ue->timeend > $timenow) && ($ue->timeend < ($ue->expirythreshold + $timenow));
            $inactivitycond = ($ue->customint2 > 0) && (($timenow - $ue->timeaccess + $ue->expirythreshold) > $ue->customint2);

            $user = $DB->get_record('user', ['id' => $ue->userid]);

            if ($expirycond) {
                if ($lastenrollid && $lastenrollid != $ue->enrolid) {
                    $this->notify_expiry_enroller($lastenrollid, $users, $trace);
                    $users = [];
                }
                $lastenrollid = $ue->enrolid;

                $enroller = $this->get_enroller($ue->enrolid);
                $context = context_course::instance($ue->courseid);

                $users[] = [
                    'fullname' => fullname($user, has_capability('moodle/site:viewfullnames', $context, $enroller)),
                    'timeend' => $ue->timeend,
                ];
            }
            // Notifications to inactive users only if inactive time limit is set.
            if ($inactivitycond && $ue->notifyall) {
                $ue->message = 'expiryinactivemessageenrolledbody';
                $lastaccess = $ue->timeaccess;
                if (!$lastaccess) {
                    $lastaccess = $ue->timestart;
                }
                $ue->inactivetime = floor(($timenow - $lastaccess) / DAYSECS);
                $this->notify_expiry_enrolled($user, $ue, $trace);
            }

            if ($expirycond) {
                if (!$ue->notifyall) {
                    continue;
                }

                if ($ue->timeend - $ue->expirythreshold + 86400 < $timenow) {
                    // Notify enrolled users only once at the start of the threshold.
                    $trace->output("user $ue->userid was already notified that enrolment in course $ue->courseid expires on " .
                        userdate($ue->timeend, '', $CFG->timezone), 1);
                    continue;
                }

                $this->notify_expiry_enrolled($user, $ue, $trace);
            }
        }
        $rs->close();

        if ($lastenrollid && $users) {
            $this->notify_expiry_enroller($lastenrollid, $users, $trace);
        }
    }

    /**
     * Returns the user who is responsible for self enrolments in given instance.
     *
     * Usually it is the first editing teacher - the person with "highest authority"
     * as defined by sort_by_roleassignment_authority() having 'enrol/conditional:manage'
     * capability.
     *
     * @param int $instanceid enrolment instance id
     * @return stdClass user record
     */
    protected function get_enroller($instanceid) {
        global $DB;

        if ($this->lasternollerinstanceid == $instanceid && $this->lasternoller) {
            return $this->lasternoller;
        }

        $instance = $DB->get_record('enrol', ['id' => $instanceid, 'enrol' => $this->get_name()], '*', MUST_EXIST);
        $context = context_course::instance($instance->courseid);

        if ($users = get_enrolled_users($context, 'enrol/conditional:manage')) {
            $users = sort_by_roleassignment_authority($users, $context);
            $this->lasternoller = reset($users);
            unset($users);
        } else {
            $this->lasternoller = parent::get_enroller($instanceid);
        }

        $this->lasternollerinstanceid = $instanceid;

        return $this->lasternoller;
    }

    /**
     * Builds the body of the expiry notification sent to the enrolled user.
     *
     * @param stdClass $user Enrolled user.
     * @param stdClass $ue User enrolment record.
     * @param string $name Enrolment method name.
     * @param stdClass $enroller User the message is sent from.
     * @param context $context Course context.
     * @return string
     */
    protected function get_expiry_message_body(
        stdClass $user,
        stdClass $ue,
        string $name,
        stdClass $enroller,
        context $context
    ): string {
        $a = new stdClass();
        $a->course = format_string($ue->fullname, true, ['context' => $context]);
        $a->user = fullname($user, true);
        // If the enrolment duration is disabled, replace timeend with other data.
        if ($ue->timeend == 0) {
            $timenow = time();
            $lastaccess = $ue->timeaccess > 0 ? $ue->timeaccess : $ue->timestart;
            $ue->timeend = $timenow + ($ue->customint2 - ($timenow - $lastaccess));
        }
        $a->timeend  = userdate($ue->timeend, '', $user->timezone);
        $a->enroller = fullname($enroller, has_capability('moodle/site:viewfullnames', $context, $user));
        if (isset($ue->inactivetime)) {
            $a->inactivetime = $ue->inactivetime;
        }
        $a->url = new moodle_url('/course/view.php', ['id' => $ue->courseid]);
        // The inactivity notice is marked by fetch_users_and_notify_expiry(); every other notice is the expiry one.
        if (($ue->message ?? '') === 'expiryinactivemessageenrolledbody') {
            return get_string('expiryinactivemessageenrolledbody', 'enrol_conditional', $a);
        }
        return get_string('expirymessageenrolledbody', 'enrol_conditional', $a);
    }

    /**
     * Restore instance and map settings.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $course
     * @param int $oldid
     */
    public function restore_instance(restore_enrolments_structure_step $step, stdClass $data, $course, $oldid) {
        global $DB;
        if ($step->get_task()->get_target() == backup::TARGET_NEW_COURSE) {
            $merge = false;
        } else {
            $merge = [
                'courseid'   => $data->courseid,
                'enrol'      => $this->get_name(),
                'status'     => $data->status,
                'roleid'     => $data->roleid,
            ];
        }
        if ($merge && $instances = $DB->get_records('enrol', $merge, 'id')) {
            $instance = reset($instances);
            $instanceid = $instance->id;
        } else {
            $samesite = $step->get_task()->is_samesite();
            // A backup taken before the cohort restriction became a condition carries it in customint5, which
            // nothing reads: it joins the tree first, so that from another site it is made unknown with the rest.
            $oldcohort = (int) ($data->customint5 ?? 0);
            // The column is cleared once the restriction is in the tree, never before: a tree that does not
            // parse comes out as the cohort condition alone.
            if ($oldcohort !== 0) {
                $stored = $data->{self::FIELD_CONDITIONS} ?? null;
                $data->{self::FIELD_CONDITIONS} = cohort_restriction::add_to_tree($stored, $oldcohort);
            }
            $data->customint5 = 0;
            try {
                $json = $data->{self::FIELD_CONDITIONS} ?? null;
                $data->{self::FIELD_CONDITIONS} = \local_enrolconditions\tree::from_json($json)
                    ->for_restore($samesite)->to_json();
                // Structure only: a tree whose types or configuration do not validate on this site is kept,
                // and evaluates to unknown or not met, so nobody enrols until a teacher fixes it.
                $instanceid = $this->insert_instance($course, (array) $data);
            } catch (\local_enrolconditions\invalid_tree_exception $e) {
                // Keep the invalid tree: it evaluates to unknown and blocks self enrolment until fixed, instead of
                // removing the conditions.
                $step->log('Invalid condition tree in enrolment instance ' . $oldid . ': ' . $e->getMessage(), backup::LOG_WARNING);
                $instanceid = enrol_plugin::add_instance($course, (array) $data);
                $this->purge_event_map();
            }
        }
        $step->set_mapping('enrol', $oldid, $instanceid);
    }

    /**
     * Restore user enrolment.
     *
     * Records, in the restore's own mappings, the enrolments this restore creates: only those may receive the
     * suspension records of the backup ({@see restore_enrol_conditional_plugin::process_enrol_conditional_state()}).
     * An enrolment that already existed in the target instance keeps whoever suspended it.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $instance
     * @param int $userid
     * @param int $oldinstancestatus
     */
    public function restore_user_enrolment(restore_enrolments_structure_step $step, $data, $instance, $userid, $oldinstancestatus) {
        global $DB;
        $existed = $DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $userid]);
        $this->enrol_user($instance, $userid, null, $data->timestart, $data->timeend, $data->status);
        if (!$existed) {
            $ueid = (int) $DB->get_field('user_enrolments', 'id', ['enrolid' => $instance->id, 'userid' => $userid]);
            if ($ueid) {
                $step->set_mapping(self::RESTORE_CREATED_ENROLMENT, $ueid, $ueid);
            }
        }
    }

    /**
     * Restore role assignment.
     *
     * @param stdClass $instance
     * @param int $roleid
     * @param int $userid
     * @param int $contextid
     */
    public function restore_role_assignment($instance, $roleid, $userid, $contextid) {
        // This is necessary only because we may migrate other types to this instance,
        // we do not use component in manual or self enrol.
        role_assign($roleid, $userid, $contextid, '', 0);
    }

    /**
     * Deletes the instance and its condition records.
     *
     * @param stdClass $instance
     * @return void
     */
    public function delete_instance($instance) {
        parent::delete_instance($instance);
        // The observers of local_enrolconditions remove the condition records of the instance.
        $this->purge_event_map();
    }

    /**
     * Re-checks the enrolments of a user that logs in, in the instances with CHECK_LOGIN.
     *
     * @param stdClass $user
     * @return void
     */
    public function sync_user_enrolments($user) {
        if (empty($user->id) || isguestuser($user)) {
            return;
        }
        (new \local_enrolconditions\local\enforcer())->check_user_in_mode((int) $user->id, self::CHECK_LOGIN, 'conditional');
    }

    /**
     * Is it possible to delete enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_delete_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/conditional:config', $context);
    }

    /**
     * Is it possible to hide/show enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_hide_show_instance($instance) {
        $context = context_course::instance($instance->courseid);

        if (!has_capability('enrol/conditional:config', $context)) {
            return false;
        }

        // If the instance is currently disabled, before it can be enabled,
        // we must check whether the password meets the password policies.
        if ($instance->status == ENROL_INSTANCE_DISABLED) {
            if ($this->get_config('requirepassword')) {
                if (!enrolment_key::is_required($instance)) {
                    return false;
                }
            }
            // Only check the password if it is set.
            if (enrolment_key::is_required($instance) && $this->get_config('usepasswordpolicy')) {
                if (!\core\di::get(\core\authentication\password::class)->check_policy($instance->password, $errmsg)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Return an array of valid options for the status.
     *
     * @return array
     */
    protected function get_status_options() {
        $options = [ENROL_INSTANCE_ENABLED  => get_string('yes'),
                         ENROL_INSTANCE_DISABLED => get_string('no')];
        return $options;
    }

    /**
     * Return an array of valid options for the newenrols property.
     *
     * @return array
     */
    protected function get_newenrols_options() {
        $options = [1 => get_string('yes'), 0 => get_string('no')];
        return $options;
    }

    /**
     * Return an array of valid options for the groupkey property.
     *
     * @return array
     */
    protected function get_groupkey_options() {
        $options = [1 => get_string('yes'), 0 => get_string('no')];
        return $options;
    }

    /**
     * Return an array of valid options for the expirynotify property.
     *
     * @return array
     */
    protected function get_expirynotify_options() {
        $options = [0 => get_string('no'),
                         1 => get_string('expirynotifyenroller', 'enrol_conditional'),
                         2 => get_string('expirynotifyall', 'enrol_conditional')];
        return $options;
    }

    /**
     * Return an array of valid options for the longtimenosee property.
     *
     * @return array
     */
    protected function get_longtimenosee_options() {
        $options = [0 => get_string('never'),
                         1800 * 3600 * 24 => get_string('numdays', '', 1800),
                         1000 * 3600 * 24 => get_string('numdays', '', 1000),
                         365 * 3600 * 24 => get_string('numdays', '', 365),
                         180 * 3600 * 24 => get_string('numdays', '', 180),
                         150 * 3600 * 24 => get_string('numdays', '', 150),
                         120 * 3600 * 24 => get_string('numdays', '', 120),
                         90 * 3600 * 24 => get_string('numdays', '', 90),
                         60 * 3600 * 24 => get_string('numdays', '', 60),
                         30 * 3600 * 24 => get_string('numdays', '', 30),
                         21 * 3600 * 24 => get_string('numdays', '', 21),
                         14 * 3600 * 24 => get_string('numdays', '', 14),
                         7 * 3600 * 24 => get_string('numdays', '', 7)];
        return $options;
    }

    /**
     * The self enrollment plugin has several bulk operations that can be performed.
     * @param course_enrolment_manager $manager
     * @return array
     */
    public function get_bulk_operations(course_enrolment_manager $manager) {
        global $CFG;
        require_once($CFG->dirroot . '/enrol/locallib.php');
        $context = $manager->get_context();
        $bulkoperations = [];
        if (has_capability("enrol/conditional:manage", $context)) {
            $bulkoperations['editselectedusers'] = new enrol_conditional_editselectedusers_operation($manager, $this);
        }
        if (has_capability("enrol/conditional:unenrol", $context)) {
            $bulkoperations['deleteselectedusers'] = new enrol_conditional_deleteselectedusers_operation($manager, $this);
        }
        return $bulkoperations;
    }

    /**
     * Add elements to the edit instance form.
     *
     * @param stdClass $instance
     * @param MoodleQuickForm $mform
     * @param context $context
     * @return bool
     */
    public function edit_instance_form($instance, MoodleQuickForm $mform, $context) {
        global $CFG, $DB;

        // Main fields.
        if (has_capability('enrol/conditional:config', $context)) {
            // Merge these two settings to one value for the single selection element.
            if ($instance->notifyall && $instance->expirynotify) {
                $instance->expirynotify = 2;
            }
            unset($instance->notifyall);

            $nameattribs = ['size' => '20', 'maxlength' => '255'];
            $mform->addElement('text', 'name', get_string('custominstancename', 'enrol'), $nameattribs);
            $mform->setType('name', PARAM_TEXT);
            $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'server');

            $options = $this->get_status_options();
            $mform->addElement('select', 'status', get_string('status', 'enrol_conditional'), $options);
            $mform->addHelpButton('status', 'status', 'enrol_conditional');

            $options = $this->get_newenrols_options();
            $mform->addElement('select', 'customint6', get_string('newenrols', 'enrol_conditional'), $options);
            $mform->addHelpButton('customint6', 'newenrols', 'enrol_conditional');
            $mform->disabledIf('customint6', 'status', 'eq', ENROL_INSTANCE_DISABLED);

            $passattribs = ['size' => '20', 'maxlength' => '50'];
            $mform->addElement('passwordunmask', 'password', get_string('password', 'enrol_conditional'), $passattribs);
            $mform->addHelpButton('password', 'password', 'enrol_conditional');
            if (empty($instance->id) && $this->get_config('requirepassword')) {
                $mform->addRule('password', get_string('required'), 'required', null, 'client');
            }
            $mform->addRule('password', get_string('maximumchars', '', 50), 'maxlength', 50, 'server');

            $options = $this->get_groupkey_options();
            $mform->addElement('select', 'customint1', get_string('groupkey', 'enrol_conditional'), $options);
            $mform->addHelpButton('customint1', 'groupkey', 'enrol_conditional');

            $roles = $this->extend_assignable_roles($context, $instance->roleid);
            $mform->addElement('select', 'roleid', get_string('role', 'enrol_conditional'), $roles);

            $options = ['optional' => true, 'defaultunit' => 86400];
            $mform->addElement('duration', 'enrolperiod', get_string('enrolperiod', 'enrol_conditional'), $options);
            $mform->addHelpButton('enrolperiod', 'enrolperiod', 'enrol_conditional');

            $options = $this->get_expirynotify_options();
            $mform->addElement('select', 'expirynotify', get_string('expirynotify', 'core_enrol'), $options);
            $mform->addHelpButton('expirynotify', 'expirynotify', 'core_enrol');

            $options = ['optional' => false, 'defaultunit' => 86400];
            $mform->addElement('duration', 'expirythreshold', get_string('expirythreshold', 'core_enrol'), $options);
            $mform->addHelpButton('expirythreshold', 'expirythreshold', 'core_enrol');
            $mform->disabledIf('expirythreshold', 'expirynotify', 'eq', 0);

            $options = ['optional' => true];
            $mform->addElement('date_time_selector', 'enrolstartdate', get_string('enrolstartdate', 'enrol_conditional'), $options);
            $mform->setDefault('enrolstartdate', 0);
            $mform->addHelpButton('enrolstartdate', 'enrolstartdate', 'enrol_conditional');

            $options = ['optional' => true];
            $mform->addElement('date_time_selector', 'enrolenddate', get_string('enrolenddate', 'enrol_conditional'), $options);
            $mform->setDefault('enrolenddate', 0);
            $mform->addHelpButton('enrolenddate', 'enrolenddate', 'enrol_conditional');

            $options = $this->get_longtimenosee_options();
            $mform->addElement('select', 'customint2', get_string('longtimenosee', 'enrol_conditional'), $options);
            $mform->addHelpButton('customint2', 'longtimenosee', 'enrol_conditional');

            $mform->addElement('text', 'customint3', get_string('maxenrolled', 'enrol_conditional'));
            $mform->addHelpButton('customint3', 'maxenrolled', 'enrol_conditional');
            $mform->setType('customint3', PARAM_INT);
        }

        // Conditions for self enrolment.
        if (has_capability('enrol/conditional:config', $context)) {
            \local_enrolconditions\output\tree_editor::add_to_form(
                $mform,
                self::FIELD_CONDITIONS,
                get_string('conditions', 'enrol_conditional'),
                $context,
                $instance->{self::FIELD_CONDITIONS} ?? null,
                $this->get_name(),
                (int) ($instance->id ?? 0),
            );
            $mform->addHelpButton(self::FIELD_CONDITIONS . '_editor', 'conditions', 'enrol_conditional');

            \local_enrolconditions\output\enforcement_form::add_to_form(
                $mform,
                self::get_condition_action($instance),
                self::get_reactivate($instance),
                self::get_check_modes($instance),
            );
        }

        // Course welcome message.
        if (has_any_capability(['enrol/conditional:config', 'moodle/course:editcoursewelcomemessage'], $context)) {
            $mform->addElement(
                'select',
                'customint4',
                get_string('sendcoursewelcomemessage', 'enrol_conditional'),
                enrol_send_welcome_email_options()
            );
            $mform->addHelpButton('customint4', 'sendcoursewelcomemessage', 'enrol_conditional');

            $options = [
                'cols' => '60',
                'rows' => '8',
            ];
            $mform->addElement('textarea', 'customtext1', get_string('customwelcomemessage', 'core_enrol'), $options);
            $mform->setDefault('customtext1', get_string('customwelcomemessageplaceholder', 'core_enrol'));
            $mform->hideIf(
                elementname: 'customtext1',
                dependenton: 'customint4',
                condition: 'eq',
                value: ENROL_DO_NOT_SEND_EMAIL,
            );

            // Static form elements cannot be hidden by hideIf() so we need to add a dummy group.
            // See: https://tracker.moodle.org/browse/MDL-66251.
            $group[] = $mform->createElement(
                'static',
                'customwelcomemessage_extra_help',
                null,
                get_string(
                    identifier: 'customwelcomemessage_help',
                    component: 'core_enrol',
                ),
            );
            $mform->addGroup($group, 'group_customwelcomemessage_extra_help', '', ' ', false);
            $mform->hideIf(
                elementname: 'group_customwelcomemessage_extra_help',
                dependenton: 'customint4',
                condition: 'eq',
                value: ENROL_DO_NOT_SEND_EMAIL,
            );
        }

        // Enrolment changes warning.
        if (has_capability('enrol/conditional:config', $context) && enrol_accessing_via_instance($instance)) {
            $warntext = get_string('instanceeditselfwarningtext', 'core_enrol');
            $mform->addElement('static', 'selfwarn', get_string('instanceeditselfwarning', 'core_enrol'), $warntext);
        }
    }

    /**
     * We are a good plugin and don't invent our own UI/validation code path.
     *
     * @return boolean
     */
    public function use_standard_editing_ui() {
        return true;
    }

    /**
     * Perform custom validation of the data used to edit the instance.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @param object $instance The instance loaded from the DB
     * @param context $context The context of the instance we are editing
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK.
     * @return void
     */
    public function edit_instance_validation($data, $files, $instance, $context) {
        $errors = [];

        $checkpassword = false;

        if ($instance->id) {
            // Check the password if we are enabling the plugin again.
            if (($instance->status == ENROL_INSTANCE_DISABLED) && ($data['status'] == ENROL_INSTANCE_ENABLED)) {
                $checkpassword = true;
            }

            // Check the password if the instance is enabled and the password has changed.
            if (($data['status'] == ENROL_INSTANCE_ENABLED) && ($instance->password !== $data['password'])) {
                $checkpassword = true;
            }

            // Check the password if we are enabling group enrolment keys.
            if (!$instance->customint1 && $data['customint1']) {
                $checkpassword = true;
            }
        } else {
            $checkpassword = true;
        }

        if ($checkpassword) {
            $require = $this->get_config('requirepassword');
            $policy = $this->get_config('usepasswordpolicy');
            if ($require && trim($data['password']) === '') {
                $errors['password'] = get_string('required');
            } else if (enrolment_key::is_key((string) $data['password'])) {
                if ($policy) {
                    $errmsg = '';
                    if (!\core\di::get(\core\authentication\password::class)->check_policy($data['password'], $errmsg)) {
                        $errors['password'] = $errmsg;
                    }
                }
                if (
                    $data['customint1']
                    && enrolment_key::find_group((int) $instance->courseid, (string) $data['password']) !== null
                ) {
                    $errors['password'] = get_string('passwordmatchesgroupkey', 'enrol_conditional');
                }
            }
        }

        if ($data['status'] == ENROL_INSTANCE_ENABLED) {
            if (!empty($data['enrolenddate']) && $data['enrolenddate'] < $data['enrolstartdate']) {
                $errors['enrolenddate'] = get_string('enrolenddaterror', 'enrol_conditional');
            }
        }

        // If expirynotify is selected, then ensure the threshold is at least one day.
        if (isset($data['expirynotify']) && $data['expirynotify'] > 0 && $data['expirythreshold'] < DAYSECS) {
            $errors['expirythreshold'] = get_string('errorthresholdlow', 'core_enrol');
        }

        // Now these ones are checked by quickforms, but we may be called by the upload enrolments tool, or a webservive.
        if (array_key_exists('name', $data) && core_text::strlen($data['name']) > 255) {
            $errors['name'] = get_string('err_maxlength', 'form', 255);
        }
        $validstatus = array_keys($this->get_status_options());
        $validnewenrols = array_keys($this->get_newenrols_options());
        if (core_text::strlen($data['password']) > 50) {
            $errors['name'] = get_string('err_maxlength', 'form', 50);
        }
        $validgroupkey = array_keys($this->get_groupkey_options());
        $context = context_course::instance($instance->courseid);
        $validroles = array_keys($this->extend_assignable_roles($context, $instance->roleid));
        $validexpirynotify = array_keys($this->get_expirynotify_options());
        $validlongtimenosee = array_keys($this->get_longtimenosee_options());
        $tovalidate = [
            'enrolstartdate' => PARAM_INT,
            'enrolenddate' => PARAM_INT,
            'name' => PARAM_TEXT,
            'customint1' => $validgroupkey,
            'customint2' => $validlongtimenosee,
            'customint3' => PARAM_INT,
            'customint4' => PARAM_INT,
            'customint6' => $validnewenrols,
            'conditionaction' => array_keys($this->get_condition_action_options()),
            'conditionreactivate' => [0, 1],
            'checkmodelogin' => [0, 1],
            'checkmodeperiodic' => [0, 1],
            'checkmodeevents' => [0, 1],
            'status' => $validstatus,
            'enrolperiod' => PARAM_INT,
            'expirynotify' => $validexpirynotify,
            'roleid' => $validroles,
        ];
        if (array_key_exists('expirynotify', $data) && $data['expirynotify'] != 0) {
            $tovalidate['expirythreshold'] = PARAM_INT;
        }
        $typeerrors = $this->validate_param_types($data, $tovalidate);
        $errors = array_merge($errors, $typeerrors);

        if (array_key_exists(self::FIELD_CONDITIONS, $data)) {
            $message = $this->validate_conditions((string) $data[self::FIELD_CONDITIONS], $instance, $context);
            if ($message !== null) {
                $errors[self::FIELD_CONDITIONS . '_editor'] = $message;
            }
        }

        return $errors;
    }

    /**
     * Validates the JSON of a condition tree.
     *
     * Types already used by the saved tree are accepted even when they were disabled since.
     *
     * With a context, a condition that is not stored unchanged in the instance must also pass the reach check of
     * its type (the user saving may reference what it names, {@see \local_enrolconditions\tree::validate()}).
     *
     * @param string $json Submitted tree.
     * @param stdClass|null $instance Instance being edited, if it exists.
     * @param \context|null $context Course context of the instance; null skips the reach check.
     * @return string|null Error message, or null when valid.
     */
    public function validate_conditions(string $json, ?stdClass $instance = null, ?\context $context = null): ?string {
        try {
            $tree = \local_enrolconditions\tree::from_json($json);
        } catch (\local_enrolconditions\invalid_tree_exception $e) {
            return $e->getMessage();
        }
        $allowed = [];
        $previous = null;
        if (!empty($instance->{self::FIELD_CONDITIONS})) {
            try {
                $previous = \local_enrolconditions\tree::from_json($instance->{self::FIELD_CONDITIONS});
                $allowed = $previous->get_types();
            } catch (\local_enrolconditions\invalid_tree_exception $e) {
                $previous = null;
            }
        }
        $errors = $tree->validate($allowed, $context, $previous);
        return $errors ? implode(' ', $errors) : null;
    }

    /**
     * Normalised JSON of a condition tree, for storage.
     *
     * @param string|null $json
     * @return string|null Null when there are no conditions.
     * @throws \local_enrolconditions\invalid_tree_exception When the structure is invalid.
     */
    protected function normalise_conditions(?string $json): ?string {
        return \local_enrolconditions\tree::from_json($json)->to_json();
    }

    /**
     * Value of FIELD_ACTION, with the site defaults when the instance has none yet.
     *
     * @param stdClass|null $instance
     * @return int
     */
    protected static function get_action_field(?stdClass $instance): int {
        if ($instance !== null && isset($instance->{self::FIELD_ACTION})) {
            return (int) $instance->{self::FIELD_ACTION};
        }
        return self::encode_action(
            (int) get_config('enrol_conditional', 'conditionaction'),
            (bool) get_config('enrol_conditional', 'conditionreactivate')
        );
    }

    /**
     * Action applied when the conditions of an enrolment stop being met.
     *
     * @param stdClass|null $instance
     * @return int One of the ACTION_ constants.
     */
    public static function get_condition_action(?stdClass $instance): int {
        $action = self::get_action_field($instance) & self::ACTION_MASK;
        return in_array($action, [self::ACTION_NONE, self::ACTION_SUSPEND, self::ACTION_REMOVE], true)
            ? $action : self::ACTION_SUSPEND;
    }

    /**
     * Whether enrolments suspended by the plugin are reactivated when the conditions are met again.
     *
     * @param stdClass|null $instance
     * @return bool
     */
    public static function get_reactivate(?stdClass $instance): bool {
        return (bool) (self::get_action_field($instance) & self::FLAG_REACTIVATE);
    }

    /**
     * Value stored in FIELD_ACTION.
     *
     * @param int $action One of the ACTION_ constants.
     * @param bool $reactivate
     * @return int
     */
    public static function encode_action(int $action, bool $reactivate): int {
        return ($action & self::ACTION_MASK) | ($reactivate ? self::FLAG_REACTIVATE : 0);
    }

    /**
     * Options of the action when the conditions stop being met.
     *
     * @return array
     */
    public function get_condition_action_options(): array {
        return \local_enrolconditions\output\enforcement_form::get_action_options();
    }

    /**
     * Site default of FIELD_CHECKMODES.
     *
     * @return int
     */
    public static function get_default_check_modes(): int {
        $modes = self::CHECK_ENROL;
        foreach (self::CHECK_MODE_FIELDS as $name => $bit) {
            if (get_config('enrol_conditional', $name)) {
                $modes |= $bit;
            }
        }
        return $modes;
    }

    /**
     * When the conditions of the instance are checked.
     *
     * Without an instance, or for fields that do not name the column yet (a new instance), the site defaults
     * apply. A stored NULL means CHECK_ENROL only: that is what the upgrade gave the instances created before
     * the column was used, and what {@see get_enforcement_mode_sql()} reads.
     *
     * @param stdClass|null $instance Instance record, or the fields of a new one.
     * @return int Bitmask of CHECK_ constants; CHECK_ENROL is always set.
     */
    public static function get_check_modes(?stdClass $instance): int {
        if ($instance === null || !property_exists($instance, self::FIELD_CHECKMODES)) {
            return self::get_default_check_modes();
        }
        return ((int) $instance->{self::FIELD_CHECKMODES}) | self::CHECK_ENROL;
    }

    /**
     * Moves the form fields checkmode* into FIELD_CHECKMODES.
     *
     * @param array $fields Instance fields, changed in place.
     * @param stdClass|null $instance Current instance, for the values not sent.
     */
    protected function merge_check_mode_fields(array &$fields, ?stdClass $instance): void {
        $sent = array_intersect_key($fields, self::CHECK_MODE_FIELDS);
        if (!$sent) {
            return;
        }
        $submitted = \local_enrolconditions\output\enforcement_form::get_submitted(
            $sent,
            self::get_condition_action($instance),
            self::get_reactivate($instance),
            self::get_check_modes($instance)
        );
        $fields = array_diff_key($fields, self::CHECK_MODE_FIELDS);
        $fields[self::FIELD_CHECKMODES] = $submitted['modes'];
    }

    /**
     * Forgets the cached map of observed events, after an instance changed.
     */
    protected function purge_event_map(): void {
        \local_enrolconditions\local\enforcer::purge_event_map();
    }

    /**
     * Moves the form fields conditionaction and conditionreactivate into FIELD_ACTION.
     *
     * @param array $fields Instance fields, changed in place.
     * @param stdClass|null $instance Current instance, for the values not sent.
     */
    protected function merge_action_fields(array &$fields, ?stdClass $instance): void {
        if (!array_key_exists('conditionaction', $fields) && !array_key_exists('conditionreactivate', $fields)) {
            return;
        }
        $action = array_key_exists('conditionaction', $fields)
            ? (int) $fields['conditionaction'] : self::get_condition_action($instance);
        $reactivate = array_key_exists('conditionreactivate', $fields)
            ? (bool) $fields['conditionreactivate'] : self::get_reactivate($instance);
        $fields[self::FIELD_ACTION] = self::encode_action($action, $reactivate);
        unset($fields['conditionaction'], $fields['conditionreactivate']);
    }

    /**
     * Add new instance of enrol plugin.
     *
     * @param stdClass $course
     * @param array|null $fields instance fields
     * @return int id of new instance, null if can not be created
     * @throws \local_enrolconditions\invalid_tree_exception When the condition tree does not validate.
     */
    public function add_instance($course, ?array $fields = null) {
        if (!empty($fields) && array_key_exists(self::FIELD_CONDITIONS, $fields)) {
            $this->require_valid_conditions((string) $fields[self::FIELD_CONDITIONS], null, (int) $course->id);
        }
        return $this->insert_instance($course, $fields);
    }

    /**
     * Stores a new instance, its condition tree checked for structure only.
     *
     * @param stdClass $course
     * @param array|null $fields Instance fields.
     * @return int Id of the new instance.
     * @throws \local_enrolconditions\invalid_tree_exception When the structure of the tree is invalid.
     */
    protected function insert_instance($course, ?array $fields): int {
        // In the form we are representing 2 db columns with one field.
        if (!empty($fields) && !empty($fields['expirynotify'])) {
            if ($fields['expirynotify'] == 2) {
                $fields['expirynotify'] = 1;
                $fields['notifyall'] = 1;
            } else {
                $fields['notifyall'] = 0;
            }
        }
        if (!empty($fields) && array_key_exists(self::FIELD_CONDITIONS, $fields)) {
            $fields[self::FIELD_CONDITIONS] = $this->normalise_conditions($fields[self::FIELD_CONDITIONS]);
        }
        $fields = $fields ?? [];
        $this->merge_action_fields($fields, null);
        if (!isset($fields[self::FIELD_ACTION])) {
            $fields[self::FIELD_ACTION] = self::get_action_field(null);
        }
        $this->merge_check_mode_fields($fields, null);
        $fields[self::FIELD_CHECKMODES] = self::get_check_modes((object) $fields);

        $id = (int) parent::add_instance($course, $fields);
        $this->purge_event_map();
        return $id;
    }

    /**
     * Refuses a condition tree that the edit form would refuse.
     *
     * @param string $json Tree to store.
     * @param stdClass|null $instance Instance being changed, whose saved types and conditions stay accepted.
     * @param int $courseid Course of the instance, whose context the reach check uses.
     * @return void
     * @throws \local_enrolconditions\invalid_tree_exception With the validation errors.
     */
    protected function require_valid_conditions(string $json, ?stdClass $instance, int $courseid): void {
        $message = $this->validate_conditions($json, $instance, \context_course::instance($courseid));
        if ($message !== null) {
            throw new \local_enrolconditions\invalid_tree_exception($message);
        }
    }

    /**
     * Whether a submitted tree is the one already stored, compared in normalised form.
     *
     * @param string|null $json Submitted tree.
     * @param stdClass $instance Stored instance.
     * @return bool
     */
    protected function is_stored_tree(?string $json, stdClass $instance): bool {
        $stored = $instance->{self::FIELD_CONDITIONS} ?? null;
        try {
            return $this->normalise_conditions($json) === $this->normalise_conditions($stored);
        } catch (\local_enrolconditions\invalid_tree_exception $e) {
            return (string) $json === (string) $stored;
        }
    }

    /**
     * Update instance of enrol plugin.
     *
     * @param stdClass $instance
     * @param stdClass $data modified instance fields
     * @return boolean
     * @throws \local_enrolconditions\invalid_tree_exception When a changed condition tree does not validate.
     */
    public function update_instance($instance, $data) {
        // In the form we are representing 2 db columns with one field.
        if ($data->expirynotify == 2) {
            $data->expirynotify = 1;
            $data->notifyall = 1;
        } else {
            $data->notifyall = 0;
        }
        // Keep previous/default value of disabled expirythreshold option.
        if (!$data->expirynotify) {
            $data->expirythreshold = $instance->expirythreshold;
        }
        // Add previous value of newenrols if disabled.
        if (!isset($data->customint6)) {
            $data->customint6 = $instance->customint6;
        }
        // Keep the conditions when the form did not send them (user without enrol/conditional:config).
        if (!property_exists($data, self::FIELD_CONDITIONS)) {
            $data->{self::FIELD_CONDITIONS} = $instance->{self::FIELD_CONDITIONS};
        } else if ($this->is_stored_tree($data->{self::FIELD_CONDITIONS}, $instance)) {
            // Unchanged, so not validated again: the course upload sends the stored tree back with every
            // update, and a tree that stopped validating must not block the other fields.
            $data->{self::FIELD_CONDITIONS} = $instance->{self::FIELD_CONDITIONS};
        } else {
            $this->require_valid_conditions((string) $data->{self::FIELD_CONDITIONS}, $instance, (int) $instance->courseid);
            // Core skips null values, so an empty tree is stored as an empty string.
            $data->{self::FIELD_CONDITIONS} = $this->normalise_conditions($data->{self::FIELD_CONDITIONS}) ?? '';
        }
        $fields = (array) $data;
        $this->merge_action_fields($fields, $instance);
        $this->merge_check_mode_fields($fields, $instance);
        $data = (object) $fields;

        $result = parent::update_instance($instance, $data);
        $this->purge_event_map();
        return $result;
    }

    /**
     * Enables or disables the instance.
     *
     * @param stdClass $instance
     * @param int $newstatus ENROL_INSTANCE_ENABLED or ENROL_INSTANCE_DISABLED.
     * @return void
     */
    public function update_status($instance, $newstatus) {
        parent::update_status($instance, $newstatus);
        $this->purge_event_map();
    }

    /**
     * Gets a list of roles that this user can assign for the course as the default for self-enrolment.
     *
     * @param context $context the context.
     * @param integer $defaultrole the id of the role that is set as the default for self-enrolment
     * @return array index is the role id, value is the role name
     */
    public function extend_assignable_roles($context, $defaultrole) {
        global $DB;

        $roles = get_assignable_roles($context, ROLENAME_BOTH);
        if (!isset($roles[$defaultrole])) {
            if ($role = $DB->get_record('role', ['id' => $defaultrole])) {
                $roles[$defaultrole] = role_get_name($role, $context, ROLENAME_BOTH);
            }
        }
        return $roles;
    }

    /**
     * Check if enrolment plugin is supported in csv course upload.
     *
     * @return bool
     */
    public function is_csv_upload_supported(): bool {
        return true;
    }

    /**
     * Finds matching instances for a given course.
     *
     * @param array $enrolmentdata enrolment data.
     * @param int $courseid Course ID.
     * @return stdClass|null Matching instance
     */
    public function find_instance(array $enrolmentdata, int $courseid): ?stdClass {

        $instances = enrol_get_instances($courseid, false);
        $instance = null;
        foreach ($instances as $i) {
            if ($i->enrol == 'conditional') {
                // This is bad - we can not really distinguish between self instances. So grab first available.
                $instance = $i;
                break;
            }
        }
        return $instance;
    }

    /**
     * Fill custom fields data for a given enrolment plugin.
     *
     * @param array $enrolmentdata enrolment data.
     * @param int $courseid Course ID.
     * @return array Updated enrolment data with custom fields info.
     */
    public function fill_enrol_custom_fields(array $enrolmentdata, int $courseid): array {
        return $enrolmentdata + ['password' => ''];
    }

    /**
     * Updates enrol plugin instance with provided data.
     * @param int $courseid Course ID.
     * @param array $enrolmentdata enrolment data.
     * @param stdClass $instance Instance to update.
     *
     * @return stdClass updated instance
     */
    public function update_enrol_plugin_data(int $courseid, array $enrolmentdata, stdClass $instance): stdClass {
        if (enrolment_key::is_key((string) ($enrolmentdata['password'] ?? ''))) {
            $instance->password = $enrolmentdata['password'];
        }
        return parent::update_enrol_plugin_data($courseid, $enrolmentdata, $instance);
    }

    /**
     * Check if data is valid for a given enrolment plugin
     *
     * @param array $enrolmentdata enrolment data to validate.
     * @param int|null $courseid Course ID.
     * @return array Errors
     */
    public function validate_enrol_plugin_data(array $enrolmentdata, ?int $courseid = null): array {
        global $DB;

        // If password is omitted or empty in csv it will be generated automatically if it is a required policy.

        $errors = parent::validate_enrol_plugin_data($enrolmentdata, $courseid);
        $policy = $this->get_config('usepasswordpolicy');
        if (enrolment_key::is_key((string) ($enrolmentdata['password'] ?? ''))) {
            // The column holds 50 characters, the bound the instance form puts on the key.
            if (core_text::strlen((string) $enrolmentdata['password']) > 50) {
                $errors['errorpasswordtoolong'] = new lang_string('err_maxlength', 'form', 50);
            }
            if ($policy) {
                $errarray = \core\di::get(\core\authentication\password::class)->get_policy_errors($enrolmentdata['password']);
                foreach ($errarray as $i => $err) {
                    $errors['enrol_conditional' . $i] = $err;
                }
            }

            if ($courseid) {
                // This is bad - no way to identify which instance it is.
                // So if any instance in course uses group key we should error.
                $usegroupenrolmentkeys =
                    $DB->count_records('enrol', ['courseid' => $courseid, 'enrol' => 'conditional', 'customint1' => 1]);
                if ($usegroupenrolmentkeys && enrolment_key::find_group($courseid, (string) $enrolmentdata['password']) !== null) {
                    $errors['errorpasswordmatchesgroupkey'] =
                        new lang_string('passwordmatchesgroupkey', 'enrol_conditional', $enrolmentdata['password']);
                }
            }
        }
        return $errors;
    }
}

/**
 * Get icon mapping for font-awesome.
 */
function enrol_conditional_get_fontawesome_icon_map() {
    return [
        'enrol_conditional:withkey' => 'fa-key',
        'enrol_conditional:withoutkey' => 'fa-solid fa-right-to-bracket',
    ];
}

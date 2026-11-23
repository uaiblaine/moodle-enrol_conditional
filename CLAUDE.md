# Claude instructions for `enrol_conditional`

This file is auto-loaded as context whenever Claude works in this plugin's
directory tree. **Fleet-wide standards live in `~/dev/CLAUDE.md`** (coding
style, CI gates, lang-string rules, the `mdl` environment, git rules) — do not
repeat them here. This file keeps only what is true for this plugin, as rules
with a one-line reason each.

Plugin context: a Moodle **enrol** plugin ("Conditional self enrolment") copied from core's `enrol_self` where
self enrolment depends on pluggable conditions (AND, OR, NOT) provided by `local_enrolconditions`. The conditions
can also be re-checked at login, periodically or on events, suspending or removing enrolments that no longer meet
them. It owns no table (the suspension records live in the engine's `local_enrolconditions_state`) and depends on
`local_enrolconditions` (`$plugin->dependencies`). Supports Moodle **5.3 only** (`$plugin->requires = 2026100200`,
`$plugin->supported = [503, 503]`). CI is the moodle-an-hochschulen reusable workflow, one job (`ci-503`) in
`.github/workflows/ci.yml`, which installs the engine from `main` — **update it when `supported` changes**.
Development happens on the m503 stack; this repo is mounted into m503 and m503b through `auto`, and into the mini
cluster, at `enrol/conditional` (see `~/dev/moodle-dev/plugins.conf`).

## Agent orchestration budget (fleet rule, repeated here on purpose)

Section 6 of `~/dev/CLAUDE.md` (`moodle-dev/CLAUDE.fleet.md`) is the authority and says why.
This short copy reaches sessions that do not load that file: cloud sessions and checkouts
outside `~/dev`. Every subagent gets the model and effort of its role from its agent
definition, and none runs on the session model.

| Role | model | effort | agent |
|---|---|---|---|
| Mechanical sweeps, greps, renames, counts, log reading | `haiku` | `medium` | `fleet-sweeper` |
| Checklists against evidence (handoff counts, spec lines against a sweep log, lang lockstep) | `haiku` | `high` | `fleet-checker` |
| Readers, measurers, graders | `sonnet` | `medium` | `fleet-reader` |
| Refuters and verifiers of a blocking finding | `sonnet` | `high` | `fleet-verifier` |
| Well-scoped implementation (established cause, settled design, written recipe) | `sonnet` | `medium` | `fleet-fixer` |
| Non-trivial implementation (open design, several files, long tasks) | `opus` | `high` | `fleet-implementer` |
| Consolidators, critics, estimators, ADR and documentation drafters | `opus` | `high` | `fleet-synthesist` |

- Launch the `Agent` tool with `subagent_type: "fleet-*"`; it has no `effort` parameter, so
  the role's effort comes from that definition (`mdl claude-setup` installs them). Where they
  are not installed, pass `model`. Aliases only; never `fable`; `xhigh` only for a long-horizon
  implementer whose prompt says why; never `xhigh`/`max` on Sonnet or Haiku.
- No long command inside a subagent: `mdl ci --matrix`, `mdl mutate` and Behat run from the
  main session in a background Bash command; a subagent runs the fast gate its prompt names and
  reports the command with its counts.
- Workflows only on the user's opt-in, every `agent()` with `agentType: 'fleet-*'`, under 10
  agents. Advisor off by default.

## Commands

```sh
mdl ci moodle-enrol_conditional                      # one leg locally before any push
mdl phpunit m503 enrol_conditional                   # targeted tests
mdl behat m503 @enrol_conditional                    # Behat smoke tests (key holder, welcome message, conditions)
mdl grunt m503 enrol/conditional                     # rebuild amd/build (commit with src)
mdl purge m503                                       # after PHP changes that affect output
php enrol/conditional/cli/sync.php -v                # run the enrolment sync once
```

In a Claude Code cloud session none of `mdl`, the stacks or `~/dev/CLAUDE.md` exist. The cloud
environment's setup script (`moodle-dev/cloud/setup.sh`) installs `moodle-plugin-ci` and the `mpci`
runner instead: `mpci` runs every gate on the highest branch in `ci.yml`, `mpci --branch <branch>`
another one, `mpci --reuse --only phpunit --filter <test>` iterates. It cannot run Behat, PHP 8.4 or
MariaDB, so the owner runs the gate the change's tier asks (fleet `CLAUDE.md` section 4; the
matrix, `mdl ci moodle-enrol_conditional --matrix --behat`, for a tier-3 change) before merging: push a branch,
open the pull request, leave the merge to them. If `which mpci` prints nothing, the session was resumed on
a container that skipped the setup script: attach `moodle-dev` and run `bash cloud/setup.sh` from it.
See `moodle-dev/cloud/README.md`.

## Code layout

```
lib.php                 enrol_conditional_plugin (implements the engine's enforceable)
classes/local/          enrolment_key (key and group-key logic shared by the form, the web service and the enrolment)
classes/external/       get_instance_info, enrol_user (the web services)
classes/form/           enrol form shown to the user; classes/*operation.php, *_form.php bulk edit/delete
classes/task/           sync_enrolments, send_expiry_notifications
classes/event/          conditions_not_met, user_enrolment_suspended / _reactivated
backup/moodle2/         backup and restore of the instance, its condition tree and the open state rows
amd/src/enrol_page.js   enrolment page script
docs/                   upstream's documentation (en, pt_BR), export-ignored
```

## Fork rules

- **This is a fork.** `origin` is `uaiblaine`, the remote `upstream` is `moodle-by-kelsoncm`. Upstream's
  copyright lines and `@author` tags stay as they are, because they are its authors' attribution; only
  new files carry `@copyright 2026 Anderson Blaine`. Reason: the licence and the attribution travel with the code.
- **Upstream's date-based version numbers are kept, never renumbered into the fleet's `20261005XX`
  namespace.** Reason: the sites already run them, and an upstream release would otherwise conflict or
  look older than what is installed. Bump the next number of upstream's own sequence.
- **The two plugins are developed together.** `local_enrolconditions` (the engine) and `enrol_conditional`
  (the method) change in step: the method implements the engine's `\local_enrolconditions\enforceable`
  interface, so an interface change needs both repositories, and the method's `version.php` names the
  engine version it needs. Reason: the dependency is by version number, and a stale pair fails at install.
- **The audit of 2026-11-23** is in `~/dev/moodle-dev/data/audits/enrol_conditional-local_enrolconditions-2026-11-23.md`
  (not versioned). Read it before touching enforcement, backup/restore or the SQL list condition.
- `AGENTS.md` is upstream's file and only points here.

## Architecture gotchas

- **Conditions apply only to enrolments made by this method**, including its own web service. Manual, cohort and
  other methods are out of scope; the engine serves any other method that implements `enforceable`.
- **Only suspensions recorded in `local_enrolconditions_state` are ever reactivated.** Reason: an enrolment someone
  else suspended must stay suspended. The table belongs to the engine; the old `enrol_conditional_state` name no
  longer exists.
- **The condition tree is stored as JSON in the instance's `customtext2`.** It is validated (`validate_config()` and
  the reach check `check_access()`, with the course context and the tree stored before) on the instance form, in
  `add_instance()` and in `update_instance()` when the tree changes. Restore stores it as it comes and fails closed:
  an invalid tree is "unknown" and nobody enrols. Pinned by `tests/conditions_writers_test.php`.
- **Listing paths (`get_info_icons()`, `show_enrolme_link()`) never evaluate conditions.** Reason: core calls them
  once per course box, so a condition would cost queries per course on every listing.
- **`enrol_conditional()` checks everything itself** (guest, instance, capability, conditions, key, capacity under a
  core lock). Reason: three doors call it (the enrol page, the key form, the web service) and none may be trusted
  to have checked.
- **Restore brings back suspension state only for enrolments the restore created, and only from the same site.**
  Reason: a merge into an existing course must never hand the engine an enrolment a teacher suspended by hand.
- **The bulk operations skip enrolments of other methods.** Reason: a selected user may be enrolled by several
  methods and this one owns only its own.
- **`lang/pt_br`** exists, in lockstep with `lang/en`, `export-ignore`d; in Portuguese "inscrição", never "matrícula".
- **Backup carries the open state rows** when users are included, and the files of `enrolcond_userfile`; restore maps
  users back. Reason: without the rows a restored site could never reactivate what the source site suspended.
- **Groups added by the method are added without a component** (`groups_add_member($id, $userid)`), so core's group
  provider covers them. If that ever passes `enrol_conditional`, the privacy provider must link `core_group`.

## Testing notes

PHPUnit covers the method (`lib_test`, `enforcer_test`, `check_modes_test`, backup/restore, bulk operations, web
service); Behat is smoke only (`tests/behat/*.feature`, steps in `behat_enrol_conditional.php`). The engine's tests live in
`local_enrolconditions`; run both after an interface change.

## When in doubt

Follow the patterns in existing files. The codebase is internally
consistent — if a new file feels like it matches no existing shape,
re-examine the approach.

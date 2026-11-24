# Mutation spec

Every guard this plugin's promises rest on is paired with the test that must go red when the guard is
removed. Run the set with:

```sh
mdl mutate moodle-enrol_conditional moodle-enrol_conditional/mutations/gates.conf --fast --dry-run --branch MOODLE_503_STABLE   # patterns only, seconds
mdl mutate moodle-enrol_conditional moodle-enrol_conditional/mutations/gates.conf --fast --jobs 4                               # the sweep
mdl mutate moodle-enrol_conditional moodle-enrol_conditional/mutations/gates.conf --fast --changed origin/main                  # this change's gates
mdl mutate moodle-enrol_conditional moodle-enrol_conditional/mutations/gates.conf --fast --only A7_key_not_checked
```

Sweep both database families (`--db pgsql`, `--db mariadb`). `mdl mutate` lives in
`~/dev/moodle-dev/bin/mdl-mutate`; its header documents the format.

## Reading it

- Each gate is `tag | file | perl script`; a `#   ->` line under it names a test that must be among the
  gate's reds. A gate with no `->` line is a guard that no test holds as far as the audit could read: the
  sweep shows what, if anything, goes red, and an empty result is a finding to fix by writing the test.
- A to P group the gates by promise: A the checks of `enrol_conditional()` itself, B capacity under the lock,
  C the enrolment key, D listings never evaluate conditions, E the cheap checks, F and G the web services, H tree
  validation where it is stored, R restore, S bulk operations, K check modes, N the editor, L capability hooks,
  M and P the key form and the enrol button.
- `F4` cannot be seen through `call_external_function()`, which cleans the answer to the declared structure, so extra
  keys never reach a client whatever `execute()` returns. Its test calls `execute()` directly.

## Rules for adding one

- Add the gate in the same change as the guard, and name the test that must redden. If you cannot, the guard is
  unheld and that is the finding.
- Perl interpolates `$variables` on both sides of `s///`: write `\$` in patterns and replacements, and delimit
  with `#`, never with a character the pattern needs literally (`|`, `{`, `}`).
- Anchor on what makes the line unique, and end each script with `or die` so a pattern that stops matching
  fails the dry run instead of passing silently.
- Mutate the value, not the guard around it, and make the fixture able to see the difference (the null check
  modes need a site default that differs from what NULL means).

This directory is not shipped (`.gitattributes` marks it `export-ignore`) and CI does not run it.

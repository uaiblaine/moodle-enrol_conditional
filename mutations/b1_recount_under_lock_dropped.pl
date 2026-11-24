# B1: the count under the lock is trusted away: a full instance takes one more.
s#\n            if \(\$DB->count_records\('user_enrolments', \['enrolid' => \$instance->id\]\) >= \(int\) \$instance->customint3\) \{\n                throw new moodle_exception\('maxenrolledreached', 'enrol_conditional'\);\n            \}\n#\n# or die "b1: pattern did not match\n";

# A7: enrol_conditional() skips the enrolment key check.
s#\n        if \(enrolment_key::check\(\$instance, \$key\) !== enrolment_key::ACCEPTED\) \{\n            throw new moodle_exception\('passwordinvalid', 'enrol_conditional'\);\n        \}\n#\n# or die "a7: pattern did not match\n";

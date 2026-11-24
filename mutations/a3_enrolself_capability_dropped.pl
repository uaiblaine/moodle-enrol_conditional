# A3: enrol_conditional() skips the enrol/conditional:enrolself capability check.
s#\n        if \(!has_capability\('enrol/conditional:enrolself', context_course::instance\(\$instance->courseid\)\)\) \{\n            throw new moodle_exception\('canntenrol', 'enrol_conditional'\);\n        \}\n#\n# or die "a3: pattern did not match\n";

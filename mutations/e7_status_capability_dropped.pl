# E7: get_self_enrol_status() skips the enrol/conditional:enrolself capability check.
s#\n        if \(!has_capability\('enrol/conditional:enrolself', context_course::instance\(\$instance->courseid\)\)\) \{\n            return get_string\('canntenrol', 'enrol_conditional'\);\n        \}\n#\n# or die "e7: pattern did not match\n";

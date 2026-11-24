# E5: a user already enrolled in the instance is offered it again.
s#\n        if \(\$DB->record_exists\('user_enrolments', \['userid' => \$USER->id, 'enrolid' => \$instance->id\]\)\) \{\n            return get_string\('canntenrol', 'enrol_conditional'\);\n        \}\n#\n# or die "e5: pattern did not match\n";

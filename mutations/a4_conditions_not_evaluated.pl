# A4: enrol_conditional() evaluates the conditions but never refuses on them.
s#if \(\$result !== null && !\$result->is_met\(\)\) \{\n            throw new moodle_exception\('conditionsnotmetshort'#if (false) {\n            throw new moodle_exception('conditionsnotmetshort'# or die "a4: pattern did not match\n";

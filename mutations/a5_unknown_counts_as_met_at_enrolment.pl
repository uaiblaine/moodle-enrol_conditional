# A5: enrol_conditional() refuses only a result that is not met for sure, so an unknown one enrols.
s#if \(\$result !== null && !\$result->is_met\(\)\) \{\n            throw new moodle_exception\('conditionsnotmetshort'#if (\$result !== null && \$result->is_not_met()) {\n            throw new moodle_exception('conditionsnotmetshort'# or die "a5: pattern did not match\n";

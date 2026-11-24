# A13: the enrol page decides "needs a key" by truthiness of the column.
s#if \(enrolment_key::is_required\(\$instance\)\) \{\n                // Self-enrolment with password#if (\$instance->password) {\n                // Self-enrolment with password# or die "a13: pattern did not match\n";

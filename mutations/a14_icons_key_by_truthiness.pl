# A14: the listing icons decide "needs a key" by truthiness of the column.
s#if \(enrolment_key::is_required\(\$instance\) \|\| \$instance->customint1\) \{#if (\$instance->password || \$instance->customint1) {# or die "a14: pattern did not match\n";

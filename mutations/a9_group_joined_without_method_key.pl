# A9: a group key is looked up even when the instance has no key of its own.
s#if \(enrolment_key::is_required\(\$instance\) && !empty\(\$instance->customint1\)\) \{#if (!empty(\$instance->customint1)) {# or die "a9: pattern did not match\n";

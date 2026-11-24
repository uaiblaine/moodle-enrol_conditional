# A15: the app information decides "needs a key" by truthiness of the column.
s#if \(enrolment_key::is_required\(\$instance\)\) \{\n            \$instanceinfo->requiredparam#if (\$instance->password) {\n            \$instanceinfo->requiredparam# or die "a15: pattern did not match\n";

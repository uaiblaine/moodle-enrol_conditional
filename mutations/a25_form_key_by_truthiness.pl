# A25: the instance form checks the policy and the group-key collision only for a truthy key.
s#\} else if \(enrolment_key::is_key\(\(string\) \$data\['password'\]\)\) \{#} else if (!empty(\$data['password'])) {# or die "a25: pattern did not match\n";

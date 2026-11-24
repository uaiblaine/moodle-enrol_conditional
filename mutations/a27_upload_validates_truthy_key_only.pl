# A27: the course upload applies the policy and the length bound only to a truthy key.
s#if \(enrolment_key::is_key\(\(string\) \(\$enrolmentdata\['password'\] \?\? ''\)\)\) \{\n            // The column holds#if (!empty(\$enrolmentdata['password'])) {\n            // The column holds# or die "a27: pattern did not match\n";

# A26: the course upload stores a key only when it is truthy.
s#if \(enrolment_key::is_key\(\(string\) \(\$enrolmentdata\['password'\] \?\? ''\)\)\) \{\n            \$instance->password#if (!empty(\$enrolmentdata['password'])) {\n            \$instance->password# or die "a26: pattern did not match\n";

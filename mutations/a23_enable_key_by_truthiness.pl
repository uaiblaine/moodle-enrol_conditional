# A23: enabling an instance asks whether a key is set by truthiness, so a key of '0' counts as none.
s#if \(!enrolment_key::is_required\(\$instance\)\) \{\n                    return false;#if (empty(\$instance->password)) {\n                    return false;# or die "a23: pattern did not match\n";

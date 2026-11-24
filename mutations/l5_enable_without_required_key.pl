# L5: a disabled instance without a key can be enabled although the site requires a key.
s#if \(!enrolment_key::is_required\(\$instance\)\) \{\n                    return false;#if (false) {\n                    return false;# or die "l5: pattern did not match\n";

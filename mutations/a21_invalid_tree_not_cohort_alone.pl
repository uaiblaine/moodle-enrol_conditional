# A21: a stored tree that does not parse is no longer replaced by the cohort condition alone: the rule throws.
s#\$root = \[\];#throw \$e;# or die "a21: pattern did not match\n";

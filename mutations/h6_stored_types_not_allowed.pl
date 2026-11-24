# H6: the types the stored tree already uses are no longer accepted when they were disabled since.
s#\$allowed = \$previous->get_types\(\);#\$allowed = [];# or die "h6: pattern did not match\n";

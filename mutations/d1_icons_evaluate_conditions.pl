# D1: the listing icons evaluate the conditions, once per course box of a listing.
s#(get_self_enrol_status\(\$instance, false, )false(\) !== true\) \{\n                continue;)#${1}true$2# or die "d1: pattern did not match\n";

# D4: get_self_enrol_status() evaluates the conditions whatever its caller asked.
s#if \(\$checkconditions && isloggedin\(\) && !isguestuser\(\)\) \{#if (isloggedin() && !isguestuser()) {# or die "d4: pattern did not match\n";

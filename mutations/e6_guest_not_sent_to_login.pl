# E6: the enrol page no longer refuses a guest with a login button.
s#if \(\$checkguest && isguestuser\(\)\) \{#if (false) {# or die "e6: pattern did not match\n";

# S5: the participants page offers the bulk edit without enrol/conditional:manage.
s#if \(has_capability\("enrol/conditional:manage", \$context\)\) \{#if (true) {# or die "s5: pattern did not match\n";

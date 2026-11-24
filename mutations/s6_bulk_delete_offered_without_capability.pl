# S6: the participants page offers the bulk delete without enrol/conditional:unenrol.
s#if \(has_capability\("enrol/conditional:unenrol", \$context\)\) \{#if (true) {# or die "s6: pattern did not match\n";

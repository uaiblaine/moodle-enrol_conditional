# B9: the capacity lock is released before the enrolment is written (the count is no longer protected).
s#(capacity_' \. \$instance->id, 10\);\n        if \(!\$lock\) \{\n[^\n]*\n        \}\n)        try \{#$1        \$lock->release();\n        try {# or die "b9: pattern did not match\n";

# A30: the lock of the user is released before the read and the write it protects.
s#('enrol_' \. \$instance->id \. '_' \. \$userid, 10\);\n        if \(!\$lock\) \{\n[^\n]*\n        \}\n)        try \{#$1        \$lock->release();\n        try {# or die "a30: pattern did not match\n";

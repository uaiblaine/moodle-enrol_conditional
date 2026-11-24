# B2: a lock that is not granted no longer refuses; the enrolment goes on without it.
s#(capacity_' \. \$instance->id, 10\);\n        )if \(!\$lock\) \{#$1if (false) {# or die "b2a: pattern did not match\n";
s#\$lock->release\(\);(\n        \}\n    \}\n\n[^\n]*Override[^\n]*\n    public function enrol_page_hook)#if (\$lock) {\n                \$lock->release();\n            }$1# or die "b2b: pattern did not match\n";

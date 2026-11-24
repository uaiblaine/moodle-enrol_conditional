# E2: the enrolment start date is ignored.
s#\n        if \(\$instance->enrolstartdate != 0 && \$instance->enrolstartdate > time\(\)\) \{\n            return [^\n]*\n        \}\n#\n# or die "e2: pattern did not match\n";

# E3: the enrolment end date is ignored.
s#\n        if \(\$instance->enrolenddate != 0 && \$instance->enrolenddate < time\(\)\) \{\n            return [^\n]*\n        \}\n#\n# or die "e3: pattern did not match\n";

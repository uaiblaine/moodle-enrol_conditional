# S1: the bulk delete also unenrols the selected users from enrolments of other methods.
s#\n                if \(\$instance->enrol !== \$name\) \{\n                    continue;\n                \}\n#\n# or die "s1: pattern did not match\n";

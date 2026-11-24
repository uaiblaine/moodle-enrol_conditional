# G3: enrol_user ignores the instance id asked for and tries every instance of the course.
s#\n            if \(\$params\['instanceid'\] && \(int\) \$instance->id !== \$params\['instanceid'\]\) \{\n                continue;\n            \}\n#\n# or die "g3: pattern did not match\n";

# H4: the tree is validated without the course context, so the reach check of each type is skipped.
s#\$this->validate_conditions\(\$json, \$instance, \\context_course::instance\(\$courseid\)\);#\$this->validate_conditions(\$json, \$instance, null);# or die "h4: pattern did not match\n";

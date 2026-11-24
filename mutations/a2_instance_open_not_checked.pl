# A2: enrol_conditional() skips the check that the instance is open to new enrolments.
s#\n        \$status = \$this->get_instance_status\(\$instance, false\);\n        if \(\$status !== true\) \{\n            throw new moodle_exception\('cannotenrolreason'[^\n]*\n        \}\n#\n# or die "a2: pattern did not match\n";

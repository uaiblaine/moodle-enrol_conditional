# E1: a disabled instance is offered.
s#\n        if \(\$instance->status != ENROL_INSTANCE_ENABLED\) \{\n            return get_string\('canntenrol', 'enrol_conditional'\);\n        \}\n#\n# or die "e1: pattern did not match\n";

# E4: an instance closed to new enrolments is offered.
s#\n        if \(!\$instance->customint6\) \{\n            // New enrols not allowed.\n            return get_string\('canntenrol', 'enrol_conditional'\);\n        \}\n#\n# or die "e4: pattern did not match\n";

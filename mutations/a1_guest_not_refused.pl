# A1: enrol_conditional() no longer refuses a guest or a visitor itself.
s#\n        if \(!isloggedin\(\) \|\| isguestuser\(\)\) \{\n            throw new moodle_exception\('noguestaccess', 'enrol'\);\n        \}\n#\n# or die "a1: pattern did not match\n";

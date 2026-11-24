# M3: the key form's access check no longer refuses a guest.
s#\n        if \(isguestuser\(\)\) \{\n            throw new \\moodle_exception\('noguestaccess', 'enrol'\);\n        \}\n#\n# or die "m3: pattern did not match\n";

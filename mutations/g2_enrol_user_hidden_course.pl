# G2: enrol_user enrols into a course the caller may not see.
s#\n        if \(!\\core_course_category::can_view_course_info\(\$course\)\) \{\n            throw new \\moodle_exception\('coursehidden'\);\n        \}\n#\n# or die "g2: pattern did not match\n";

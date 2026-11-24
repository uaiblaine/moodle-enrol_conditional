# F5: get_instance_info answers for a course the caller may not see.
s#\n        if \(!\\core_course_category::can_view_course_info\(\$course\) && !can_access_course\(\$course\)\) \{\n            throw new \\moodle_exception\('coursehidden'\);\n        \}\n#\n# or die "f5: pattern did not match\n";

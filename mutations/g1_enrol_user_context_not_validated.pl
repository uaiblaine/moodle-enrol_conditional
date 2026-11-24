# G1: enrol_user no longer validates its context.
s#\n        self::validate_context\(\\context_course::instance\(\$course->id\)->get_parent_context\(\)\);\n#\n# or die "g1: pattern did not match\n";

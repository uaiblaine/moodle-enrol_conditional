# F2: get_instance_info no longer validates its context.
s#\n        self::validate_context\(\\context_course::instance\(\$course->id\)->get_parent_context\(\)\);\n#\n# or die "f2: pattern did not match\n";

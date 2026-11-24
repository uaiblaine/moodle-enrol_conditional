# L1: can_add_instance() no longer needs moodle/course:enrolconfig.
s#!has_capability\('moodle/course:enrolconfig', \$context\) \|\| ##  or die "l1: pattern did not match\n";

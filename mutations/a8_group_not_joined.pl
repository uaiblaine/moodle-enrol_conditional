# A8: the group whose key was posted is never found, so the user is not added to it.
s#\$groupid = enrolment_key::find_group\(\(int\) \$instance->courseid, \$key\);#\$groupid = null;# or die "a8: pattern did not match\n";

# A19: the group is joined on the id found before the enrolment, without checking it still exists.
s#\$group = \$DB->get_record\('groups', \['id' => \$groupid, 'courseid' => \$instance->courseid\]\);#\$group = \$groupid;# or die "a19: pattern did not match\n";

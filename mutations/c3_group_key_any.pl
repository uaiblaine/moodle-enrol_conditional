# C3: on an instance that accepts group keys, any key is accepted.
s#return self::find_group\(\(int\) \$instance->courseid, \$key\) === null \? self::REFUSED_GROUP : self::ACCEPTED;#return self::ACCEPTED;# or die "c3: pattern did not match\n";

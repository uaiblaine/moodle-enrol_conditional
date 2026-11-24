# G4: enrol_user lists the disabled instances of the course too.
s#enrol_get_instances\(\$course->id, true\)#enrol_get_instances(\$course->id, false)# or die "g4: pattern did not match\n";

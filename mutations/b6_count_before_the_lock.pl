# B6: enrol_conditional() counts the places before the lock as well, and decides on that earlier count.
s#\$status = \$this->get_instance_status\(\$instance, false\);#\$status = \$this->get_instance_status(\$instance, true);# or die "b6: pattern did not match\n";

# B8: is_self_enrol_available() does not count the places.
s#return \$this->get_instance_status\(\$instance, true\);#return \$this->get_instance_status(\$instance, false);# or die "b8: pattern did not match\n";

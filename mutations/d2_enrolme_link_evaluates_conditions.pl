# D2: the "Enrol me" link evaluates the conditions.
s#return \$this->get_self_enrol_status\(\$instance, false, false\) === true;#return \$this->get_self_enrol_status(\$instance, false, true) === true;# or die "d2: pattern did not match\n";
